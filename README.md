# OutputTransformer

Makes better output for ProcessWire CMS.

## How does it work

Typical ProcessWire HTML output contains symbols not needed to render a page in a browser: tabs, newlines etc. However removing them from template files is not reasonable because they make files readable.
OutputTransformer removes all that overhead, leaving only the bare minimum which is enough to render what is expected by the developer.
Another problem is typographics. All that mdashes, correct quotes inside quotes etc. which make the text more readable and pleasant for users. OutputTransformer can add them automagically to the final HTML output.
And, just for the polishing: sometimes it can be handy to just replace some symbols in the final output. OutputTransformer can handle this also.

The module hooks `Page::render` three times, one hook per transformation: cleanup, typografy and replace.

### What is transformed

Only the page being viewed, rendered as a whole, when the response is HTML:

- **Partial renders are left alone**: another page's `render()`, or the viewed page rendering a field or another file of itself (`$page->render('body')`, `$page->render('file.php')`). Their output, `<no-x>` markers included, ends up inside the viewed page, which is transformed once, as a whole. Up to 0.4.2 every render was transformed, so typography could run twice on the same text. Code that sends a partial render somewhere else (a mail, an `echo ...; exit;`) gets it untransformed.
- **Non-HTML responses are left alone**: a template whose content type is not HTML (`json`, `xml`, `txt` or a MIME type), or a template that sent a non-HTML `Content-Type` header itself.
- **The admin is never transformed**, whatever the "No ... page templates" settings say.

## Installation

- Download or git clone this repository
- Copy the OutputTransformer folder inside /my-processwire-project/site/modules directory
- Go to PW admin page
- Go to Modules admin page
- Hit Refresh button
- Locate the newly found OutputTransformer module in the Site tab
- Hit Install button
- Modify module settings at will

### Upgrading from 0.4.x or older

Settings stay as they are: no setting was renamed or removed, and new settings default to off. Since 0.5.0 the module file is `OutputTransformer.module.php` and `OutputTransformer.info.php` is gone. Replace the whole folder: a plain copy over the old one leaves `OutputTransformer.module` and `OutputTransformer.info.php` behind, and ProcessWire then sees a duplicate. Delete the old folder first. A site that links the folder (junction, symlink) needs nothing else; ProcessWire finds the new file without a refresh.

Coming from 0.3.0 or 020: the cleanup was rewritten in 0.4.0, see below. Pages come out slightly different (for example `content="…" />` becomes `content="…"/>`), never with changed attribute values.

## Dependencies

OutputTransformer uses 3rd-party typography code: mundschenk-at/php-typography for most languages and atispro/emt-php8 for Russian. Don't forget to install the preferred typograf:

``` composer require mundschenk-at/php-typography ``` or ```composer require atispro/emt-php8``` , respectively.

A typograf that is selected but not installed does nothing. That is logged to `outputtransformer` once a day, and the module's settings screen shows a warning.

## Settings

Don't hesitate to check and adjust OutputTransformer settings after the module is installed! There's not too much of them, but every byte is important here.

| Setting | Key | Default | |
|---|---|---|---|
| Enable this module | `isEnabled` | off | Off, only the `<no-x>` markers are removed |
| cleanup / typografy / replace priority | `cleanupPriority`, `typografyPriority`, `replacePriority` | -1, -10, -20 | Empty: that transformation does not run (its markers are still removed) |
| No … page templates | `noCleanupTemplates`, `noTypografyTemplates`, `noReplaceTemplates` | admin | Pages with these templates skip that transformation |
| Keep untouched by … | `keepCleanupPatterns`, `keepTypografyPatterns`, `keepReplacePatterns` | empty | See "Keeping things untouched" |
| Replace what / Replace by | `replaceWhats`, `replaceBys` | `\s&\s` → `&nbsp;&&nbsp;` | See "Replace rules" |
| Empty replacement deletes | `replaceEmptyDeletes` | off | Since 0.5.0 |
| *Language* typograf | `typograf_<language name>` | none | Intl or Cyrillic, per language; `typograf_default` on a site without LanguageSupport |
| Cyrillic typograf: make links of URLs | `emtAutoLinks` | off | Since 0.5.0, EMT option `Text.auto_links` |
| Cyrillic typograf: spaces after punctuation | `emtAutospaceAfter` | off | Since 0.5.0, EMT option `Space.autospace_after` |

### Order of the transformations

Each transformation is a `Page::render` hook with the priority set in the settings (defaults: cleanup -1, typografy -10, replace -20). ProcessWire sorts hook priorities as strings in natural order, which ignores the minus sign: `-1` runs before `-10`, which runs before `-20`, and all negative priorities run before the default 100. So with the defaults the order is **cleanup, then typografy, then replace** — "-1 is higher priority than -2", as the priority notes say.

### What cleanup changes, and what it never does

Since 0.4.0 cleanup splits the page into comments, tags, text and raw-text elements, and treats each kind on its own:

- whitespace in text is collapsed; whitespace between two tags, and around the text of an element holding nothing but text, is removed — except spaces (no line break or tab) between two text-level tags such as `</strong> <code>`, which are a word space and stay as one; likewise a space trimmed from the edge of a text-level element that sits flush against running text moves outside the tag (`слово<b> жирное</b>` → `слово <b>жирное</b>`);
- tags are rebuilt with one space between attributes, and quotes are dropped where HTML allows it;
- comments are removed, except conditional comments and `<!--noindex-->`.

