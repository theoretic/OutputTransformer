# OutputTransformer: audit, actualisation, Bridge

Written 2026-10-18 from a review of 0.4.2 (`43b0531`) and every site copy. Worked through the same
day in 0.5.0: each item below says **done** (with how) or **not done** (with why).

## Status and copies

- Canonical: this repo, 0.4.2. Junctioned into atispro, era-remont, livingston, mantra-mama,
  mantra-papa, onmed, opus-design, republic-remont, testfly, voxhall-design.
- Plain copies, all exact snapshots of commits here; nothing to harvest except the v21 item below:
  - paraavis 0.4.2: identical, but the copy carries `.claude/worktrees/nice-wright-63a4ac/`
    inside the site webroot. Delete it there. **Not done**: site files are out of scope for this
    repo (read-only from here); delete it when paraavis is switched to the junction.
  - txm "21": byte-identical to the uncommitted work of the worktree branch
    `claude/nice-wright-63a4ac` (`574546d`), never merged.
  - 0.3.0 (`a5851f4`): elbrusfly, steelbak, odnaco, peterbak, sla.events.
  - "020" (`574546d`): ofsla, tandem-chegem. atisprod is an older 020 (one commented line).
- Housekeeping: merge what is useful from `claude/nice-wright-63a4ac` (below), then remove
  the worktree and the branch. **Done**: marker stripping when a stage does not run ported
  (`hookStripMarkers`), its nesting support redone as a recursive wrapper rule, its test cases
  moved to `tests/run.php`. Dropped as superseded by 0.4.0: its findChunks/cleanup rewrite, its
  `tests/bootstrap.php` stubs, the octal-version note (semver since 0.3.0), and its README claim
  that the order is replace, typografy, cleanup (it is the reverse). Worktree and branch removed.
- The predecessor `_archive/OutputFilter` is still active on davinci (007), ofsla (008) and
  paraavis (003). Retiring it depends on the typography items below. **Not done here**: site
  configuration; the module side is ready and the README says what each site needs.

## Audit

Bugs:
- `OutputTransformer.module:181-183`: textareas are split on `"\n"`. Config saved from a
  browser arrives with CRLF, so every pattern and replacement but the last ends in `\r` and
  never matches (cleanup has already removed `\r` from the HTML). Split with
  `preg_split('/\R/', ...)`. StaticPages has the same issue; share the helper.
  **Done**: `OutputTransformer::lines()`. Current core turns CRLF into LF on save, but stored
  values may still carry `\r`. Not shared with StaticPages: separate repos, and StaticPages
  already splits with `\R` (`StaticPages.module:1439`).
- `:186`: `$replaceBys[$i]` is an undefined index when there are more patterns than
  replacements, and `if(!$replaceBys[$i]) continue` makes an empty or `0` replacement
  impossible. Use `array_key_exists` and allow empty strings. **Done**, with one compat
  adjustment: a missing replacement line is skipped quietly, `0` now works, but an empty line
  still switches its rule off (that is what stored settings mean); the new setting
  `replaceEmptyDeletes` makes it delete. Also: blank pattern lines skipped, a failing rule
  skipped and logged instead of failing the stage, patterns checked on save, textareas no
  longer trimmed.
- `:26`, `:33`, `:37-39`: when a helper file is missing, the module is disabled or a stage's
  priority is empty, no hook is added, so `<no-cleanup>`, `<no-typografy>` and `<no-replace>`
  markers reach the browser. Inside `<head>` they break the document. v21 had
  `hookStripChunkTags` for this; port it using `OutputTransformerMasker::stripWrapper`.
  **Done**: `hookStripMarkers` at priority -1 with `OutputTransformer::stripMarkers()`, which
  needs no helper class (the Masker is the missing helper in one of the three cases).
- `:37-39`: no guard that the rendered page is the request's page (StaticPages has one at
  `StaticPages.module:247`). Every partial `$page->render()` is transformed, and typography
  can run twice on the same text. **Done**: `isPageView()` (same identity check as StaticPages,
  plus the viewed page rendering a field or file of itself).
- No content-type guard: a template that outputs JSON or XML is minified as HTML, and cleanup
  collapses whitespace inside JSON strings. Skip when the template's content type is not
  `html` or `headers_list()` has a non-HTML `Content-Type`. **Done**: `isHtmlResponse()`.
- `:122`, `:144`: `wire('user')->language->name` reads a property of null on sites without
  LanguageSupport. `OutputTransformer.config.php:97` loops `wire('languages')`, null there.
  **Done**: `languageName()` gives `default`; the settings show one `typograf_default`.
- `:83`: `in_array()` on a config value that is not an array throws a TypeError in PHP 8.
  **Done**: a non-array value is read as a list of IDs.
- `:108`: a private `log()` shadows hookable `Wire::___log()`. Rename it (`logLine()`).
  **Done**.
