<?php
/*
Output Transformer: masking

Every transformation runs over the whole page, so anything it must not touch
is swapped out for an opaque placeholder first and put back afterwards:
<no-x> wrapper content, the elements a stage has no business in, and the
patterns listed in the module settings.

A placeholder is U+F8F0, the index as U+F8E0-U+F8EF hex digits, U+F8F1:
private-use characters, no ASCII, nothing a typographer, a regex or a
whitespace rule would match -- and not the U+E000-U+E004 range EMT v3 uses
for its own placeholders.

No ProcessWire dependency: run() takes the transformation as a callable, so
tests/run.php can drive the whole pipeline with plain PHP.

AT
11.09.26
*/

namespace ProcessWire;

class OutputTransformerMasker {

	const OPEN = "\u{F8F0}";
	const CLOSE = "\u{F8F1}";
	const DIGIT_BASE = 0xF8E0;

	//UTF-8 lead bytes shared by U+F8C0-U+F8FF: finding them is enough to refuse
	const RESERVED_PREFIX = "\xEF\xA3";

	/**
	 * Full pipeline for one stage, with the failure policy: if anything goes
	 * wrong -- a PCRE error, an exception, a placeholder that does not come back
	 * exactly once -- the stage is skipped and the page goes out as it came in,
	 * minus this stage's <no-x> wrapper tags.
	 *
	 * @param string   $html
	 * @param string   $stage      cleanup, typografy or replace
	 * @param callable $transform  fn(string): ?string
	 * @param array    $rules      [[regex, keepGroup|null], ...] masked in this order
	 * @param callable $log        fn(string)
	 */
	public static function run($html, $stage, callable $transform, array $rules, ?callable $log = null) {

		if(!is_string($html) || $html === '') return $html;

		$fail = function($why) use ($html, $stage, $log) {
			if($log) $log("$stage skipped: $why");
			return self::stripWrapper($html, $stage);
		};

		if(strpos($html, self::RESERVED_PREFIX) !== false) {
			return $fail('the page already contains characters from the placeholder range U+F8C0-U+F8FF');
		}

		$masker = new self();
		$masked = $masker->mask($html, $rules, $log);
		if($masked === null) return $fail('masking failed: ' . self::pcreError());

		try {
			$out = $transform($masked);
		}
		catch(\Throwable $e) {
			return $fail(get_class($e) . ': ' . $e->getMessage());
		}
		if(!is_string($out)) return $fail('the transformation returned no string: ' . self::pcreError());

		$restored = $masker->restore($out);
		if($restored === null) return $fail('a placeholder was lost or duplicated by the transformation');

		return self::stripWrapper($restored, $stage);
	}

	/**
	 * Removes a stage's <no-x> tags that are left: unmatched ones, or all of
	 * them when the stage does not run for this template.
	 */
	public static function stripWrapper($html, $stage) {
		$out = preg_replace('~</?no-' . preg_quote($stage, '~') . '\b[^>]*>~i', '', $html);
		return is_string($out) ? $out : $html;
	}

	/**
	 * The wrapper rule for a stage: <no-x>content</no-x> is kept, wrapper dropped.
	 */
	public static function wrapperRule($stage) {
		$tag = preg_quote("no-$stage", '~');
		return ["~<$tag\b[^>]*>(.*?)</$tag\s*>~si", 1];
	}

	/**
	 * Rules for whole elements: <name ...>...</name>, quote-aware in the start tag.
	 */
	public static function elementRule(array $names) {
		$names = implode('|', array_map(function($n) { return preg_quote($n, '~'); }, $names));
		return ["~<($names)\b(?:\"[^\"]*\"|'[^']*'|[^'\">])*+>.*?</\\1[ \\t\\n\\r\\f]*>~si", null];
	}

	/**
	 * Settings textarea -> rules. One regex per line, delimiters included.
	 * A pattern that does not compile is left out and reported.
	 */
	public static function userRules($text, ?callable $log = null) {
		$rules = [];
		foreach(preg_split('~\R~', (string) $text) as $line) {
			$pattern = trim($line);
			if($pattern === '') continue;
			if(@preg_match($pattern, '') === false) {
				if($log) $log("ignoring invalid pattern $pattern");
				continue;
			}
			$rules[] = [$pattern, null];
		}
		return $rules;
	}

	public static function pcreError() {
		return function_exists('preg_last_error_msg') ? preg_last_error_msg() : (string) preg_last_error();
	}

////

	private $store = [];

	/**
	 * Replaces every match of every rule with a placeholder; later rules see
	 * earlier placeholders, and restore() unwinds them in reverse. Returns null
	 * on a PCRE error.
	 */
	public function mask($html, array $rules, ?callable $log = null) {

		foreach($rules as $rule) {
			list($regex, $keepGroup) = $rule;

			$next = preg_replace_callback($regex, function($m) use ($keepGroup) {
				$this->store[] = $keepGroup === null ? $m[0] : ($m[$keepGroup] ?? '');
				return $this->token(count($this->store) - 1);
			}, $html);

			if(!is_string($next)) {
				if($log) $log("pattern $regex failed: " . self::pcreError());
				return null;
			}
			$html = $next;
		}

		return $html;
	}

	/**
	 * Puts the masked text back, last placeholder first. Returns null unless
	 * every placeholder is found exactly once and none is left over.
	 */
	public function restore($html) {

		for($i = count($this->store) - 1; $i >= 0; $i--) {
			$token = $this->token($i);
			if(substr_count($html, $token) !== 1) return null;
			$html = str_replace($token, $this->store[$i], $html);
		}

		if(strpos($html, self::RESERVED_PREFIX) !== false) return null;

		return $html;
	}

	private function token($i) {
		$digits = '';
		foreach(str_split(dechex($i)) as $hex) $digits .= mb_chr(self::DIGIT_BASE + hexdec($hex), 'UTF-8');
		return self::OPEN . $digits . self::CLOSE;
	}
}
