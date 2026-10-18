<?php namespace ProcessWire;

/**
 * OutputTransformer
 *
 * Post-processes the HTML of rendered pages in three Page::render after-hooks:
 * cleanup (minify), typografy (typographer per language), replace (mb_ereg rules).
 *
 * @author AT / atis.pro
 * @author Claude
 *
 * 13.08.26, 11.09.26
 */

class OutputTransformer extends WireData implements Module, ConfigurableModule {

	public static function getModuleInfo() {
		return array(
			'title' => 'OutputTransformer',
			'version' => '0.4.2',
			'summary' => 'Strips extra symbols from output, performs typographics, replacements etc.',
			'author' => 'AT / atis.pro',
			'href' => 'http://atis.pro',
			'singular' => true,
			'autoload' => true,
			'icon' => 'file-o',
		);
	}

	const LOG = 'outputtransformer';

	//elements typografy and replace have no business in: code, css, form values
	const OFF_LIMITS = array('script', 'style', 'textarea');

	//loaded in init(), not at the top of the file: a deploy that uploads this file
	//before its helpers must leave pages untransformed for a moment, not fatal
	const HELPERS = array('OutputTransformerMasker.php', 'OutputTransformerCleanup.php');

	const STAGES = array('cleanup', 'typografy', 'replace');

	//never transformed, whatever the "No ... page templates" settings say
	const SKIP_TEMPLATES = array('admin');

	/**
	 * Stages whose <no-x> markers hookStripMarkers() removes: the ones that do not run
	 *
	 * @var array
	 *
	 */
	protected $strippedStages = array();

	public function init() {
		foreach(self::HELPERS as $helper) {
			if(!is_file(__DIR__ . "/$helper")) {
				$this->logLine("$helper is missing -- no transformations until it is there");
				$this->addStripHook(self::STAGES);
				return;
			}
			require_once(__DIR__ . "/$helper");
		}

		$this->addHookAfter('Modules::saveConfig', $this, 'hookAfterSaveConfig');

		if(!$this->isEnabled || $this->isEnabled == '') {
			$this->addStripHook(self::STAGES);
			return;
		}

		$off = array();
		foreach(self::STAGES as $stage) {
			$priority = $this->get("{$stage}Priority");
			if($priority === '') {
				$off[] = $stage;
				continue;
			}
			$this->addHookAfter('Page::render', $this, 'hook' . ucfirst($stage), array('priority' => $priority ?: -1));
		}
		$this->addStripHook($off);
	}

	/**
	 * A stage that does not run still owes the page the removal of its <no-x> markers: an
	 * unknown element like <no-cleanup> inside <head> closes the head, and everything after
	 * it is reparented into the body.
	 *
	 */
	protected function addStripHook(array $stages) {
		if(!count($stages)) return;
		$this->strippedStages = $stages;
		$this->addHookAfter('Page::render', $this, 'hookStripMarkers', array('priority' => -1));
	}

	/**
	 * Removes the <no-x> marker tags of the given stages, nothing else
	 *
	 * Needs no helper class, so it also works when the helpers are missing.
	 *
	 * @param string $html
	 * @param array $stages
	 * @return string
	 *
	 */
	public static function stripMarkers($html, array $stages) {
		if(!is_string($html) || $html === '' || !count($stages) || stripos($html, 'no-') === false) return $html;
		$names = implode('|', array_map(function($stage) { return preg_quote($stage, '~'); }, $stages));
		$out = preg_replace('~</?no-(?:' . $names . ')\b[^>]*>~i', '', $html);
		return is_string($out) ? $out : $html;
	}

	public function hookStripMarkers(HookEvent $event) {
		if(!$this->appliesTo($event)) return;
		$event->return = self::stripMarkers($event->return, $this->strippedStages);
	}

	/**
	 * Whether a Page::render call is one this module may touch at all
	 *
	 * Only the page being viewed, rendered as a whole, as HTML, and never the admin. Partial
	 * renders are left alone, markers included: they end up inside the page that is viewed,
	 * which is transformed once, as a whole.
	 *
	 */
	protected function appliesTo(HookEvent $event) {
		$page = $event->object;
		if(!$page instanceof Page || !$page->template) return false;
		if(in_array($page->template->name, self::SKIP_TEMPLATES, true)) return false;
		if(!$this->isPageView($page, $event->arguments())) return false;
		return $this->isHtmlResponse($page);
	}

	/**
	 * Whether this render is the page being viewed, not a partial render
	 *
	 * Partial: another page's render() (as StaticPages::cachePage() checks), or the viewed page
	 * rendering a field or another file of itself ($page->render('body'), render('file.php')).
	 *
	 * @param Page $page
	 * @param array $arguments Arguments of the render() call
	 * @return bool
	 *
	 */
	public function isPageView(Page $page, array $arguments = array()) {
		if($page !== $this->wire()->page) return false;
		$first = $arguments[0] ?? null;
		if(is_string($first) && $first !== '') return false;
		if(is_array($first) && !empty($first['filename'])) return false;
		return true;
	}

	/**
	 * Whether the response is HTML: by the template's content type, then by a Content-Type
	 * header the template may have sent itself
	 *
	 * @param Page $page
	 * @param array|null $headers Headers to check, headers_list() when omitted
	 * @return bool
	 *
	 */
	public function isHtmlResponse(Page $page, ?array $headers = null) {
		$type = (string) $page->template->contentType;
		if($type !== '') {
			if(strpos($type, '/') === false) {
				$types = $this->wire()->config->contentTypes;
				$type = is_array($types) && isset($types[$type]) ? $types[$type] : $type;
			}
			if(!self::isHtmlType($type)) return false;
		}
		if($headers === null) $headers = headers_list();
		foreach(array_reverse($headers) as $header) {
			if(stripos($header, 'content-type:') !== 0) continue;
			return self::isHtmlType(substr($header, 13));
		}
		return true;
	}

