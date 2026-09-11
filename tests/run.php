<?php
/*
OutputTransformer regression cases

  php tests/run.php            run the cases, exit 1 on any failure
  php tests/run.php -v         also print what 0.3.0 made of each input

Plain PHP, no ProcessWire: loads the masker and the cleanup directly and runs
them through the same pipeline the module uses (OutputTransformerMasker::run
with the stage's rules). legacy030.php is 0.3.0's cleanup, shown for contrast.

AT
11.09.26
*/

namespace ProcessWire;

require __DIR__ . '/../OutputTransformerMasker.php';
require __DIR__ . '/../OutputTransformerCleanup.php';
require __DIR__ . '/legacy030.php';

$verbose = in_array('-v', $argv, true);
$logged = [];
$log = function($message) use (&$logged) { $logged[] = $message; };

//the module's cleanup stage: wrapper rule + settings patterns, then the segmenter
$cleanup = function($html, array $keep = []) use ($log) {
	$rules = array_merge([OutputTransformerMasker::wrapperRule('cleanup')], OutputTransformerMasker::userRules(implode("\n", $keep), $log));
	return OutputTransformerMasker::run($html, 'cleanup', function($s) { return OutputTransformerCleanup::run($s); }, $rules, $log);
};

//the module's replace stage with the kit sites' two rules
$replace = function($html) use ($log) {
	$rules = [OutputTransformerMasker::wrapperRule('replace'), OutputTransformerMasker::elementRule(['script', 'style', 'textarea'])];
	return OutputTransformerMasker::run($html, 'replace', function($s) {
		$s = mb_ereg_replace('\s&\s', '&nbsp;&&nbsp;', $s);
		return mb_ereg_replace(' --', ' &mdash;', $s);
	}, $rules, $log);
};

$nl = "\n";
$cases = [
	//[label, stage callable, input, expected]
	['separator input keeps its spaces', $cleanup, '<input class="uk-input" name="title_separator" type="text" value=" | ">', '<input class=uk-input name=title_separator type=text value=" | ">'],
	['placeholder and double spaces kept', $cleanup, '<input placeholder=" поиск " value="a  b">', '<input placeholder=" поиск " value="a  b">'],
	['option value kept', $cleanup, '<select><option value=" x">X</option></select>', '<select><option value=" x">X</option></select>'],
	['trailing ; in a value kept', $cleanup, '<input value="a b;">', '<input value="a b;">'],
	['< in text is not a tag', $cleanup, '<p>если x < y, то</p>', '<p>если x < y, то</p>'],
	['> in text kept', $cleanup, '<p>5 > 3</p>', '<p>5 > 3</p>'],
	['script verbatim', $cleanup, "<script>var a = 1; // note{$nl}var b = \"x\";</script>", "<script>var a = 1; // note{$nl}var b = \"x\";</script>"],
	['ld+json verbatim', $cleanup, '<script type="application/ld+json">{"a": "b;"}</script>', '<script type=application/ld+json>{"a": "b;"}</script>'],
	['pre verbatim', $cleanup, "<pre>line 1{$nl}    indented</pre>", "<pre>line 1{$nl}    indented</pre>"],
	['textarea verbatim', $cleanup, "<textarea> x  y {$nl}</textarea>", "<textarea> x  y {$nl}</textarea>"],
	['unclosed script verbatim to the end', $cleanup, "<p>a</p><script>x = 1;{$nl}  y = 2;", "<p>a</p><script>x = 1;{$nl}  y = 2;"],
	['style collapsed, selector quotes kept', $cleanup, "<style>a[href=\"/x\"] {{$nl}  color: red;{$nl}}</style>", '<style>a[href="/x"] { color: red; }</style>'],
	['attribute spacing, class collapsed', $cleanup, '<div   class="  a   b "  id="x"  >', '<div class="a b" id=x>'],
	['style attribute: trailing ; dropped', $cleanup, '<p style="color:red;">t</p>', '<p style="color:red">t</p>'],
	['empty value stays quoted', $cleanup, '<img src="a.png" alt="">', '<img src=a.png alt="">'],
	['self-closing: last value keeps quotes', $cleanup, '<meta content="article"/><circle r="10"/>', '<meta content="article"/><circle r="10"/>'],
	['unquoted value ending in / is not self-closing', $cleanup, '<a href=/x/>t</a>', '<a href=/x/>t</a>'],
	['> inside a value does not end the tag', $cleanup, '<div data-a="x>y">t</div>', '<div data-a="x>y">t</div>'],
	['quote of the other kind kept', $cleanup, "<p title='it\"s'>t</p>", "<p title='it\"s'>t</p>"],
	['colon values stay quoted (as in 0.3.0)', $cleanup, '<a href="https://x.ru/a">t</a>', '<a href="https://x.ru/a">t</a>'],
	['doctype kept, whitespace after it glued', $cleanup, "<!DOCTYPE html>{$nl}<html lang=\"ru\">", '<!DOCTYPE html><html lang=ru>'],
	['heading text trimmed like other leaf elements', $cleanup, "<h3>{$nl}\tТекст{$nl}</h3>", '<h3>Текст</h3>'],
	['comments dropped', $cleanup, '<p>a</p> <!-- note --> <p>b</p>', '<p>a</p><p>b</p>'],
	['conditional and noindex comments kept', $cleanup, '<!--[if IE]><p>x</p><![endif]--><!--noindex--><p>y</p><!--/noindex-->', '<!--[if IE]><p>x</p><![endif]--><!--noindex--><p>y</p><!--/noindex-->'],
	['whitespace between tags still glued (0.3.0)', $cleanup, '<a>x</a> <a>y</a>', '<a>x</a><a>y</a>'],
	['text of a leaf element trimmed (0.3.0)', $cleanup, '<b> bold </b> rest', '<b>bold</b> rest'],
	['text whitespace collapsed', $cleanup, "<p>a{$nl}{$nl}   b\tc</p>", '<p>a b c</p>'],
	['raw NBSP kept', $cleanup, "<p>a\u{00A0}b</p>", "<p>a\u{00A0}b</p>"],
	['no-cleanup wrapper: content kept, wrapper gone, spaces kept', $cleanup, 'a <no-cleanup><link rel=x href="y" media=\'none\'></no-cleanup> c', "a <link rel=x href=\"y\" media='none'> c"],
	['textarea inside a script string loses nothing', $cleanup, "<script>var t = '<textarea>';</script><p>x</p>", "<script>var t = '<textarea>';</script><p>x</p>"],
	['a keep pattern protects what nothing else foresaw', function($h) use ($cleanup) { return $cleanup($h, ['~<p class="keep">.*?</p>~s']); }, "<p class=\"keep\">a  {$nl}b</p><p>c  d</p>", "<p class=\"keep\">a  {$nl}b</p><p>c d</p>"],
	['an invalid keep pattern is skipped, not fatal', function($h) use ($cleanup) { return $cleanup($h, ['~unclosed[']); }, '<p>a  b</p>', '<p>a b</p>'],
	['input with placeholder-range characters returned unchanged', $cleanup, "<p>a  \u{F8F0}b</p> <no-cleanup>x</no-cleanup>", "<p>a  \u{F8F0}b</p> x"],
	['replace leaves script and style alone', $replace, "<p>a & b --c</p><script>if (a & b) i --;</script><style>:root{ --gut: 1rem }</style>", "<p>a&nbsp;&&nbsp;b &mdash;c</p><script>if (a & b) i --;</script><style>:root{ --gut: 1rem }</style>"],
];

