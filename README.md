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
  280  1100 12088 BuildCommand.php
   40   111  1117 CollectionArray.php
  127   511  4478 Collection.php
   52   233  2152 Config.php
   93   406  3734 Entry.php
  674  2818 25740 MarkdownContentParser.php
  103   380  3569 SaazeCli.php
  122   519  6567 Saaze.php
   93   326  3375 TemplateManager.php
   83   263  2030 php_md4c_toHtml.c
 1667  6667 64850 total
```


# Examples

Nr | Theme demo                      | GitHub                                                         | blog post about theme
---|---------------------------------|----------------------------------------------------------------|------------------------
1 | [Saaze example](https://eklausmeier.goip.de/saaze-example) | [saaze-example](https://github.com/eklausme/saaze-example)     | n/a
2 | [J-Pilot](https://eklausmeier.goip.de/jpilot)              | [saaze-jpilot](https://github.com/eklausme/saaze-jpilot)       | [Example Theme for Simplified Saaze: J-Pilot](https://eklausmeier.goip.de/blog/2022/06-27-example-theme-for-simplified-saaze-jpilot)
3 | [Koehntopp](https://eklausmeier.goip.de/koehntopp)         | [saaze-nukeklaus](https://github.com/eklausme/saaze-koehntopp) | [Example Theme for Simplified Saaze: Koehntopp](https://eklausmeier.goip.de/blog/2022/07-09-example-theme-for-simplified-saaze-koehntopp)
4 | [NukeKlaus](https://eklausmeier.goip.de/nukeklaus)         | [saaze-koehntopp](https://github.com/eklausme/saaze-nukeklaus) | [Example Theme for Simplified Saaze: nukeKlaus](https://eklausmeier.goip.de/blog/2022/09-05-example-theme-for-simplified-saaze-nukeklaus)
5 | [Mobility](https://eklausmeier.goip.de/mobility)           | [saaze-mobility](https://github.com/eklausme/saaze-mobility)   | [Example Theme for Simplified Saaze: Mobility](https://eklausmeier.goip.de/blog/2023/01-21-example-theme-for-simplified-saaze-mobility)
6 | [Vonhoff](https://eklausmeier.goip.de/vonhoff)             | [saaze-vonhoff](https://github.com/eklausme/saaze-vonhoff)     | [Example Theme for Simplified Saaze: Vonhoff](https://eklausmeier.goip.de/blog/2023/06-05-example-theme-for-simplified-saaze-vonhoff)
7 | [Paternoster](https://eklausmeier.goip.de/paternoster)     | [saaze-paternoster](https://github.com/eklausme/saaze-paternoster) | [Example Theme for Simplified Saaze: Paternoster](https://eklausmeier.goip.de/blog/2023/06-23-example-theme-for-simplified-saaze-paternoster)
8 | [Panorama](https://eklausmeier.goip.de/panorama)           | [saaze-panorama](https://github.com/eklausme/saaze-panorama) | [Example Theme for Simplified Saaze: Panorama](https://eklausmeier.goip.de/blog/2023/09-27-example-theme-for-simplified-saaze-panorama)
9 | [Lemire](https://eklausmeier.goip.de/lemire)               | [saaze-lemire](https://github.com/eklausme/saaze-lemire) | [Example Theme for Simplified Saaze: Lemire](https://eklausmeier.goip.de/blog/2024/01-02-example-theme-for-simplified-saaze-lemire)


# Credits

_Simplified Saaze_ was created by [Elmar Klausmeier](https://eklausmeier.goip.de/aux/about).

[Saaze](https://saaze.dev) was created by [Gilbert Pellegrom](https://gilbitron.me). Released under the MIT license.