	protected static function isHtmlType($type) {
		list($mime) = explode(';', strtolower($type), 2);
		return in_array(trim($mime), array('', 'html', 'text/html', 'application/xhtml+xml'), true);
	}

	public function hookAfterSaveConfig(HookEvent $event) {
		//settings of this module only: report keep patterns that do not compile
		$module = $event->arguments(0);
		$class = is_object($module) ? $module->className() : wireClassName((string) $module);
		if($class !== $this->className()) return;

		$data = $this->wire()->modules->getConfig($this);
		foreach(self::STAGES as $transformation) {
			$field = 'keep' . ucfirst($transformation) . 'Patterns';
			OutputTransformerMasker::userRules($data[$field] ?? '', function($message) use ($transformation) {
				$this->warning("OutputTransformer, $transformation: $message -- it will be skipped");
			});
		}
	}

	public function hookTypografy(HookEvent $event) {
		if(!$this->appliesTo($event)) return;
		$event->return = $this->transform($event->object, $event->return, 'typografy');
	}

	public function hookReplace(HookEvent $event) {
		if(!$this->appliesTo($event)) return;
		$event->return = $this->transform($event->object, $event->return, 'replace');
	}

	public function hookCleanup(HookEvent $event) {
		if(!$this->appliesTo($event)) return;
		$event->return = $this->transform($event->object, $event->return, 'cleanup');
	}

	private function transform($page, $html, $transformation) {
		//no-transform template pages still lose this stage's <no-x> wrapper tags
		if(!$this->canTransformBy($page, $transformation)) return self::stripMarkers($html, array($transformation));
		return $this->apply($html, $transformation);
	}

	private function canTransformBy($page, $transformation) {
		//checking whether page template is not in no$hookNameTemplates
		$deniedTemplatesConfigParam = 'no' . ucfirst($transformation) . 'Templates';
		if($this->$deniedTemplatesConfigParam !== null)
			if(in_array($page->template->id, $this->$deniedTemplatesConfigParam)) return false;
		return true;
	}

	private function apply($html, $transformationName) {
		//masks what the transformation must not touch, runs it, puts the masked parts back;
		//on any failure the page goes out untransformed rather than broken or blank

		$log = function($message) { $this->logLine($message); };

		//<no-x> wrappers first: their content is kept, the wrapper tags dropped
		$rules = array(OutputTransformerMasker::wrapperRule($transformationName));

		//cleanup knows script, pre, textarea and style itself; the other two do not
		if($transformationName !== 'cleanup') $rules[] = OutputTransformerMasker::elementRule(self::OFF_LIMITS);

		//"Keep these untouched" from the settings
		$keepPatterns = $this->{'keep' . ucfirst($transformationName) . 'Patterns'};
		$rules = array_merge($rules, OutputTransformerMasker::userRules($keepPatterns, $log));

		return OutputTransformerMasker::run($html, $transformationName, function($masked) use ($transformationName) {
			return $this->$transformationName($masked);
		}, $rules, $log);
	}

	/**
	 * Saves a line to the module log, once per message per request: a broken pattern must not
	 * write a line per render. Not named log(): that would shadow the hookable Wire::log().
	 *
	 */
	private function logLine($message) {
		static $logged = array();
		if(isset($logged[$message])) return;
		$logged[$message] = true;
		$this->wire()->log->save(self::LOG, $message);
	}

	private function cleanup($html) {
		//see OutputTransformerCleanup.php; null on a PCRE error, which apply() treats as failure
		return OutputTransformerCleanup::run($html);
	}

	private function typografy($html) {
		$lang = $this->wire()->user->language->name;
		if(!$this->{"typograf_$lang"}) return $html;

		$typografMethodName = $this->{"typograf_$lang"};
		$typografMethodName = method_exists($this, $typografMethodName) ? $typografMethodName : 'typografIntl';
		$html = $this->$typografMethodName($html);

		return $html;
	}

	private function typografIntl($html) {
		//uses mundschenk-at typograf
		if(!class_exists('\\PHP_Typography\\PHP_Typography')) return $html;

		$locales = array(
			'en' => 'en-US',
			'fr' => 'fr-FR',
			'es' => 'es-ES',
		);

		$locale = $locales[$this->wire()->user->language->name] ?? 'en-US';

		$settings = new \PHP_Typography\Settings();
		$settings->set_hyphenation(true);
		$settings->set_hyphenation_language($locale);

		$typo = new \PHP_Typography\PHP_Typography();
		$html = $typo->process($html, $settings);

		return $html;
	}

	private function typografCyr($html) {
		//uses EMT-php8 typograf from https://mdash.ru/, https://atis.pro/ version
		if(!class_exists('\\EMT\\EMTypograph')) return $html;

		$options = array(
			'Etc.split_number_to_triads' => 'off',
			'OptAlign.all' => 'off',
			'Space.bracket_fix' => 'off',
			'Text.breakline' => 'off',
			'Text.email' => 'off',
			'Text.paragraphs' => 'off',
		);

		$html = \EMT\EMTypograph::fast_apply($html, $options);

		return $html;
	}

	private function replace($html) {
		if(!$this->replaceWhats || $this->replaceWhats == '') return $html;

		$replaceWhats = explode("\n", $this->replaceWhats);
		$replaceBys = explode("\n", $this->replaceBys);

		foreach($replaceWhats as $i => $replaceWhat) {
			if(!$replaceBys[$i]) continue;

			$html = mb_ereg_replace($replaceWhat, $replaceBys[$i], $html); //can be memory and time heavy!
			if(!is_string($html)) return null;
		}
		return $html;
	}

}