$failed = 0;
foreach($cases as list($label, $stage, $input, $expected)) {
	$actual = $stage($input);
	$ok = $actual === $expected;
	if(!$ok) $failed++;
	printf("%s %s\n", $ok ? 'ok  ' : 'FAIL', $label);
	if(!$ok) {
		echo "     in:       ", json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
		echo "     expected: ", json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
		echo "     actual:   ", json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
	}
	if($verbose && $stage === $cleanup) echo "     0.3.0:    ", json_encode(\OutputTransformerLegacy030::apply($input), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}

//failure policy: a transformation that throws or loses a placeholder leaves the page as it was
$html = '<p>a  b</p><no-cleanup>kept</no-cleanup>';
$thrown = OutputTransformerMasker::run($html, 'cleanup', function($s) { throw new \RuntimeException('boom'); }, [OutputTransformerMasker::wrapperRule('cleanup')], $log);
$lost = OutputTransformerMasker::run($html, 'cleanup', function($s) { return preg_replace('~\x{F8F0}.*?\x{F8F1}~u', '', $s); }, [OutputTransformerMasker::wrapperRule('cleanup')], $log);
foreach(['throwing transformation' => $thrown, 'lost placeholder' => $lost] as $label => $out) {
	$ok = $out === '<p>a  b</p>kept';
	if(!$ok) $failed++;
	printf("%s failure policy: %s -> input kept, wrapper stripped%s\n", $ok ? 'ok  ' : 'FAIL', $label, $ok ? '' : ' -- got ' . json_encode($out));
}

//a big page never comes back empty
$big = str_repeat('<div class="  x  "><p title=" t ">text  text</p><script>a = 1; // c' . $nl . 'b = 2;</script></div>' . $nl, 25000);
$t = microtime(true);
$out = $cleanup($big);
$ms = round((microtime(true) - $t) * 1000);
$ok = is_string($out) && strlen($out) > 0 && strlen($out) < strlen($big);
if(!$ok) $failed++;
printf("%s %s MB page: %s KB out in %d ms\n", $ok ? 'ok  ' : 'FAIL', round(strlen($big) / 1048576, 1), round(strlen((string) $out) / 1024), $ms);

if($logged) echo "\nlogged:\n  " . implode("\n  ", array_unique($logged)) . "\n";
echo $failed ? "\n$failed FAILED\n" : "\nall passed\n";
exit($failed ? 1 : 0);
