<?php
/*
Output Transformer: cleanup

Minifies HTML without changing what it means. Up to 0.3.0 cleanup ran
document-wide string rules that could not tell an attribute from a script
from text, and so corrupted values -- an admin form round-tripping
value=" | " as "| " was how it surfaced -- turned "x < y" into a tag opener
and joined script lines into their // comments.

Now the page is split into segments first and each kind has its own rules:

  comment    dropped, except conditional comments and <!--noindex-->
  CDATA, <!DOCTYPE ...>
             verbatim
  script, pre, textarea
             start tag rebuilt, content verbatim; unclosed: verbatim to the end
  style      start tag rebuilt, whitespace in the content collapsed
  tag        rebuilt attribute by attribute; values stay byte-identical except
             class/rel/srcset/sizes (trimmed, collapsed) and style (trimmed,
             trailing ; dropped); quotes dropped only where HTML allows it
  text       \r removed, \n and \t to spaces, runs of spaces collapsed

Kept from 0.3.0 on purpose, because pages were styled against it: whitespace
between two tags is removed, and so is whitespace around the text of an
element holding nothing but text. (0.3.0 missed that for h1-h6, whose names
its [a-z]+ pattern did not match; headings are blocks, so nothing visible
changes.) xmp and plaintext are not supported.

Whitespace here is [ \t\n\r\f], never \s with /u: that matches U+00A0, and a
non-breaking space is content.

No ProcessWire dependency, so tests/run.php can load it directly.

AT
11.09.26
*/

namespace ProcessWire;

class OutputTransformerCleanup {

	const WS = " \t\n\r\f";

	//attributes whose values are whitespace-separated lists
	const LIST_ATTRIBUTES = ['class', 'rel', 'srcset', 'sizes'];

