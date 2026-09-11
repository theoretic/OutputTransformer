<?php
/*
Output Transformer module config
https://github.com/ryancramerdesign/Helloworld
AT
24.12.25
*/

namespace ProcessWire;

foreach( wire('templates') as $template ){
	$noOutputTransformTemplatesOptions[$template->id] = $template->name;
	if( $template->name == 'admin' ) $defaultNoTransformTemplateValues[] = $template->id;
}
//echo '$noOutputTransformTemplatesOptions: ', var_dump($noOutputTransformTemplatesOptions);//

$transformations = [
	'cleanup'			=> [ 'priority'=>-1, ],
	'typografy'			=> [ 'priority'=>-10, ],
	'replace'			=> [ 'priority'=>-20, ],
];

$typografyOptions = [
	'',
	'typografIntl'		=> 'Intl',
	'typografCyr'		=> 'Cyrillic',
];

//priorities
foreach( $transformations as $transformation=>$params ){
	$config[] = 
	[
	'name'					=> "{$transformation}Priority",
	'type'					=> 'integer',
	'label'					=> sprintf( $this->_("%s priority"), $transformation ),
	'columnWidth'			=> 33,
	'notes'					=> $this->_("Use negative numbers. -1 is higher priority than -2. If no value, this transformation will not be applied."),
	'value'					=> $params['priority'],
	];
}

//no-transform templates
foreach( $transformations as $transformation=>$params ){
	$config[] =
	[
	'name'					=> 'no'.ucfirst($transformation).'Templates',
	'type'					=> 'AsmSelect',
	'label'					=> sprintf( $this->_('No %s page templates'), $transformation ),
	//'description'			=> $this->_('one name per line'),
	'notes'					=> sprintf( $this->_('Output of pages having these templates will not be affected by %s.'), $transformation ),
	//'required'				=> true,
	'columnWidth'			=> 33,
	'options'				=> $noOutputTransformTemplatesOptions,
	'value'					=> $defaultNoTransformTemplateValues,
	];
}

//keep patterns: the escape hatch for what no built-in rule foresaw
foreach( $transformations as $transformation=>$params ){
	$config[] =
	[
	'name'					=> 'keep'.ucfirst($transformation).'Patterns',
	'type'					=> 'textarea',
	'label'					=> sprintf( $this->_('Keep untouched by %s'), $transformation ),
	'description'			=> $this->_('One PCRE pattern per line, with delimiters. Whatever it matches is left exactly as it is.'),
	'notes'					=> sprintf( $this->_('Example: %s. A pattern that does not compile is skipped and logged. Built in already: <no-%s> wrappers%s.'), '~<input\b[^>]*name="?title_separator[^>]*>~i', $transformation, $transformation === 'cleanup' ? $this->_(', script, style, pre and textarea content, attribute values') : $this->_(', script, style and textarea elements') ),
	'columnWidth'			=> 33,
	'rows'					=> 3,
	];
}

//replacements
$config[] =
[
'name'					=> 'replaceWhats',
'type'					=> 'textarea',
'label'					=> $this->_('Replace what'),
//'description'			=> $this->_('UNIX string like 0755'),
'notes'					=> $this->_("One mb_ereg_replace sample per string."),
//'required'				=> true,
'columnWidth'			=> 20,
'value'					=> '\s&\s',
];

$config[] = 
[
'name'					=> 'replaceBys',
'type'					=> 'textarea',
'label'					=> $this->_('Replace by'),
//'description'			=> $this->_('UNIX string like 0755'),
'notes'					=> $this->_("One mb_ereg_replace sample per string."),
//'required'				=> true,
'columnWidth'			=> 20,
'value'					=> '&nbsp;&&nbsp;',
];

foreach( wire('languages') as $language ){
	$config[] =
	[
	'name'					=> "typograf_{$language->name}",
	'label'					=> sprintf( $this->_("%s typograf"), $language->title ),
	'type'					=> 'select',
	'columnWidth'			=> 20,
	//'required'				=> true,
	'options'				=> $typografyOptions,
	'description'			=> $this->_('If nothing selected, no typography will be applied for this language'),
	'notes'					=> "Don't forget to install typography classes!\nIntl: composer require mundschenk-at/php-typography\nCyrillic: composer require atispro/emt-php8",
	];
}

$config[] = 
[
'name'					=> 'isEnabled',
'type'					=> 'checkbox',
'label'					=> $this->_('Enable this module'),
//'description'			=> $this->_('UNIX string like 0755'),
//'notes'					=> $this->_('Path relative to website root directory.'), 
//'required'				=> true,
'columnWidth'			=> 20,
//'value'					=> 1,
];