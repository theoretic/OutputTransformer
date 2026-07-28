
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

## Warning

However tested and used on several websites, hosting platforms and environments, this mode should be considered as beta release. Use it at Your own risk.