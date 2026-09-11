
# OutputTransformer
Makes better output for Processwire CMS.

## How does it work

Typical Processwire's HTML output contains symbols not needed to render a page in a browser: tabs, newlines etc. However removing them from template files is not reasonable because they make files readable.
OutputTransformer removes all that overhead symbols, leaving only the bare minimum which is enough to render what is expected by developer.
Another problem is typographics. All that mdashes, correct quotes inside quotes etc. which make the text more readable and pleasant for users. OutputTransformer can add them automagically to the final html output.
And, just for the polishing: sometimes it can be handy to just replace some symbols in the final output. OutputTransformer can handle this also.

Module reacts to the following events:

- Page render: all the output is processed and transformed.

## Installation

- Download or git clone this repository
- Copy the OutputTransformer folder inside /my-processwire-project/site/modules directory
- Go to PW admin page
- Go to Modules admin page
- Hit Refresh button
- Locate the newly found OutputTransformer module in the Site tab
- Hit Install button
- Modify module settings at will

## Dependencies

OutputTransformer uses 3rd-party typography code: mundschenk-at/php-typography for most languages and atispro/emt-php8 for russian. Don't forget to install the preferred typograf:

``` composer require mundschenk-at/php-typography ``` or ```composer require atispro/emt-php8``` , respectively.

## Settings

Don't hesitate to check and adjust OutputTransformer settings after the module is installed! There's not too much of them, but every byte is important here.

### Order of the transformations

Each transformation is a `Page::render` hook with the priority set in the settings (defaults: cleanup -1, typografy -10, replace -20). ProcessWire sorts hook priorities as strings in natural order, which ignores the minus sign: `-1` runs before `-10`, which runs before `-20`, and all negative priorities run before the default 100. So with the defaults the order is **cleanup, then typografy, then replace** — "-1 is higher priority than -2", as the priority notes say.

### What cleanup changes, and what it never does

Since 0.4.0 cleanup splits the page into comments, tags, text and raw-text elements, and treats each kind on its own:

- whitespace in text is collapsed; whitespace between two tags, and around the text of an element holding nothing but text, is removed;
- tags are rebuilt with one space between attributes, and quotes are dropped where HTML allows it;
- comments are removed, except conditional comments and `<!--noindex-->`.

It never changes an attribute value — only `class`, `rel`, `srcset` and `sizes` are trimmed and collapsed, and `style` loses its trailing `;` — and never touches the content of `script`, `pre`, `textarea` or CDATA. `style` content only has its whitespace collapsed. `xmp` and `plaintext` are not supported.

Up to 0.3.0 cleanup applied its rules to the whole page as plain text, so it stripped the leading space from any attribute value (an admin form round-tripped `value=" | "` as `"| "`), turned `x < y` into a tag opener and joined script lines into their `//` comments.

### Keeping things untouched

- Wrap markup in `<no-cleanup>`, `<no-typografy>` or `<no-replace>` to keep that transformation out of it. The wrapper tags are removed from the output.
- typografy and replace never touch `script`, `style` or `textarea` elements.
- **Keep untouched by …**: one PCRE pattern per line, with delimiters, per transformation. Whatever a pattern matches is left exactly as it is. A pattern that does not compile is skipped and logged, and reported when the settings are saved. Example: `~<input\b[^>]*name="?title_separator[^>]*>~i`.

Protected parts are swapped for private-use placeholders (U+F8E0–U+F8F1) while the transformation runs. If anything goes wrong — a regex error, an exception, a placeholder that does not come back exactly once, or a page that already contains those characters — the transformation is skipped for that page, which goes out as it came in, and the reason is logged to `outputtransformer`.

## Tests

`php tests/run.php` runs the regression cases with plain PHP, no ProcessWire needed; `-v` also shows what 0.3.0 made of each input (`tests/legacy030.php`).

## Warning

However tested and used on several websites, hosting platforms and environments, this mode should be considered as beta release. Use it at Your own risk.