	const SEGMENTS = '~
		(?<comment><!--.*?-->)
		|(?<cdata><!\[CDATA\[.*?\]\]>)
		|(?<declaration><![a-zA-Z][^>]*+>)
		|(?<raw><(?<rawname>script|style|pre|textarea)\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*+>)(?<rawbody>.*?)(?<rawend></(?P=rawname)[ \t\n\r\f]*>|\z)
		|(?<tag></?[a-zA-Z][^ \t\n\r\f/>]*+(?:"[^"]*"|\'[^\']*\'|[^\'">])*+>)
		~six';

	/**
	 * @return string|null  null on a PCRE error, so the caller can keep the input
	 */
	public static function run($html) {

		if(preg_match_all(self::SEGMENTS, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) return null;

		//pieces: [kind, html, tagName, isClosing]; kind is text|tag|keep
		$pieces = [];
		$pos = 0;

		foreach($matches as $m) {
			$start = $m[0][1];
			if($start > $pos) $pieces[] = ['text', substr($html, $pos, $start - $pos), '', false];
			$pos = $start + strlen($m[0][0]);

			if(self::has($m, 'comment')) {
				$comment = $m['comment'][0];
				if(self::keepComment($comment)) $pieces[] = ['keep', $comment, '', false];
				continue;
			}

			if(self::has($m, 'cdata')) {
				$pieces[] = ['keep', $m['cdata'][0], '', false];
				continue;
			}

			if(self::has($m, 'declaration')) {
				$pieces[] = ['keep', $m['declaration'][0], '', false];
				continue;
			}

			if(self::has($m, 'raw')) {
				$name = strtolower($m['rawname'][0]);
				$body = self::has($m, 'rawbody') ? $m['rawbody'][0] : '';
				$end = self::has($m, 'rawend') ? $m['rawend'][0] : '';
				if($name === 'style') $body = self::collapse($body);
				$end = $end === '' ? '' : "</{$m['rawname'][0]}>";
				$pieces[] = ['keep', self::rebuildTag($m['raw'][0]) . $body . $end, '', false];
				continue;
			}

			$tag = $m['tag'][0];
			$isClosing = $tag[1] === '/';
			preg_match('~^</?([^ \t\n\r\f/>]+)~', $tag, $nm);
			$pieces[] = ['tag', self::rebuildTag($tag), strtolower($nm[1]), $isClosing];
		}
		if($pos < strlen($html)) $pieces[] = ['text', substr($html, $pos), '', false];

		//merging text pieces that became neighbours when a comment was dropped
		$merged = [];
		foreach($pieces as $piece) {
			$last = count($merged) - 1;
			if($piece[0] === 'text' && $last >= 0 && $merged[$last][0] === 'text') {
				$merged[$last][1] .= $piece[1];
				continue;
			}
			$merged[] = $piece;
		}

		$out = '';
		$count = count($merged);
		foreach($merged as $i => $piece) {

			if($piece[0] !== 'text') {
				$out .= $piece[1];
				continue;
			}

			$text = self::collapse($piece[1]);
			$prev = $merged[$i - 1] ?? null;
			$next = $merged[$i + 1] ?? null;

			//whitespace between two tags: dropped, as 0.3.0 did with "> <"
			if(trim($text, self::WS) === '' && $prev && $next) continue;

			//the only text of an element: trimmed, as 0.3.0 did for leaf elements
			if($prev && $next && $prev[0] === 'tag' && $next[0] === 'tag' && !$prev[3] && $next[3] && $prev[2] === $next[2]) {
				$text = trim($text, self::WS);
			}

			$out .= $text;
		}

		return trim($out, self::WS);
	}

	/**
	 * Rebuilds a start or end tag: one space between attributes, values left as
	 * they are but for the documented normalisations. Anything it cannot fully
	 * account for is returned untouched.
	 */
	public static function rebuildTag($tag) {

		if(!preg_match('~^<(/?)([^ \t\n\r\f/>]+)(.*)>$~s', $tag, $parts)) return $tag;
		list(, $slash, $name, $inner) = $parts;

		if($slash === '/') return "</$name>";

		//a trailing / closes the tag only when it is not the end of an unquoted value
		$selfClosing = (bool) preg_match('~(?:^|["\' \t\n\r\f])/[ \t\n\r\f]*$~', $inner);
		if($selfClosing) $inner = preg_replace('~/[ \t\n\r\f]*$~', '', $inner);

		$attrRegex = '~([^ \t\n\r\f"\'>/=]+)(?:[ \t\n\r\f]*=[ \t\n\r\f]*(?:"([^"]*)"|\'([^\']*)\'|([^ \t\n\r\f>"\'=<`]+)))?~';
		if(preg_match_all($attrRegex, $inner, $attrs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) return $tag;

		//everything outside the attributes must be whitespace, or the tag is left alone
		$rest = $inner;
		foreach(array_reverse($attrs) as $attr) $rest = substr_replace($rest, '', $attr[0][1], strlen($attr[0][0]));
		if(trim($rest, self::WS) !== '') return $tag;

		$out = "<$name";
		$last = count($attrs) - 1;

		foreach($attrs as $i => $attr) {
			$attrName = $attr[1][0];
			$out .= " $attrName";

			if(isset($attr[2]) && $attr[2][1] >= 0) {
				$quote = '"';
				$value = $attr[2][0];
			}
			elseif(isset($attr[3]) && $attr[3][1] >= 0) {
				$quote = "'";
				$value = $attr[3][0];
			}
			elseif(isset($attr[4]) && $attr[4][1] >= 0) {
				$out .= '=' . $attr[4][0];
				continue;
			}
			else {
				continue;
			}

			$lower = strtolower($attrName);
			if(in_array($lower, self::LIST_ATTRIBUTES, true)) {
				$value = self::collapse(trim($value, self::WS));
			}
			elseif($lower === 'style') {
				$value = rtrim(trim($value, self::WS), ';');
			}

			$keepQuotes = $selfClosing && $i === $last;
			$out .= '=' . (!$keepQuotes && self::canUnquote($value) ? $value : $quote . $value . $quote);
		}

		return $out . ($selfClosing ? '/' : '') . '>';
	}

	/**
	 * Whether a value may go unquoted: HTML5 allows it without whitespace,
	 * quotes, = < > or backtick. Colons stay quoted as in 0.3.0, a trailing /
	 * would read as self-closing, and a masked region may hide anything.
	 */
	public static function canUnquote($value) {
		if($value === '') return false;
		if(substr($value, -1) === '/') return false;
		if(strpos($value, OutputTransformerMasker::RESERVED_PREFIX) !== false) return false;
		return (bool) preg_match('~^[^ \t\n\r\f"\'=<>`:]+$~', $value);
	}

	public static function keepComment($comment) {
		return (bool) preg_match('~^<!--(?:\[if\b|<!\[endif\]|/?noindex-->)~i', $comment);
	}

	public static function collapse($text) {
		$text = str_replace("\r", '', $text);
		$text = strtr($text, "\n\t\f", '   ');
		return preg_replace('~ {2,}~', ' ', $text);
	}

	private static function has(array $m, $group) {
		return isset($m[$group]) && $m[$group][1] >= 0 && $m[$group][0] !== '';
	}
}
