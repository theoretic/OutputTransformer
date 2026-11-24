# Changelog

## 0.5.1 (2026-11-24)

### Changed
- Cleanup minifies `style` attribute values: whitespace runs (line breaks and tabs of a style
  spread over template lines) become one space, none is left around `;` and each declaration's
  colon. CSS strings, also `&quot;`/`&#39;`/`&apos;`-quoted ones, stay verbatim. Up to 0.5.0 the
  value was only trimmed and lost its trailing `;`.
- A value ending in `/` goes unquoted like any other: `href="/x/" class=step` becomes
  `href=/x/ class=step`. In the tag's last attribute a space keeps it off the `>`
  (`<a href=/x/ >`), so the markup never shows a `/>` that is not self-closing; before the `/` of a
  self-closing tag it stays quoted. Up to 0.5.0 such values were always quoted.

### Tests
- `tests/smoke.php` covers a missing helper file: only the `<no-x>` markers are removed and the
  strip hook is the module's only render hook. `init()` reads `static::HELPERS` so a test
  subclass can name a missing file; no change for sites.

## 0.5.0 (2026-10-18)

Stored settings of every deployed version (020, 21, 0.3.0, 0.4.x) keep working: no key was
renamed or removed, the new keys default to off. Rendered output of atispro (0.4.2 settings) and
odnaco (0.3.0 settings) sandboxes is byte-identical to 0.4.2.

### Fixed
- `<no-cleanup>`, `<no-typografy>` and `<no-replace>` reached the browser when the module was
  disabled, a helper file was missing or a stage's priority was empty. Inside `<head>` that broke
  the document. A hook now removes the markers of every stage that does not run (ported from the
  unmerged v21 branch).
- Nested wrappers of one stage: the first inner closing tag ended the protection early.
- Every `Page::render` was transformed, partial renders included, so typography could run twice on
  the same text. Only the page being viewed is transformed now, once, as a whole.
- A template that outputs JSON, XML or text (template content type or `Content-Type` header) was
  minified as HTML.
- Replace rules: a value with CRLF left `\r` on every line but the last, so those rules never
  matched; a pattern without a replacement line read an undefined index; a blank pattern line
  failed the whole stage; `0` could not be a replacement; one bad pattern left the page
  unreplaced. Now split on any line break, short lists and blank lines are skipped, a failing rule
  is skipped and logged and the others run.
- Sites without LanguageSupport: `$user->language->name` read a property of null, and the settings
  file looped over a null `$languages` on every module load. Such a site gets one setting,
  `typograf_default`.
- `in_array()` on a "No ... page templates" value that is not an array threw a TypeError.
- The typograf setting could name any method of the module; only the typograf methods run now,
  anything else falls back to Intl as before. A stored `"0"` (the select's empty option) means none.
- The Intl locale map named `fr-FR` and `es-ES`, which php-typography does not have, so French and
  Spanish got no hyphenation. Now `en` en-US, `fr`, `es`, `de`, `ru`; anything else en-US.
- A private `log()` shadowed the hookable `Wire::log()`; renamed `logLine()`.

### Added
- Settings for the Cyrillic typograf (EMT): `emtAutoLinks` (`Text.auto_links`) and
  `emtAutospaceAfter` (`Space.autospace_after`), both off by default as in OutputFilter.
  **Output change** for sites using the Cyrillic typograf: until now EMT ran both rules.
- Setting `replaceEmptyDeletes` (off): an empty "Replace by" line deletes what its pattern matches.
  Off, an empty line switches its rule off, as before.
- A typograf whose Composer package is missing is logged once a day, and the settings screen warns.
- Saving the settings warns about replace patterns that do not compile or have no replacement line.
- The replace textareas are no longer trimmed on save: leading and trailing spaces stay in a rule.
- `tests/smoke.php` against a real install; more cases in `tests/run.php`.

### Changed
- The admin template is never transformed, whatever the "No ... page templates" settings say.
- `OutputTransformer.module` is now `OutputTransformer.module.php`, and the module info moved from
  `OutputTransformer.info.php` into `getModuleInfo()`. A plain-copy deploy must delete the old
  files first. A junctioned site needs no refresh: ProcessWire finds the new file.
- Kit conventions: `array()`, `$this->wire()->x`, `.gitattributes` and `.editorconfig` pin LF.

## 0.4.2 (2026-09-29)

- Cleanup: a word space trimmed from the edge of a text-level element that sits flush against
  running text moves outside the tag instead of gluing words.

## 0.4.1 (2026-09-29)

- Cleanup: spaces between two text-level tags (`</strong> <code>`) stay as one word space.

## 0.4.0 (2026-09-11)

- Cleanup rewritten as a segmenter (comments, tags, text, raw-text elements): attribute values are
  never changed beyond the documented normalisations, `x < y` stays text, script, pre and textarea
  content stays verbatim.
- Masking with private-use placeholders and a failure policy: a stage that fails leaves the page as
  it came in and logs why.
- "Keep untouched by ..." pattern settings per stage.
- Helpers are loaded in `init()`, and only when present.

## 0.3.0 (2026-09-11)

- Version as a semver string (was the octal literal `020`, which PHP reads as 16).

## 020 / 21 (2026-07)

- First versions on the sites. "21" (txm) is the uncommitted work of the branch
  `claude/nice-wright-63a4ac`, merged in 0.5.0 as far as still useful.