- Found on the way: the typograf setting could name any module method (now only the typograf
  methods), and a stored `"0"` (the select's empty option, odnaco has it) must mean none.
  **Done**.

Typography:
- `:158` requires the namespaced `\EMT\EMTypograph` from Composer package `atispro/emt-php8`.
  ofsla lacks the package (its `composer.json` does not require it), so its Cyrillic typograf
  silently does nothing; the site's old non-namespaced `site/shared/classes/EMT.php` is never
  used. Log once (and warn on the config screen) when the configured typograf class is absent.
  **Done**: logged once a day (WireCache), warning on the settings screen
  (`missingTypografs()`). Installing the package on ofsla is site work, **not done**.
- `:160-170` does not switch off EMT's `Text.auto_links` and `Space.autospace_after`, which
  OutputFilter 008 switches off. After a switch EMT turns URLs into links and adds spaces after
  punctuation. Add both as options, default off as in OutputFilter. **Done**: `emtAutoLinks`,
  `emtAutospaceAfter`. This changes the output of sites already on the Cyrillic typograf.
- The PHP_Typography locale map has `en`, `fr`, `es` and falls back to `en-US` (`:138-144`).
  OutputFilter 007 (davinci) maps `ru` and falls back to `fr-FR`. Document per-language
  `typograf_<lang>` setup for davinci in the README. **Done**, and the map fixed: `fr-FR` and
  `es-ES` do not exist in php-typography (`fr.json`, `es.json`), so those languages got no
  hyphenation; now `en` en-US, `fr`, `es`, `de`, `ru`.
- OutputFilter's built-in rules (` -- ` to `&mdash;`, ten `Б У` to `БУ` style pairs on ofsla)
  must move into `replaceWhats`/`replaceBys` (`mb_ereg` patterns) before it is retired.
  **Documented** in the README as a ready table; entering them is site configuration, not done.

Performance:
- Three `Page::render` hooks run on every render, nested ones included (see the guard above).
  **Done** as far as wanted: partial renders now return after a few cheap checks. The three hooks
  stay separate on purpose: the kit's `seoneo.inject.php` relies on their priorities.
- One `mb_ereg_replace` per rule over the whole document. Fine for tens of rules; say so in
  the README. **Done**.

Safety: no request input, no file writes except logs. Regex patterns are owner-entered config;
`hookAfterSaveConfig` already validates keep patterns. Still true; it now validates replace
patterns too. One WireCache row a day when a typograf package is missing.

## Actualisation

- Rename `OutputTransformer.module` to `OutputTransformer.module.php`. Deployed plain copies
  must delete the old file first, or PW sees a duplicate. **Done**; the info moved into
  `getModuleInfo()` too. Tested in the atispro sandbox: a replaced folder is found without a
  refresh.
- `array()` style, `$this->wire()->x` instead of `wire('x')`, drop the commented debug echoes
  (`:35`, `:123`, `:128`). **Done**.
- `.gitattributes` with `eol=lf` and `.editorconfig` (copy from SelectorTester). LF is only a
  habit here today. **Done**.
- `CHANGELOG.md` starting at 0.4.2, `CLAUDE.md` with the commands. **Done** (the changelog goes
  back to 020).
- Keep `tests/run.php` (needs no PW) and add `tests/smoke.php` against a real install: markers
  stripped when disabled, CRLF config, JSON template untouched, nested render transformed once.
  **Done**, plus the other new behaviour. Not covered: the missing-helper case (needs a second
  copy of the class in one process); it shares `hookStripMarkers` with the disabled case.
- Version stays a semver string. The module has no assets, so `?v=` does not matter here.
  **Done**: 0.5.0.

## Bridge

No admin JavaScript and no screens of its own; the config form uses core Inputfields. Native
by construction. Keep it so: list `admin` in the skipped templates explicitly instead of
relying on the default denied list. **Done**: `SKIP_TEMPLATES`. (atispro's stored
`noCleanupTemplates` lacked admin, so its admin was being minified until now.)

## Verification

- `php tests/run.php -v` and the new `tests/smoke.php <webroot>` on atispro. **Done**, both pass.
- A sandbox without LanguageSupport (peterbak): no warnings in `site/assets/logs/errors.txt`.
  **Not done as written**: there is no peterbak sandbox, and no sandbox without LanguageSupport.
  `smoke.php` covers it instead: the settings file read with `languages` null, and a user without
  a language. odnaco (0.3.0 settings, one language) passes the smoke test.
- Page source with the module disabled shows no `<no-*>` tags. **Done** in `smoke.php`.
- Added: rendered pages compared before and after with the stored settings, all other render
  hooks removed, CSRF tokens masked: atispro (7 pages) and odnaco (8 pages) are byte-identical
  between 0.4.2 and 0.5.0.
