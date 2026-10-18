# OutputTransformer

ProcessWire 3 autoload module: post-processes the HTML of the viewed page in three
`Page::render` after-hooks, cleanup (minify), typografy (typographer per language) and replace
(`mb_ereg` rules). No admin screens, no JS or CSS; the settings form is core Inputfields from
`OutputTransformer.config.php`. Remote: `github.com/theoretic/OutputTransformer`.

## Layout

| File | Role |
|---|---|
| `OutputTransformer.module.php` | Module: hooks, guards (`isPageView()`, `isHtmlResponse()`), `stripMarkers()`, typografs, replace |
| `OutputTransformer.config.php` | Settings as a `$config` array. Core includes it from its own classes on every module load (defaults) and for the form: `$this` is a core Wire, not the module. Keep it cheap |
| `OutputTransformerMasker.php` | Masking with private-use placeholders, failure policy. No PW dependency |
| `OutputTransformerCleanup.php` | Segmenting minifier. No PW dependency |
| `tests/run.php` | Regression cases for masker + cleanup, plain PHP; `tests/legacy030.php` is 0.3.0's cleanup, frozen |
| `tests/smoke.php` | Against a real install: fixtures rendered through the module's real hooks |

## Commands

```bash
D:/work/sites/modules/PHP-8.5/php -l OutputTransformer.module.php
D:/work/sites/modules/PHP-8.5/php tests/run.php -v
D:/work/sites/modules/PHP-8.5/php tests/smoke.php D:/work/projects/processwire/bridge/sandbox/atispro
```

`smoke.php` needs the install to load this code: in a Bridge sandbox, move
`app/modules/OutputTransformer` (a plain copy) aside, copy this repo's files in
(`git ls-files | xargs cp --parents -t <dir>`), run, and put the original back. Never against a live
site: it writes the module log. PW core: `D:/install/processwire/wire` (3.0.274). Read it instead of
guessing signatures.

## Conventions

- `namespace ProcessWire;`, tabs, LF, `array()`, `$this->wire()->x`, `$this->_()`.
- Version: semver string in `getModuleInfo()`, same in `CHANGELOG.md`. No assets, so no `?v=`.
- Each new behaviour gets a `check()` in `tests/smoke.php`, or a case in `tests/run.php` when it
  needs no ProcessWire.

## Compatibility (deployed on ~20 sites)

- Never rename or drop a settings key, never change what a stored value means. Keys:
  `isEnabled`, `{cleanup,typografy,replace}Priority` (`''` = stage off, `0`/missing = -1),
  `no{Cleanup,Typografy,Replace}Templates` (template IDs), `keep{...}Patterns`, `replaceWhats`,
  `replaceBys`, `typograf_<language name>` (`''` or `"0"` = none; `default` without
  LanguageSupport), `emtAutoLinks`, `emtAutospaceAfter`, `replaceEmptyDeletes`. New keys default off.
- Hook priorities are the sites' contract: `seoneo.inject.php` (kit sites) runs at 500 after the
  three stages, StaticPages caches at 1000. Keep three separate hooks at the configured priorities.
- Site templates rely on `<no-cleanup>`, `<no-typografy>`, `<no-replace>` and on the cleanup rules
  described in README.md. Compare rendered pages before and after a change (render a sandbox's pages
  with the stored settings, mask CSRF tokens, diff).
