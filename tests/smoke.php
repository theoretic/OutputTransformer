<?php namespace ProcessWire;

/**
 * Smoke test: OutputTransformer against a bootstrapped ProcessWire install
 *
 * Usage: php tests/smoke.php /path/to/pw/webroot
 *
 * Run it against a Bridge sandbox or another disposable install, never a live site: rendering
 * writes the module's log when a test rule fails on purpose. Settings are not saved.
 *
 * The install should load this module from this repo (a junction, or a copy in site/modules).
 * If it does not have the module, the module file of this repo is required directly.
 *
 * Every Page::render hook is removed first, so site hooks (StaticPages, SeoNeo...) neither run
 * nor interfere. Page output comes from a before hook that replaces render() with fixtures,
 * so the module's own after hooks run exactly as they do on a real request.
 *
 * @author AT
 * @author Claude
 */

if(PHP_SAPI !== 'cli') exit;

$root = rtrim(str_replace('\\', '/', $argv[1] ?? (getenv('PW_ROOT') ?: '')), '/');
if(!is_file("$root/index.php")) exit("Usage: php tests/smoke.php /path/to/pw/webroot\n");

//some site configs read it (StaticPages, document_root.php), CLI has none
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/index.php";

$fails = 0;
function check($label, $ok, $info = '') {
	global $fails;
	if(!$ok) $fails++;
	echo ($ok ? 'ok   ' : 'FAIL ') . $label . ($ok || $info === '' ? '' : "  [$info]") . "\n";
}
function show($value) {
	return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

//PHP warnings and notices raised from this module's files fail the test
$moduleDir = str_replace('\\', '/', dirname(__DIR__));
$phpErrors = array();
set_error_handler(function($no, $message, $file) use (&$phpErrors, $moduleDir) {
	if(!(error_reporting() & $no)) return false; //@-silenced on purpose
	if(strpos(str_replace('\\', '/', $file), $moduleDir) === 0) $phpErrors[] = "$message in " . basename($file);
	return false;
});

$config = $wire->config;
$pages = $wire->pages;
$modules = $wire->modules;
$templates = $wire->templates;

if(!class_exists(__NAMESPACE__ . '\\OutputTransformer', false)) {
	require dirname(__DIR__) . '/OutputTransformer.module.php';
}
$file = str_replace('\\', '/', (new \ReflectionClass(__NAMESPACE__ . '\\OutputTransformer'))->getFileName());
echo "PW " . $config->version . ", module " . OutputTransformer::getModuleInfo()['version'] . " from $file\n";
echo 'LanguageSupport: ' . ($wire->languages ? count($wire->languages) . ' languages' : 'none') . "\n";
echo 'stored settings: ' . show($modules->getConfig('OutputTransformer')) . "\n\n";
check('the module class is this build', strpos($file, $moduleDir) === 0 || basename(dirname($file)) === 'OutputTransformer', $file);

//// settings: defaults for new keys, old settings keep working

$version = OutputTransformer::getModuleInfo()['version'];
check('version is a semver string', is_string($version) && preg_match('~^\d+\.\d+\.\d+$~', $version) === 1, (string) $version);

$loaded = $modules->getModule('OutputTransformer', array('noInit' => true, 'noCache' => true));
check('new settings default to off on a stored config',
	!$loaded->emtAutoLinks && !$loaded->emtAutospaceAfter && !$loaded->replaceEmptyDeletes,
	show(array($loaded->emtAutoLinks, $loaded->emtAutospaceAfter, $loaded->replaceEmptyDeletes)));

//the keys every deployed version stored (020, 21, 0.3.0); 0.4.x added the keep patterns
$admin = $templates->get('admin');
$old = array(
	'cleanupPriority' => -1, 'typografyPriority' => -10, 'replacePriority' => '',
	'noCleanupTemplates' => array($admin->id), 'noTypografyTemplates' => array($admin->id), 'noReplaceTemplates' => array($admin->id),
	'replaceWhats' => '\s&\s', 'replaceBys' => '&nbsp;&&nbsp;', 'typograf_default' => '', 'isEnabled' => 1,
);
$legacy = $modules->getModule('OutputTransformer', array('noInit' => true, 'noCache' => true));
$modules->configs->setModuleConfigData($legacy, $old);
check('a 0.3.0 config keeps its values', $legacy->replacePriority === '' && $legacy->noReplaceTemplates === array($admin->id) && $legacy->replaceBys === '&nbsp;&&nbsp;');
check('a 0.3.0 config gets the new defaults', empty($legacy->keepCleanupPatterns) && !$legacy->emtAutoLinks && !$legacy->replaceEmptyDeletes);

$form = $modules->getModuleConfigInputfields('OutputTransformer');
$names = array();
foreach($form->getAll() as $f) $names[] = $f->attr('name');
check('settings form has the new fields', !array_diff(array('emtAutoLinks', 'emtAutospaceAfter', 'replaceEmptyDeletes', 'replaceWhats', 'replaceBys', 'isEnabled'), $names), implode(',', $names));
check('settings form has a typograf per language', count(preg_grep('~^typograf_~', $names)) === ($wire->languages ? count($wire->languages) : 1), implode(',', preg_grep('~^typograf_~', $names)));
check('replace textareas are not trimmed', $form->getChildByName('replaceBys') && $form->getChildByName('replaceBys')->noTrim);

//the settings file as core includes it, on a site without LanguageSupport
$noLanguages = new class extends Wire {
	public $api;
	public function wire($name = '', $value = null, $lock = false) { return $this->api; }
	public function read($file) { $config = null; include($file); return $config; }
};
$noLanguages->api = (object) array('templates' => $templates, 'languages' => null);
$read = $noLanguages->read(dirname(__DIR__) . '/OutputTransformer.config.php');
$readNames = is_array($read) ? array_column($read, 'name') : array();
check('settings without LanguageSupport: one typograf_default', array_values(preg_grep('~^typograf_~', $readNames)) === array('typograf_default'), implode(',', $readNames));

//// rendering: fixtures through the real Page::render hooks

$home = $pages->get(1);
$other = $pages->get("id>1, template!=admin, template!={$home->template->name}, include=all");
$adminPage = $pages->get(2);
$wire->wire('page', $home);
$lang = (new OutputTransformer())->languageName();

$fixture = array(); //"<id>" or "<id>:<arg>" => html or callable
$base = array(
	'isEnabled' => 1, 'cleanupPriority' => -1, 'typografyPriority' => -10, 'replacePriority' => -20,
	'noCleanupTemplates' => array(), 'noTypografyTemplates' => array(), 'noReplaceTemplates' => array(),
	'keepCleanupPatterns' => '', 'keepTypografyPatterns' => '', 'keepReplacePatterns' => '',
	'replaceWhats' => '\s&\s', 'replaceBys' => '&nbsp;&&nbsp;', "typograf_$lang" => '',
);

/**
 * A fresh module instance with these settings, its hooks the only Page::render hooks
 *
 */
function scenario(array $settings) {
	global $wire, $base, $fixture;
	$probe = new Page();
	foreach($wire->hooks->getHooks($probe, 'render') as $hook) $wire->hooks->removeHook($probe, $hook['id']);
	$m = $wire->wire(new OutputTransformer());
	$m->setArray(array_merge($base, $settings));
	$m->init();
	$wire->addHookBefore('Page::render', function(HookEvent $event) use (&$fixture) {
		$arg = $event->arguments(0);
		$key = $event->object->id . (is_string($arg) && $arg !== '' ? ":$arg" : '');
		if(!isset($fixture[$key])) return;
		$event->replace = true;
		$event->return = is_callable($fixture[$key]) ? $fixture[$key]() : $fixture[$key];
	});
	return $m;
}

$page = "<head>\n<no-cleanup><title> A  &  B </title></no-cleanup>\n</head>\n<p>x  &  y</p> <no-replace>q & r</no-replace>";
$fixture[$home->id] = $page;

scenario(array());
check('enabled: cleaned, replaced, markers gone, wrapped parts kept', $home->render() === '<head><title> A &nbsp;&&nbsp; B </title></head><p>x&nbsp;&&nbsp;y</p>q & r', show($home->render()));

scenario(array('isEnabled' => 0));
check('disabled: only the markers are removed', $home->render() === str_replace(array('<no-cleanup>', '</no-cleanup>', '<no-replace>', '</no-replace>'), '', $page), show($home->render()));

//a helper file missing (a deploy that uploaded the module file first): enabled, yet only the markers go
scenario(array()); //for its fixture hook; then drop the module's own hooks
$probe = new Page();
foreach($wire->hooks->getHooks($probe, 'render') as $hook) {
	if($hook['toObject'] instanceof OutputTransformer) $wire->hooks->removeHook($probe, $hook['id']);
}
$noHelper = $wire->wire(new class extends OutputTransformer {
	const HELPERS = array('OutputTransformerMasker.php', 'OutputTransformerNoSuchHelper.php');
});
$noHelper->setArray($base);
$noHelper->init();
check('helper missing: only the markers are removed', $home->render() === str_replace(array('<no-cleanup>', '</no-cleanup>', '<no-replace>', '</no-replace>'), '', $page), show($home->render()));
$hookNames = array();
foreach($wire->hooks->getHooks($probe, 'render') as $hook) if($hook['toObject'] === $noHelper) $hookNames[] = $hook['toMethod'];
check('helper missing: the strip hook is the only module hook', $hookNames === array('hookStripMarkers'), show($hookNames));

scenario(array('replacePriority' => ''));
check('stage off: its markers removed, the stage not run', $home->render() === '<head><title> A  &  B </title></head><p>x & y</p>q & r', show($home->render()));

scenario(array('noReplaceTemplates' => array($home->template->id), 'noCleanupTemplates' => array($home->template->id)));
check('template denied: markers removed, stages not run', $home->render() === str_replace(array('<no-cleanup>', '</no-cleanup>', '<no-replace>', '</no-replace>'), '', $page), show($home->render()));

scenario(array('noReplaceTemplates' => (string) $home->template->id));
check('a denied list stored as a string is no TypeError', strpos($home->render(), 'x & y') !== false, show($home->render()));

$fixture[$adminPage->id] = $page;
scenario(array());
check('admin: never touched, though not denied', $adminPage->render() === $page, show($adminPage->render()));

//partial and nested renders
if($other->id) {
	$fixture[$other->id] = '<p>X  <no-replace>Y</no-replace></p>';
	scenario(array('replaceWhats' => 'X', 'replaceBys' => 'XX'));
	check('a partial render alone is left as it is, markers included', $other->render() === $fixture[$other->id], show($other->render()));
	$fixture[$home->id] = function() use ($other) { return "<p>X</p>\n" . $other->render(); };
	check('a page rendering another page: transformed once, as a whole', $home->render() === '<p>XX</p><p>XX Y</p>', show($home->render()));
} else {
	echo "skip partial render checks: no second page\n";
}
$fixture["{$home->id}:body"] = '<p>X</p>';
$fixture[$home->id] = function() use ($home) { return '<p>X</p>' . $home->render('body'); };
scenario(array('replaceWhats' => 'X', 'replaceBys' => 'XX'));
check('the page rendering a field of itself: transformed once', $home->render() === '<p>XX</p><p>XX</p>', show($home->render()));

//content types
$json = "{\n  \"a\": \"x  &  y\",\n  \"b\": \"<no-cleanup>\"\n}";
$fixture[$home->id] = $json;
$contentType = $home->template->contentType;
$home->template->contentType = 'json';
scenario(array());
check('JSON template: untouched', $home->render() === $json, show($home->render()));
$home->template->contentType = 'html';
check('html template: transformed', $home->render() !== $json);
$home->template->contentType = $contentType;
$m = scenario(array());
check('Content-Type header application/json: not HTML', !$m->isHtmlResponse($home, array('X-A: b', 'Content-Type: application/json; charset=utf-8')));
check('Content-Type header text/html: HTML', $m->isHtmlResponse($home, array('Content-Type: text/html; charset=UTF-8')));
check('no Content-Type header: HTML', $m->isHtmlResponse($home, array()));

//replace rules
$fixture[$home->id] = '<p>A B C D E</p>';
scenario(array('replaceWhats' => "A\r\nB\r\nC", 'replaceBys' => "1\r\n2\r\n3"));
check('CRLF settings: every rule applies', $home->render() === '<p>1 2 3 D E</p>', show($home->render()));
scenario(array('replaceWhats' => "A\nB\nC\nD", 'replaceBys' => "0\n\n3"));
check('short and empty replacement lists: "0" applies, empty and missing skip', $home->render() === '<p>0 B 3 D E</p>', show($home->render()));
scenario(array('replaceWhats' => "A\nB\nC", 'replaceBys' => "1\n\n3", 'replaceEmptyDeletes' => 1));
check('empty replacement deletes when set', $home->render() === '<p>1  3 D E</p>', show($home->render()));
scenario(array('replaceWhats' => "A\n(\n\nC", 'replaceBys' => "1\nx\ny\n3"));
check('a bad or blank pattern is skipped, the other rules run', $home->render() === '<p>1 B 3 D E</p>', show($home->render()));

//typography
check('language name for typograf_<name>', $lang !== '' && ($wire->languages || $lang === 'default'), $lang);
$m = scenario(array());
check('missingTypografs() lists only what is set and absent', is_array($m->missingTypografs()));
if(class_exists('EMT\\EMTypograph')) {
	$fixture[$home->id] = '<p>Сайт https://example.com/page тут,и всё</p>';
	scenario(array("typograf_$lang" => 'typografCyr', 'replacePriority' => ''));
	$off = $home->render();
	scenario(array("typograf_$lang" => 'typografCyr', 'replacePriority' => '', 'emtAutoLinks' => 1, 'emtAutospaceAfter' => 1));
	$on = $home->render();
	check('EMT Text.auto_links off by default, on when set', stripos($off, '<a') === false && stripos($on, '<a') !== false, show(array($off, $on)));
	check('EMT Space.autospace_after off by default, on when set', strpos($off, 'тут,и') !== false && strpos($on, 'тут, и') !== false, show(array($off, $on)));
} else {
	echo "skip EMT checks: atispro/emt-php8 not installed here\n";
}
if(class_exists('PHP_Typography\\PHP_Typography')) {
	$fixture[$home->id] = '<p>"quoted"</p>';
	scenario(array("typograf_$lang" => 'typografIntl', 'replacePriority' => ''));
	check('Intl typograf runs', $home->render() !== '<p>"quoted"</p>', show($home->render()));
	scenario(array("typograf_$lang" => 'cleanup', 'replacePriority' => ''));
	check('a typograf setting naming another method falls back to Intl', $home->render() !== '<p>"quoted"</p>', show($home->render()));
}
$fixture[$home->id] = '<p>"quoted" - text</p>';
foreach(array('0', '', null) as $none) {
	scenario(array("typograf_$lang" => $none, 'replacePriority' => ''));
	check('typograf ' . show($none) . ' (as the empty option saves it): no typography', $home->render() === '<p>"quoted" - text</p>', show($home->render()));
}

//a user without a language property, as on a site without LanguageSupport
$bare = new class extends OutputTransformer {
	public $api;
	public function wire($name = '', $value = null, $lock = false) { return $this->api; }
};
$bare->api = (object) array('user' => new WireData());
check('no user language: the typograf_default setting is used', $bare->languageName() === 'default', $bare->languageName());

check('stripMarkers() keeps other stages and text', OutputTransformer::stripMarkers('<no-cleanup a="1">x</no-cleanup> no-replace <no-replace>y</no-replace><no-cleanupx>', array('cleanup')) === 'x no-replace <no-replace>y</no-replace><no-cleanupx>');

//the real home page template with the stored settings, site render hooks still removed
$fixture = array();
scenario(array_merge(array('typograf_default' => ''), $modules->getConfig('OutputTransformer')));
try {
	$t = microtime(true);
	$real = $home->render();
	$ms = round((microtime(true) - $t) * 1000);
	check("real home page with the stored settings ($ms ms, " . round(strlen($real) / 1024) . ' KB)', strlen($real) > 0 && !preg_match('~</?no-(?:cleanup|typografy|replace)\b~i', $real), substr($real, 0, 200));
} catch(\Throwable $e) {
	echo 'skip real home page render: ' . $e->getMessage() . "\n";
}

restore_error_handler();
check('no PHP warnings or notices from the module', !count($phpErrors), implode('; ', array_unique($phpErrors)));

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
