# kavanotparsedown
 
 Extensions to [Parsedown](http://parsedown.org) that makes my life easier editing http://kavanot.name.

It does not incorporate [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/), since I think the only thing I want from that would be tables, and Parsedown already includes that. Definition lists might be useful, so I may extend from [Parsedown Extra](https://github.com/erusev/parsedown-extra) at some point.

## `<i>` elements
I use a lot of Hebrew, including transliterations. According to [MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/i):
>The HTML `<i>` element represents a range of text that is set off from the normal text for some reason. Some examples include technical terms, foreign language phrases, or fictional character thoughts. It is typically displayed in italic type.

So a transliterated term should be `<i lang=he>Shabbat</i>`. I use `/` for that: `/Shabbat/` becomes `<i lang=he>Shabbat</i>`. Note that the `lang=he` attribute is automatically included, but can be changed with attribute lists (see below)

URL's will still be parsed, but other uses of slashes should be escaped.

## Inline links

`<http://example.com>` in Markdown creates a link with the url as the text: `<a href="http://example.com">http://example.com</a>`. Many of my links are to other pages in the Kavanot site, and that is organized so the title of the page *is* the url, so I adopted `<//title>` for that. So `<//Glossary>` becomes `<a href="/Glossary">Glossary</a>`.

## `<cite>` elements

I use a lot of `<cite>` elements, and I figure there's no reason for *two* markers for `<em>` and `<strong>` elements, so I redefined `_`: `_A Tale of Two Cities_` becomes `<cite>A Tale of Two Cities</cite>`.

## `<figcaption>` in `<figure>` elements
Quotes should have their sources cited in the text. According to [WhatWG](https://html.spec.whatwg.org/multipage/grouping-content.html#the-blockquote-element:the-blockquote-element-4):

>Here a blockquote element is used in conjunction with a figure element and its figcaption to clearly relate a quote to its attribution (which is not part of the quote and therefore doesn't belong inside the blockquote itself):
``` html
<figure>
 <blockquote>
  <p>The truth may be puzzling. It may take some work to grapple with.
  It may be counterintuitive. It may contradict deeply held
  prejudices. It may not be consonant with what we desperately want to
  be true. But our preferences do not determine what's true. We have a
  method, and that method helps us to reach not absolute truth, only
  asymptotic approaches to the truth — never there, just closer
  and closer, always finding vast new oceans of undiscovered
  possibilities. Cleverly designed experiments are the key.</p>
 </blockquote>
 <figcaption>Carl Sagan, in "<cite>Wonder and Skepticism</cite>", from
 the <cite>Skeptical Inquirer</cite> Volume 19, Issue 1 (January-February
 1995)</figcaption>
</figure>
```
I use `--` at the beginning of the line to indicate this. So the above example would be:

```
>The truth may be puzzling. It may take some work to grapple with.
  It may be counterintuitive. It may contradict deeply held
  prejudices. It may not be consonant with what we desperately want to
  be true. But our preferences do not determine what's true. We have a
  method, and that method helps us to reach not absolute truth, only
  asymptotic approaches to the truth — never there, just closer
  and closer, always finding vast new oceans of undiscovered
  possibilities. Cleverly designed experiments are the key.
--Carl Sagan, in "_Wonder and Skepticism_", from
 the _Skeptical Inquirer_ Volume 19, Issue 1 (January-February
 1995)
```

Note that the CSS for the `<figcaption>` should match the `<blockquote>`; that won't happen automatically.

## Attribute lists
[Parsedown Extra](https://github.com/erusev/parsedown-extra) allows for adding attributes to selected elements. I wanted to add attributes to *any* element. I used the syntax of the [Python Markdown library](https://python-markdown.github.io/extensions/attr_list/), however, the attribute lists go *before* the elements. The rule about attributes for 
block elements being on a line by themselves remains.

Shortcuts include: `.foo` becomes `class="foo"`, `#bar` becomes `id="bar"`, and a two-letter attribute becomes a `lang` attribute (since I use that so much): `la` becomes `lang="la"`.

Attribute lists are enclosd in `{:` and `}`. Spaces before and after are ignored.
```
{: #details }
## Details
These are {: .big }*important* details. There is a certain {:fr}/Je ne sais qua/ about them
```
becomes
``` html
<h2 id="details">Details</h2>
<p>These are <em class="big">important</em> details. There is a certain <i lang="fr">Je ne sais qua</i> about them</p>
```
And
```
{:he}
>This is a quote
--This is the _source_
```
becomes
```html
<figure lang="he">
 <blockquote>
   <p>This is a quote</p>
 </blockquote>
 <figcaption class="source">This is the <cite>source</cite></figcaption>
</figure>
```
 Unlike the Python syntax, successive attributes with the same name will be ignored. `{: .foo .bar }` will only become `class="foo"`. Attribute names with illegal characters will be ignored.
 
## Markdown in HTML block elements
I borrowed this from [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/). Block-level raw HTML is generally not parsed, but if the attribute `markdown` or `md` is set, then the inner HTML will be parsed (with the same parser, so internal HTML is not parsed unless *it* has the `markdown` attribute set.

Note that this is more liberal than Markdown Extra, which requires `markdown=1` or `markdown=block`. This just looks for the presence of either attribute.

```
<div md>
This is *important*.

This is {:yi}/vikhtik/.
</div>
```
Becomes
```html
<div>
<p>This is <em>important</em>.</p>
<p>This is <i lang="yi">vikhtik</i>.</p>
</div>
```
 
 ## Smart Quotes
 Pairs of straight quotes will become curly: `"foo"` and `'foo'` will become `“foo”` and `‘foo’`. It tries to be smart enough to detect Hebrew text, so that the curly quotes go in the correct direction. Single quotes become an apostrophe. In Hebrew text, an isolated single quote becomes a <i lang=he><a href=https://en.wikipedia.org/wiki/Geresh>geresh</a></i> and an isolated double quote becomes a <i lang=he><a href=https://en.wikipedia.org/wiki/Gershayim>gershayim</a></i>.
 
 In addition, `...` becomes an ellipsis: `…` and `--` becomes an em-dash: `—`.
 
 The smart quotes try to be smart enough to span separate inline elements: ` "this is *important*." ` produces: ` “this is <em>important</em>.” `. And they won't span block-level elements. But there are edge cases that might need putting in curly quotes manually.

## Unicode URLs
My print CSS uses 

```css
article a::after {
		content: " {" attr(href) "}";
}
```

so that the URL's are printed on the page, so I know where my sources are. But URL's that contain Unicode characters with more than one byte (anything that isn't ASCII) are encoded to `%hexnumber` format, which I can't read. So `KavanotParsedown` adds a `data-decodedhref` attribute to all links, with the href run through [urldecode](https://www.php.net/manual/en/function.urldecode.php). So the actual CSS to use is

```css
article a::after {
		content: " {" attr(data-decodedhref) "}";
}
```
