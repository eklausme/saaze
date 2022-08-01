<p>
<a href="https://packagist.org/packages/eklausme/saaze"><img src="https://img.shields.io/packagist/v/eklausme/saaze" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/eklausme/saaze"><img src="https://img.shields.io/packagist/l/eklausme/saaze" alt="License"></a>
</p>

# Simplified Saaze

_Simplified Saaze_ is a fast, all-inclusive, flat-file CMS for simple websites and blogs. It's not designed to be a replacement for great CMS's (e.g. [WordPress](https://wordpress.com), [Statamic](https://statamic.com) etc.), rather it's designed to be a smaller, simpler alternative.

Static site builders are fast but normally have a steep learning curve and require lots of tooling to make them work. We believe building a personal site should be stupidly simple. That's why _Simplified Saaze_ is built on the following principles.

* Easy to run - All you need is PHP8, a C compiler, and Composer; no dependency hell
* Easy to host - Serve dynamically or statically
* Easy to edit - Edit content using simple Markdown files
* Easy to theme - Templates use plain PHP/HTML
* Fast and secure - No database = less moving parts + more speed, [30-times faster than Hugo](https://eklausmeier.goip.de/blog/2021/11-13-performance-comparison-saaze-vs-hugo-vs-zola), and 4-times faster than Zola
* Simple to understand - Everything is a collection of entries

_Simplified Saaze_ is, as the name implies, a simplifed version of _Saaze_. For more info and documentation for the original Saaze see https://saaze.dev. Read [_Simplified Saaze_](https://eklausmeier.goip.de/blog/2021/10-31-simplified-saaze) for installation and usage.

# Easy to understand

Entire code is ca. 1kLines of PHP and C code.

```bash
$ wc *.php *.c
  229   773  8642 BuildCommand.php
   35    97   980 CollectionManager.php
   25    74   757 Collection.php
   41   178  1595 Config.php
  131   428  3816 EntryManager.php
   98   381  3460 Entry.php
  555  2236 20272 MarkdownContentParser.php
   73   227  2239 SaazeCli.php
  112   478  6008 Saaze.php
   91   285  3010 TemplateManager.php
   82   242  1929 php_md4c_toHtml.c
 1472  5399 52708 total
```

# Credits

_Simplified Saaze_ was created by [Elmar Klausmeier](https://eklausmeier.goip.de/aux/about).

[Saaze](https://saaze.dev) was created by [Gilbert Pellegrom](https://gilbitron.me) from [Dev7studios](https://dev7studios.co). Released under the MIT license.

