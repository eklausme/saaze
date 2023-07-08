<p>
<a href="https://packagist.org/packages/eklausme/saaze"><img src="https://img.shields.io/packagist/v/eklausme/saaze" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/eklausme/saaze"><img src="https://img.shields.io/packagist/l/eklausme/saaze" alt="License"></a>
</p>

# Simplified Saaze

_Simplified Saaze_ is a fast, all-inclusive, flat-file CMS for simple websites and blogs. It comes with no graphical user-interface, but rather is used entirely via command-line.

Static site builders are fast but normally have a steep learning curve and require lots of tooling to make them work. We believe building a personal site should be stupidly simple. That's why _Simplified Saaze_ is built on the following principles.

* Easy to run - All you need is PHP8, a C compiler, and Composer; no dependency hell
* Easy to host - Serve dynamically or statically
* Easy to edit - Edit content using simple Markdown files
* Easy to theme - Templates use plain PHP/HTML
* Fast and secure - No database = less moving parts + more speed, [30-times faster than Hugo](https://eklausmeier.goip.de/blog/2021/11-13-performance-comparison-saaze-vs-hugo-vs-zola), and 4-times faster than Zola
* Simple to understand - Everything is a collection of entries

_Simplified Saaze_ is, as the name implies, a simplifed version of _Saaze_. For more info and documentation for the original Saaze see https://saaze.dev. Read [_Simplified Saaze_](https://eklausmeier.goip.de/blog/2021/10-31-simplified-saaze) for installation and usage.

# Easy to understand

Entire code is ca. 1.5kLines of PHP and C code.

```bash
wc *.php *.c
  242   897 10310 BuildCommand.php
   40   111  1117 CollectionArray.php
  133   492  4351 Collection.php
   46   206  1827 Config.php
   93   400  3698 Entry.php
  663  2748 25184 MarkdownContentParser.php
   98   336  3261 SaazeCli.php
  122   516  6557 Saaze.php
   86   293  2935 TemplateManager.php
   82   242  1929 php_md4c_toHtml.c
 1605  6241 61169 total
```

# Credits

_Simplified Saaze_ was created by [Elmar Klausmeier](https://eklausmeier.goip.de/aux/about).

[Saaze](https://saaze.dev) was created by [Gilbert Pellegrom](https://gilbitron.me). Released under the MIT license.

