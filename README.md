# kavanotparsedown
 
 Extensions to [Parsedown](http://parsedown.org) that makes my life easier editing http://kavanot.name.

It does not incorporate [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/), since I think the only thing I want from that would be tables, and Parsedown already includes that. Markdown inside HTML elements and definition lists might be useful, so I may extend from (Parsedown Extra)[https://github.com/erusev/parsedown-extra] at some point.

## `<i>` elements
I use a lot of Hebrew, including transliterations. According to [MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/i):
>The HTML `<i>` element represents a range of text that is set off from the normal text for some reason. Some examples include technical terms, foreign language phrases, or fictional character thoughts. It is typically displayed in italic type.

So a transliterated term should be `<i lang=he>Shabbat</i>`. I use `/` for that: `/Shabbat/` becomes `<i lang=he>Shabbat</i>`.

URL's will still be parsed, but other uses of slashes should be escaped.

## `<cite>` elements

To Be Done
