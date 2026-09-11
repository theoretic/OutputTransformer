<?php
/*
OutputTransformer 0.3.0 cleanup, frozen for comparison

findChunks() and cleanup() exactly as they were at a5851f4, and apply() as it
chunked every stage. tests/run.php and the page-diff scripts run this side
by side with 0.4.0 to show what changed. Not loaded by the module.

AT
11.09.26
*/

class OutputTransformerLegacy030 {

	/**
	 * @param callable|null $transform  the stage's transformation; cleanup() when omitted
	 */
	public static function apply($html, $stage = 'cleanup', ?callable $transform = null) {
		$transform = $transform ?: [self::class, 'cleanup'];
		$transformationTags = ["no-$stage"];
		if($stage === 'cleanup') $transformationTags[] = 'textarea';
		$chunks = self::findChunks($html, $transformationTags);
		$html = '';
		foreach($chunks as $chunk) {
			$chunk = (Object)$chunk;
			$html .= isset($chunk->tags) ? $chunk->html : $transform($chunk->html);
		}
		foreach($chunks as $chunk) {
			$chunk = (Object)$chunk;
			foreach($transformationTags as $transformationTag) {
				if($transformationTag === 'textarea' || !isset($chunk->tags)) continue;
				foreach($chunk->tags as $chunkTag) {
					if(!strstr($chunkTag, $transformationTag)) continue;
					$html = str_replace($chunkTag, '', $html);
				}
			}
		}
		return $html;
	}

	public static function findChunks($html, $tags) {
		//output structure: $chunks[] = ['html' => $html, 'tagged' => $tagged ];
		if (empty($tags)) {
			return [['html' => $html]];
		}

		// Build a regex that matches opening or closing tags from the $tags list
		$tagPattern = implode('|', array_map('preg_quote', $tags));
		$regex = '/<(\/)?' .					// optional closing slash
				 '(' . $tagPattern . ')' .		// tag name
				 '(\s[^>]*)?' .					// optional attributes
				 '>/i';

		// Split the HTML into tokens: either a relevant tag or plain text between tags
		$tokens = preg_split($regex, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		/*
		 * After PREG_SPLIT_DELIM_CAPTURE the tokens array contains:
		 *   [text, closing_slash_or_empty, tag_name, attributes_or_empty, text, ...]
		 * We walk through them and reconstruct full tag strings.
		 */
		$chunks   = [];
		$current  = '';	   // accumulated HTML for the current chunk
		$depth	= 0;		// nesting depth of tracked tags
		$transformationTags = [];	  // tags found in the current chunk

		$i = 0;
		$count = count($tokens);

		while ($i < $count) {
			$token = $tokens[$i];

			// Check whether this token is a tag-name capture (the 2nd capture group)
			// We detect this by looking at the surrounding captures.
			// Layout per match: [slash, name, attrs]  (indices shift by 3 each match)
			// Instead, we re-scan each token against the regex to decide.
			if (preg_match('/^' . '(\/)?' . '(' . $tagPattern . ')' . '(\s[^>]*)?$' . '/i', $token)) {
				// This token IS one of [slash|name|attrs] capture groups – skip raw captures,
				// they are handled below when we identify the tag name token.
				$i++;
				continue;
			}

			// Try to find the next full tag by checking if the NEXT tokens form [slash, name, attrs]
			// Actually, let's just re-tokenise properly using preg_match_all for clarity.
			$i++;
		}

		// -----------------------------------------------------------------------
		// Cleaner approach: use preg_match_all to get all tags + positions,
		// then walk through the original string positionally.
		// -----------------------------------------------------------------------
		$pattern = '/<(\/)?(' . $tagPattern . ')(\s[^>]*)?\s*>/i';
		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

		$pos	 = 0;
		$depth   = 0;
		$current = '';
		$transformationTags = [];
		$chunks  = [];

		foreach ($matches as $match) {
			$fullTag   = $match[0][0];		   // e.g. <my-tag1 attr="value">
			$tagOffset = $match[0][1];		   // byte offset in $html
			$isClosing = $match[1][0] !== '';	// true if </…>
			$tagName   = $match[2][0];		   // e.g. my-tag1

			//  Text BEFORE this tag 
			$before = substr($html, $pos, $tagOffset - $pos);

			if ($depth === 0) {
				// We are outside any tracked tag.
				// Flush $before as an untagged chunk (if non-empty after trim).
				$trimmed = trim($before);
				if ($trimmed !== '') {
					$chunks[] = ['html' => $trimmed];
				}
				// Start a new tagged chunk.
				$current   = '';
				$transformationTags = [];
			} else {
				// We are inside a tracked tag – just accumulate.
				$current .= $before;
			}

			//  Process the tag itself 
			$current .= $fullTag;

			// Reconstruct a "clean" tag label for the tags array
			if ($isClosing) {
				$transformationTags[] = '</' . $tagName . '>';
				$depth--;
			} else {
				// Build opening tag label (keep attributes)
				$attrs = isset($match[3][0]) ? $match[3][0] : '';
				$transformationTags[] = '<' . $tagName . $attrs . '>';
				$depth++;
			}

			$pos = $tagOffset + strlen($fullTag);

			//  If depth hits 0 we just closed the outermost tag  flush chunk 
			if ($depth === 0) {
				$chunks[] = [
					'html' => trim($current),
					'tags' => $transformationTags,
				];
				$current   = '';
				$transformationTags = [];
			}
		}

		//  Any remaining text after the last tag 
		$tail = substr($html, $pos);
		if ($tail !== false) {
			$trimmed = trim($tail);
			if ($trimmed !== '') {
				$chunks[] = ['html' => $trimmed];
			}
		}

		return $chunks;
	}

	public static function cleanup($html) {
		$search=["\n","\r","\t"];
		$replace=[" ",""," "];
		$html=str_replace($search,$replace,$html);
		
		//removing comments
		$html=preg_replace("/<\!--(.*?)-->/","",$html);

		//merging whitespaces
		$html=preg_replace("/( {2,})/"," ",$html);

		//removing whitespaces between tags
		$html=str_replace("> <","><",$html);

		//removing leading and trailing spaces near tags
		$html = preg_replace_callback(
			'~<([a-z]+)([^>]*)>([^<^>]*?)</\g{1}>~U',
			function( $matches ){
				return "<{$matches[1]}{$matches[2]}>" . trim($matches[3]) . "</{$matches[1]}>";
			},
			$html
		);

		$html = str_replace('> <', '><', $html);
		$html = str_replace(' >', '>', $html);
		$html = str_replace('< ', '<', $html);

		//removing single and double quotes for attribs like id, style etc.
		$html = preg_replace( '/="([^":=\s]+?)"/mi', '=$1', $html );
		$html = preg_replace( '/=\'([^":=\s]+?)\'/mi', '=$1', $html );

		//removing whitespaces at the beginning and end of attribute values
		$html = str_replace('=" ', '="', $html);
		//$html = str_replace(' "', '"', $html);

		//removing unused ; in attribute values
		$html = str_replace(';"', '"', $html);

		return $html;
	}
}