It never changes an attribute value — only `class`, `rel`, `srcset` and `sizes` are trimmed and collapsed, and `style` has its whitespace collapsed outside CSS strings, none left around `;` and each declaration's colon, and loses its trailing `;` — and never touches the content of `script`, `pre`, `textarea` or CDATA. `style` content only has its whitespace collapsed. `xmp` and `plaintext` are not supported.

Up to 0.3.0 cleanup applied its rules to the whole page as plain text, so it stripped the leading space from any attribute value (an admin form round-tripped `value=" | "` as `"| "`), turned `x < y` into a tag opener and joined script lines into their `//` comments.

### Keeping things untouched

- Wrap markup in `<no-cleanup>`, `<no-typografy>` or `<no-replace>` to keep that transformation out of it. The wrapper tags are removed from the output — also when that transformation does not run (module disabled, priority empty, template excluded, a helper file missing), because an unknown element like `<no-cleanup>` inside `<head>` closes the head and moves everything after it into the body. Wrappers of one kind may be nested (a partial with its own `<no-cleanup>` included inside another); an unclosed wrapper protects nothing and is removed.
- typografy and replace never touch `script`, `style` or `textarea` elements.
- **Keep untouched by …**: one PCRE pattern per line, with delimiters, per transformation. Whatever a pattern matches is left exactly as it is. A pattern that does not compile is skipped and logged, and reported when the settings are saved. Example: `~<input\b[^>]*name="?title_separator[^>]*>~i`.

Protected parts are swapped for private-use placeholders (U+F8E0–U+F8F1) while the transformation runs. If anything goes wrong — a regex error, an exception, a placeholder that does not come back exactly once, or a page that already contains those characters — the transformation is skipped for that page, which goes out as it came in, and the reason is logged to `outputtransformer`.

### Replace rules

Line N of **Replace what** (an `mb_ereg_replace()` pattern) is replaced by line N of **Replace by**. Any line break works, CRLF included.

- A blank pattern line, or a pattern with no replacement line, is skipped.
- An empty replacement line switches its rule off, as it always did. With **Empty replacement deletes** checked it deletes what the pattern matches instead.
- `0` is a replacement like any other (up to 0.4.2 it switched the rule off).
- A pattern that does not compile is skipped and logged; the other rules still run. Saving the settings warns about it, and about patterns without a replacement line.
- Neither field is trimmed on save (since 0.5.0), so a leading or trailing space belongs to its rule.

Each rule is one pass over the whole page. That is fine for tens of rules; for hundreds, measure.

To retire OutputFilter 008 (ofsla), put its built-in rules here. They ran after its typograf, as replace does by default:

| Replace what | Replace by |
|---|---|
| `\s--\s` | a space, `&mdash;`, a space |
| `Б У` | `БУ` |
| `В С` | `ВС` |
| `К Р` | `КР` |
| `Л А` | `ЛА` |
| `М Р` | `МР` |
| `М С` | `МС` |
| `О Ф` | `ОФ` |
| `П Р` | `ПР` |
| `Ф М` | `ФМ` |
| `Ч Р` | `ЧР` |

### Typography per language

Each language has its own setting, `typograf_<language name>`: none, **Intl** (php-typography) or **Cyrillic** (EMT). On a site without LanguageSupport there is one, `typograf_default`, the name of the default language, so the setting survives adding LanguageSupport later.

**Intl** hyphenates by the language name: `en` en-US, `fr` fr, `es` es, `de` de, `ru` ru, anything else en-US. Up to 0.4.2 French and Spanish were mapped to `fr-FR` and `es-ES`, which php-typography does not have, so they got no hyphenation.

**Cyrillic** runs EMT with paragraphs, line breaks, e-mail links, optical alignment, bracket spacing and number triads off. Two more EMT rules are settings since 0.5.0, both off by default as in OutputFilter: **make links of URLs** (`Text.auto_links`) and **spaces after punctuation** (`Space.autospace_after`). Up to 0.4.2 EMT ran both, so a site using the Cyrillic typograf sees plain-text URLs stay text and no spaces added after punctuation; check both boxes to get the old output back.

To move davinci from OutputFilter 007, which ran php-typography for every language: select **Intl** for each of its languages (`typograf_default`, `typograf_en`, `typograf_fr`, …), install `mundschenk-at/php-typography`, and add 007's ` -- ` rule to the replace settings (see above). 007 fell back to `fr` for languages it did not know; OutputTransformer falls back to `en-US`.

## Tests

- `php tests/run.php` runs the regression cases with plain PHP, no ProcessWire needed; `-v` also shows what 0.3.0 made of each input (`tests/legacy030.php`).
- `php tests/smoke.php /path/to/webroot` runs the module against a real install whose `site/modules/OutputTransformer` is this code: a Bridge sandbox or another disposable install, never a live site. It renders fixtures through the module's real hooks and checks marker removal, partial renders, content types, replace rules, typografs, settings defaults and old settings.

## Warning

However tested and used on several websites, hosting platforms and environments, this module should be considered as beta release. Use it at your own risk.
