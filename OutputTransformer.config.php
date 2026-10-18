<?php namespace ProcessWire;

/**
 * OutputTransformer module config
 *
 * Core includes this file from its own classes ($this is a core Wire object, not the module)
 * every time the module is loaded, to read the defaults ('value'), and again for the settings
 * screen. Keep it cheap and free of side effects.
 *
 * @author AT
 *
 * 24.12.25
 */

$noOutputTransformTemplatesOptions = array();
$defaultNoTransformTemplateValues = array();
foreach($this->wire()->templates as $template) {
	$noOutputTransformTemplatesOptions[$template->id] = $template->name;
	if($template->name == 'admin') $defaultNoTransformTemplateValues[] = $template->id;
}

$transformations = array(
	'cleanup' => array('priority' => -1),
	'typografy' => array('priority' => -10),
	'replace' => array('priority' => -20),
);

$typografyOptions = array(
	'',
	'typografIntl' => 'Intl',
	'typografCyr' => 'Cyrillic',
);

$config = array();

//priorities
foreach($transformations as $transformation => $params) {
	$config[] = array(
		'name' => "{$transformation}Priority",
		'type' => 'integer',
		'label' => sprintf($this->_('%s priority'), $transformation),
		'columnWidth' => 33,
		'notes' => $this->_('Use negative numbers. -1 is higher priority than -2. If no value, this transformation will not be applied.'),
		'value' => $params['priority'],
	);
}

//no-transform templates
foreach($transformations as $transformation => $params) {
	$config[] = array(
		'name' => 'no' . ucfirst($transformation) . 'Templates',
		'type' => 'AsmSelect',
		'label' => sprintf($this->_('No %s page templates'), $transformation),
		'notes' => sprintf($this->_('Output of pages having these templates will not be affected by %s.'), $transformation),
		'columnWidth' => 33,
		'options' => $noOutputTransformTemplatesOptions,
		'value' => $defaultNoTransformTemplateValues,
	);
}

//keep patterns: the escape hatch for what no built-in rule foresaw
foreach($transformations as $transformation => $params) {
	$config[] = array(
		'name' => 'keep' . ucfirst($transformation) . 'Patterns',
		'type' => 'textarea',
		'label' => sprintf($this->_('Keep untouched by %s'), $transformation),
		'description' => $this->_('One PCRE pattern per line, with delimiters. Whatever it matches is left exactly as it is.'),
		'notes' => sprintf($this->_('Example: %s. A pattern that does not compile is skipped and logged. Built in already: <no-%s> wrappers%s.'), '~<input\b[^>]*name="?title_separator[^>]*>~i', $transformation, $transformation === 'cleanup' ? $this->_(', script, style, pre and textarea content, attribute values') : $this->_(', script, style and textarea elements')),
		'columnWidth' => 33,
		'rows' => 3,
	);
}

//replacements
$config[] = array(
	'name' => 'replaceWhats',
	'type' => 'textarea',
	'label' => $this->_('Replace what'),
	'notes' => $this->_('One mb_ereg_replace sample per string.'),
	'columnWidth' => 20,
	'value' => '\s&\s',
);

$config[] = array(
	'name' => 'replaceBys',
	'type' => 'textarea',
	'label' => $this->_('Replace by'),
	'notes' => $this->_('One mb_ereg_replace sample per string.'),
	'columnWidth' => 20,
	'value' => '&nbsp;&&nbsp;',
);

foreach($this->wire()->languages as $language) {
	$config[] = array(
		'name' => "typograf_{$language->name}",
		'label' => sprintf($this->_('%s typograf'), $language->title),
		'type' => 'select',
		'columnWidth' => 20,
		'options' => $typografyOptions,
		'description' => $this->_('If nothing selected, no typography will be applied for this language'),
		'notes' => "Don't forget to install typography classes!\nIntl: composer require mundschenk-at/php-typography\nCyrillic: composer require atispro/emt-php8",
	);
}

$config[] = array(
	'name' => 'isEnabled',
	'type' => 'checkbox',
	'label' => $this->_('Enable this module'),
	'columnWidth' => 20,
);
