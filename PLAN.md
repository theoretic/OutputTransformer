# OutputTransformer: audit, actualisation, Bridge

Written 2026-10-18 from a review of 0.4.2 (`43b0531`) and every site copy. Nothing here is
fixed yet; each item is a task.

## Status and copies

- Canonical: this repo, 0.4.2. Junctioned into atispro, era-remont, livingston, mantra-mama,
  mantra-papa, onmed, opus-design, republic-remont, testfly, voxhall-design.
- Plain copies, all exact snapshots of commits here; nothing to harvest except the v21 item below:
  - paraavis 0.4.2: identical, but the copy carries `.claude/worktrees/nice-wright-63a4ac/`
    inside the site webroot. Delete it there.
  - txm "21": byte-identical to the uncommitted work of the worktree branch
    `claude/nice-wright-63a4ac` (`574546d`), never merged.
  - 0.3.0 (`a5851f4`): elbrusfly, steelbak, odnaco, peterbak, sla.events.
  - "020" (`574546d`): ofsla, tandem-chegem. atisprod is an older 020 (one commented line).
- Housekeeping: merge what is useful from `claude/nice-wright-63a4ac` (below), then remove
  the worktree and the branch.
- The predecessor `_archive/OutputFilter` is still active on davinci (007), ofsla (008) and
  paraavis (003). Retiring it depends on the typography items below.

## Audit

Bugs:
- `OutputTransformer.module:181-183`: textareas are split on `"\n"`. Config saved from a
  browser arrives with CRLF, so every pattern and replacement but the last ends in `\r` and
  never matches (cleanup has already removed `\r` from the HTML). Split with
  `preg_split('/\R/', ...)`. StaticPages has the same issue; share the helper.
- `:186`: `$replaceBys[$i]` is an undefined index when there are more patterns than
  replacements, and `if(!$replaceBys[$i]) continue` makes an empty or `0` replacement
  impossible. Use `array_key_exists` and allow empty strings.
- `:26`, `:33`, `:37-39`: when a helper file is missing, the module is disabled or a stage's
  priority is empty, no hook is added, so `<no-cleanup>`, `<no-typografy>` and `<no-replace>`
  markers reach the browser. Inside `<head>` they break the document. v21 had
  `hookStripChunkTags` for this; port it using `OutputTransformerMasker::stripWrapper`.
- `:37-39`: no guard that the rendered page is the request's page (StaticPages has one at
  `StaticPages.module:247`). Every partial `$page->render()` is transformed, and typography
  can run twice on the same text.
- No content-type guard: a template that outputs JSON or XML is minified as HTML, and cleanup
  collapses whitespace inside JSON strings. Skip when the template's content type is not
  `html` or `headers_list()` has a non-HTML `Content-Type`.
- `:122`, `:144`: `wire('user')->language->name` reads a property of null on sites without
  LanguageSupport. `OutputTransformer.config.php:97` loops `wire('languages')`, null there.
- `:83`: `in_array()` on a config value that is not an array throws a TypeError in PHP 8.
- `:108`: a private `log()` shadows hookable `Wire::___log()`. Rename it (`logLine()`).

Typography:
- `:158` requires the namespaced `\EMT\EMTypograph` from Composer package `atispro/emt-php8`.
  ofsla lacks the package (its `composer.json` does not require it), so its Cyrillic typograf
  silently does nothing; the site's old non-namespaced `site/shared/classes/EMT.php` is never
  used. Log once (and warn on the config screen) when the configured typograf class is absent.
- `:160-170` does not switch off EMT's `Text.auto_links` and `Space.autospace_after`, which
  OutputFilter 008 switches off. After a switch EMT turns URLs into links and adds spaces after
  punctuation. Add both as options, default off as in OutputFilter.
- The PHP_Typography locale map has `en`, `fr`, `es` and falls back to `en-US` (`:138-144`).
  OutputFilter 007 (davinci) maps `ru` and falls back to `fr-FR`. Document per-language
  `typograf_<lang>` setup for davinci in the README.
- OutputFilter's built-in rules (` -- ` to `&mdash;`, ten `Б У` to `БУ` style pairs on ofsla)
  must move into `replaceWhats`/`replaceBys` (`mb_ereg` patterns) before it is retired.

Performance:
- Three `Page::render` hooks run on every render, nested ones included (see the guard above).
- One `mb_ereg_replace` per rule over the whole document. Fine for tens of rules; say so in
  the README.

Safety: no request input, no file writes except logs. Regex patterns are owner-entered config;
`hookAfterSaveConfig` already validates keep patterns.

## Actualisation

- Rename `OutputTransformer.module` to `OutputTransformer.module.php`. Deployed plain copies
  must delete the old file first, or PW sees a duplicate.
- `array()` style, `$this->wire()->x` instead of `wire('x')`, drop the commented debug echoes
  (`:35`, `:123`, `:128`).
- `.gitattributes` with `eol=lf` and `.editorconfig` (copy from SelectorTester). LF is only a
  habit here today.
- `CHANGELOG.md` starting at 0.4.2, `CLAUDE.md` with the commands.
- Keep `tests/run.php` (needs no PW) and add `tests/smoke.php` against a real install: markers
  stripped when disabled, CRLF config, JSON template untouched, nested render transformed once.
- Version stays a semver string. The module has no assets, so `?v=` does not matter here.

## Bridge

No admin JavaScript and no screens of its own; the config form uses core Inputfields. Native
by construction. Keep it so: list `admin` in the skipped templates explicitly instead of
relying on the default denied list.

## Verification

- `php tests/run.php -v` and the new `tests/smoke.php <webroot>` on atispro.
- A sandbox without LanguageSupport (peterbak): no warnings in `site/assets/logs/errors.txt`.
- Page source with the module disabled shows no `<no-*>` tags.
