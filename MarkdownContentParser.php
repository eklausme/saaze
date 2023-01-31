<?php declare(strict_types=1);
/* Extension modeled after: https://saaze.dev/docs/extending
   Elmar Klausmeier, 05-Apr-2021
   Elmar Klausmeier, 11-Apr-2021
   Elmar Klausmeier, 22-Apr-2021, added wpvideo()
   Elmar Klausmeier, 30-Aug-2021, added codepen()
   Elmar Klausmeier, 06-Sep-2021, no longer extension, but included in class MarkdownContentParser
   Elmar Klausmeier, 18-Sep-2021, added mermaid()
   Elmar Klausmeier, 12-Apr-2022, added gallery()
   Elmar Klausmeier, 15-Apr-2022, debugged gallery()
   Elmar Klausmeier, 18-Apr-2022, integrated excerpt
   Elmar Klausmeier, 20-Apr-2022, added markmap()
   Elmar Klausmeier, 31-Dec-2022, added youtubelt() therefore reducing JS bloat
   Elmar Klausmeier, 26-Jan-2023, fixed \cases{} TeX issue, needs config in templates as well
*/

namespace Saaze;

//use ParsedownExtra;	// notoriously slow

class MarkdownContentParser {

	const GALLERY_CSS0 = <<<EOD
/* From:  https://www.w3schools.com/howto/tryit.asp?filename=tryhow_js_slideshow_gallery */
* { box-sizing: border-box; }

img { vertical-align: middle; }

/* Position the image container (needed to position the left and right arrows) */
.gallery_container { position: relative; }

.gallery_cursor { cursor: pointer; } /* Add a pointer when hovering over the thumbnail images */

.gallery_prev, .gallery_next { /* Next & previous buttons */
	cursor: pointer;
	position: absolute;
	top: 40%;
	width: auto;
	padding: 16px;
	margin-top: -50px;
	color: white;
	font-weight: bold;
	font-size: 20px;
	border-radius: 0 3px 3px 0;
	user-select: none;
	-webkit-user-select: none;
}

/* Position the "next button" to the right */
.gallery_next { right: 0; border-radius: 3px 0 0 3px; }

/* On hover, add a black background color with a little bit see-through */
.gallery_prev:hover, .gallery_next:hover { background-color: rgba(0, 0, 0, 0.8); }

.gallery_numbertext { /* Number text (1/3 etc) */
	color: #f2f2f2;
	font-size: 12px;
	padding: 8px 12px;
	position: absolute;
	top: 0;
}

.gallery_caption_container { /* Container for image text */
	text-align: center;
	background-color: #222;
	padding: 2px 16px;
	color: white;
}

EOD;
	const GALLERY_JS0 = <<<EOD
var slideIndex = new Array();

function plusSlides(k,n) { showSlides(k,slideIndex[k] += n); }
function currentSlide(k,n) { showSlides(k,slideIndex[k] = n); }

function showSlides(k,n) {
	var i;
	var slides = document.getElementsByClassName("gallery_slides"+k);
	var dots = document.getElementsByClassName("gallery_demo"+k);
	var captionText = document.getElementById("gallery_caption"+k);
	if (n > slides.length) {slideIndex[k] = 1}
	if (n < 1) {slideIndex[k] = slides.length}
	for (i=0; i < slides.length; i++)
		slides[i].style.display = "none";
	for (i=0; i < dots.length; i++)
		dots[i].className = dots[i].className.replace(" active", "");
	slides[slideIndex[k]-1].style.display = "block";
	dots[slideIndex[k]-1].className += " active";
	captionText.innerHTML = dots[slideIndex[k]-1].alt.length ?
		dots[slideIndex[k]-1].alt : slideIndex[k] + " / " + slides.length;
}

EOD;

	//public function __construct() {
	//	// initialize md4c so that we can use it in parallel
	//}

	/**
	* xxx is string between [codepen] xxx [/codepen], and should be in format user / hash.
	* Example: [codepen] thebabydino/eJrPoa [/codepen]
	*/
	static public function codepenHelper(string $xxx, int &$flag, string &$left, string &$right) : string {
		$code = explode("/",$xxx);
		if (count($code) != 2) return $xxx;
		if ($flag === 1) {
			$flag = 2;
			$right = ' style="height:300px; box-sizing:border-box; display:flex;'
				. ' align-items:center; justify-content:center;'
				. ' border:2px solid; margin:1em 0; padding:1em;">';
		} else if ($flag === 0) $flag = 1;
		return	' data-slug-hash=' . trim($code[1])
			. ' data-user=' . trim($code[0]);
	}


	/**
	 * Overall slow. Also has trouble with Github-flavored tables.
	 * But good example how to just convert Markdown to HTML
	 *
	public function toHtml(string $content, array $frontmatter=null) : string {
		return ParsedownExtra::instance()
			->setBreaksEnabled(true)
			->text($content);
	}
	 */

	private int $codepenFlag;	// JavaScript code for Codepen should only be included once
	private int $numOfGalleries;	// count number of galleries in one blog post
	private int $numOfMarkmaps;	// count number of markmaps in one blog post
	private string $cssGallery;	// CSS before HTML for galleries
	private string $jsGallery;	// JavaScript after HTML for galleries
	private string $cssMarkmap;	// CSS before HTML for markmaps: not used, is in cssGallery instead
	private string $jsMarkmap;	// JavaScript after HTML for markmaps: not used, is in jsGallery instead

	/**
	 * Work on abc $$uvw$$ xyz.
	 * Needs MathJax. For this you have to include:
	 *    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
	 *    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
	 */
	private function displayMath(string $content) : string {
		$last = 0;
		for (;;) {
			$start = strpos($content,'$$',$last);
			if ($start === false) break;
			$end = strpos($content,'$$',$start+2);
			if ($end === false) break;
			$last = $end + 2;
			$math = substr($content,$start,$last-$start);
			$math = str_replace('\>','\: ',$math);
			$math = str_replace('<','\lt ',$math);
			$math = str_replace('>','\gt ',$math);
//printf("toHtml(): fileToRender=%s, last=%d, start=%d, end=%d, %s[%s]\n",$GLOBALS['fileToRender'],$last,$start,$end,substr($content,$start,$end-$start+2),substr($content,0,12));
			$content = substr($content,0,$start)
				. "\n<div class=math>\n"
				. $math
				. "\n</div>\n"
				. substr($content,$last);
			$last = $start + strlen("\n<div class=math>\n") + strlen($math) + strlen("\n</div>\n");
		}
		return $content;
	}


	/**
	 * Work on abc $uvw$ xyz.
	 */
	private function inlineMath(string $content) : string {
		$last = 0;
		$i = 0;
		for (;;) {
			//if (++$i > 10) break;
			$start = strpos($content,'$',$last);
			if ($start === false) break;
			// Check if display math with double dollar found?
			if (substr($content,$start+1,1) == '$') { $last = $start + 2; continue; }
			$end = strpos($content,'$',$start+1);
			if ($end === false) break;
			// Check for display math again, just in case
			if (substr($content,$end+1,1) == "$") { $last = $end + 2; continue; }
			// Replace $xyz$" with \\(xyz\\)
			$last = $end + 1;
			$math = substr($content,$start+1,$end-$start-1);
			//$math = str_replace('_','\_',$math);
			$math = str_replace('\\{','\\\\{',$math);
			$math = str_replace('\\}','\\\\}',$math);
			/* Substitute $ to \\(
			$content = substr($content,0,$start)
				. '\\\\('
				. $math
				. '\\\\)'
				. substr($content,$end+1);
			$last = $start + strlen('\\\\(') + strlen($math) + strlen('\\\\)');
			*/
			$content = substr($content,0,$start)
				. '$' . $math . '$'
				. substr($content,$end+1);
			$last = $start + 2+ strlen($math);
		}
		return $content;
	}


	/**
	 * Convert [abc]xxx[/uvw] tags in your markdown to HTML:
	 * $begintag xxx $endtag -> $left xxx $right
	 */
	private function myTag(string $content, string $begintag, string $endtag, string $left, string $right, string|null $callback=NULL) : string {
		$last = 0;
		$len1 = strlen($begintag);
		$len2 = strlen($endtag);
		for (;;) {
			$start = strpos($content,$begintag,$last);
			if ($start === false) break;
			$end = strpos($content,$endtag,$start+$len1);
			if ($end === false) break;
			$xxx = trim(substr($content,$start+$len1,$end-$start-$len1));
			if ($callback !== NULL)
				$xxx = call_user_func_array(__NAMESPACE__.$callback,array($xxx,&$this->codepenFlag,&$left,&$right));
			$last = $end + $len2;
			$content = substr_replace($content, $left . $xxx . $right, $start, $last-$start);
		}
		return $content;
	}


	/**
	 * Convert [youtube]xxx[/youtube] tags in your markdown to HTML:
	 * <iframe width="560" height="315" src=https://www.youtube.com/embed/xxx
	 *    frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
	 * Example: [youtube] a5pnnkXpX-U     [/youtube]
	 */
	private function youtube(string $content) : string {
		return $this->myTag($content, "[youtube]", "[/youtube]",
			"<iframe width=560 height=315 src=https://www.youtube.com/embed/",
			" frameborder=0 allow=\"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>"
		);
	}


	/**
	 * YouTube lowtech/light is similar to [youtube] but does not incur the hight cost of YouTube's JavaScript libraries
	 * Convert [youtubelt]xxx[/youtubelt] tags in your markdown to HTML:
	 * <a href=\"https://www.youtube.com/watch?v=xxx"><img src="https://i.ytimg.com/vi/xxx/hqdefault.jpg"></a>
	 * Example: [youtubelt] a5pnnkXpX-U     [/youtubelt]
	 */
	private function youtubelt(string $content) : string {
		$last = 0;
		$begintag = "[youtubelt]";
		$endtag = "[/youtubelt]";
		$len1 = strlen($begintag);
		$len2 = strlen($endtag);
		for (;;) {
			$start = strpos($content,$begintag,$last);
			if ($start === false) break;
			$end = strpos($content,$endtag,$start+$len1);
			if ($end === false) break;
			$xxx = trim(substr($content,$start+$len1,$end-$start-$len1));
			$last = $end + $len2;
			$content = substr_replace($content, "<a href=\"https://www.youtube.com/watch?v=".$xxx."\"><img alt=YouTube src=\"https://i.ytimg.com/vi/".$xxx."/hqdefault.jpg\"></a>", $start, $last-$start);
		}
		return $content;
	}


	/**
	 * Convert [vimeo]xxx[/vimeo] tags in your markdown to HTML:
	 * <iframe src="https://player.vimeo.com/video/126529871" width="640" height="360"
	 *     frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
	 * Example: [vimeo] 126529871 [/vimeo]
	 */
	private function vimeo(string $content) : string {
		return $this->myTag($content, "[vimeo]", "[/vimeo]",
			"<iframe src=https://player.vimeo.com/video/",
			" width=560 height=315 frameborder=0 allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen></iframe>"
		);
	}


	/**
	 * Convert [twitter]xxx[/twitter] tags in your markdown HTML which Twitter-JavaScript understands.
	 * xxx is for example: https://twitter.com/eklausmeier/status/1352896936051937281
	 * i.e., just the URL, no other information is required.
	 * This xxx is "Copy link to Tweet" button in Twitter.
	 *
	 * Make sure that your layout-template contains the following:
	 *    <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
	 * or set "Twitter: true" in your frontmatter.
	 */
	private function twitter(string $content) : string {
		return $this->myTag($content, "[twitter]", "[/twitter]",
			"<blockquote class=\"twitter-tweet\"><a href=\"",
			"\"</a></blockquote>"
		);
	}


	/**
	 * Convert [codepen] user / hash [/codepen] tags in your markdown to HTML.
	 * Example: [codepen] thebabydino/eJrPoa [/codepen]
	 * Needs codepenHelper() function to separate user/hash pair.
	 */
	private function codepen(string $content) : string {
		$left = "<p class=codepen data-height=300 data-default-tab=\"html,result\"";
		// right-variable will be changed, if codepen occurs more than once
		$right = " style=\"height:300px; box-sizing:border-box; display:flex;"
			. " align-items:center; justify-content:center;"
			. " border:2px solid; margin:1em 0; padding:1em;\">"
			. " <script async src=https://cpwebassets.codepen.io/assets/embed/ei.js></script>";
		return $this->myTag($content,"[codepen]","[/codepen]",$left,$right,"\MarkdownContentParser::codepenHelper");
	}


	/**
	 * Convert [mermaid] Mermaid code [/mermaid] tags in your markdown to HTML.
	 * Example:
	 *     [mermaid]
	 *        pie title Simple chart
	 *           "A": 75
	 *           "B": 25
	 *     [/mermaid]
	 */
	private function mermaid(string $content) : string {
		return $this->myTag($content,"[mermaid]","[/mermaid]","\n<div class=mermaid>\n","\n</div>\n");
	}



	/**
	 * Convert [markmap] markmap code [/markdown] tags in your markdown to HTML.
	 * Example:
	 *     [markmap]
	 *	# markmap
	 *	## autoloader
	 *	## transformer
	 *     [/markmap]
	 */
	private function markmap(string $content) : string {
		if (($this->numOfMarkmaps += 1) <= 1) {
			$this->cssMarkmap .= "<style> .markmap > svg { width: 100%; height: 300px; } </style>\n";
			$this->jsMarkmap .= "<script src=\"https://cdn.jsdelivr.net/npm/markmap-autoloader\"></script>\n";
		}
		return $this->myTag($content,"[markmap]","[/markmap]","\n<div class=markmap>\n","\n</div>\n");
	}


	/**
	 * Convert [gallery] directory regex [/gallery] tags in your markdown to HTML.
	 * Example:
	 *     [gallery] img/gallery /IMG_20220401.*\.webp/ [/gallery]
	 * CSS+JS taken from
	 *     https://www.w3schools.com/howto/tryit.asp?filename=tryhow_js_slideshow_gallery
	 */
	private function gallery(string $content) : string {
		$last = 0;
		for ($i=0;;++$i) {
			//if (++$i > 10) break;
			$start = strpos($content,'[gallery]',$last);
			if ($start === false) break;
			$end = strpos($content,'[/gallery]',$start+9);
			if ($end === false) break;
			$last = $end + 10;
			$mid = trim(substr($content,$start+9,$end-$start-9));	// get rid of spaces at begin+end
			$midx = strpos($mid,' ');
			if ($midx === false) break;	// lacking separator between directory & regex
			$dirWeb = substr($mid,0,$midx);
			$dir = \Saaze\Config::$H['global_path_public'] . $dirWeb;
			$regex = substr($mid,$midx+1);
			if ($i === 0) {
				$this->cssGallery = "\n<style>\n";
				$this->jsGallery = "\n<script>\n";
			}
			if (($this->numOfGalleries += 1) <= 1) {
				$this->cssGallery .= self::GALLERY_CSS0;
				$this->jsGallery .= self::GALLERY_JS0;
			}
			$cnt = 0;
			$dirlist = scandir($dir);
			$photolist = array();
			foreach ($dirlist as $fn) {
				if ($fn === '.' || $fn === '..') continue;
				if (preg_match($regex,$fn) !== 1) continue;
				if (is_dir($fn)) continue;
				$photolist[] = $fn;	// push filename
				++$cnt;
			}

			/* Example: Six columns side by side. CSS is dependant on number of photos. */
			$this->cssGallery .= "\n.gallery_slides{$this->numOfGalleries} { display: none; } /* Hide the images by default */\n"
				. ".gallery_row{$this->numOfGalleries}:after { content: \"\"; display: table; clear: both; }\n"
				. sprintf(".gallery_column%d { float:left; width:%f%%; }\n",$this->numOfGalleries,100/$cnt)
				. ".gallery_demo{$this->numOfGalleries} { opacity: 0.6; } /* Add a transparency effect for thumbnail images */\n"
				. ".active, .gallery_demo{$this->numOfGalleries}:hover { opacity: 1; }\n";
			$this->jsGallery .= "slideIndex[{$this->numOfGalleries}] = 1;\n"
				. "showSlides({$this->numOfGalleries},slideIndex[{$this->numOfGalleries}]);\n";

			$html = "<div class=\"gallery_container\">\n";
			$k=0;
			foreach ($photolist as $fn) {
				$html .= sprintf("\t<div class=gallery_slides%d style=\"display:%s;\"><div class=gallery_numbertext>"
					. "%d / %d</div><img src=\"%s/%s\" alt=\"%s\" style=\"width:100%%\"></div>\n",
					$this->numOfGalleries, $k ? "none":"block", $k+1, $cnt, $dirWeb, $fn, $fn);
				++$k;
			}
			$html .= "\t<a class=\"gallery_prev\" onclick=\"plusSlides({$this->numOfGalleries},-1)\">❮</a>\n"
				. "\t<a class=\"gallery_next\" onclick=\"plusSlides({$this->numOfGalleries},1)\">❯</a>\n"
				. "\t<div class=gallery_caption_container><p id=gallery_caption{$this->numOfGalleries}></p></div>\n"
				. "\t<div class=\"gallery_row{$this->numOfGalleries}\">\n";
			$k=0;
			foreach ($photolist as $fn) {
				$html .= sprintf("\t\t<div class=gallery_column%d>"
					. "<img class=\"gallery_demo%d gallery_cursor%s\" "
					. "src=\"%s/%s\" alt=\"%s\" style=\"width:100%%\" onclick=\"currentSlide(%d,%d)\"></div>\n",
					$this->numOfGalleries, $this->numOfGalleries, $k ? "":" active",
					$dirWeb, $fn, $fn, $this->numOfGalleries, $k+1);
				++$k;
			}
			$html .= "\t</div>\n</div>\n";

			//$tst = "\ndir=$dir, dirWeb=$dirWeb regex=$regex, mid=|$mid|, midx=$midx, rbase={$GLOBALS['rbase']}\n";
			$content = substr($content,0,$start) . $html . substr($content,$end+10);
			$last = $start + strlen($html);
		}
		if ($i > 0) {
			$this->cssGallery .= "</style>\n\n";
			$this->jsGallery .= "</script>\n\n";
		}
		return $content;
	}


	/**
	 * Simply drop [more_WP_Tag], the WordPress <!--more--> tag
	 */
	private function moreTag(string $content) : string {
		return str_replace("[more_WP_Tag]","",$content);
	}


	/**
	 * [wpvideo xxx w=400 h=224] -> <iframe...></iframe>
	 * <iframe width='400' height='224' src='https://video.wordpress.com/embed/RLkLgz2V?hd=0&amp;autoPlay=0&amp;permalink=0&amp;loop=0' frameborder='0' allowfullscreen></iframe>
	 */
	private function wpvideo(string $content) : string {
		return preg_replace(
			'/\[wpvideo\s+(\w+)\s+w=(\w+)\s+h=(\w+)\s*\]/',
			"<iframe width='$2' height='$3' src='https://video.wordpress.com/embed/$1&amp;autoplay=0' allowfullscreen></iframe>",
			$content
		);
	}


	/**
	 * Correct Markdown bug: ampersands wrongly htmlified in links
	 * Convert href="http://a.com&amp;22" to href="http://a.com&22"
	 */
	private function amplink(string $html) : string {
		$begintag = array(" href=\"http", " src=\"http");
		$i = 0;
		foreach($begintag as $tag) {
			$last = 0;
			for(;;) {
				$start = strpos($html,$tag,$last);
				if ($start === false) break;
				$last = $start + 10;
				$end = strpos($html,"\"",$last);
				if ($end === false) break;
				$link = substr($html,$start,$end-$start);
				$link = str_replace("&amp;","&",$link);
				$html = substr_replace($html, $link, $start, $end-$start);
				++$i;
			}
		}
		//printf("\t\tamplink() changed %d times\n",$i);
		return $html;
	}


	private function getExcerpt(string $html, Entry &$entry) : string {
		$excerpt = strip_tags($html);
		$length = $entry->collection->data['excerpt_length'] ?? \Saaze\Config::$H['global_excerpt_length'];

		if (strlen($excerpt) > $length) {
			$excerptCut = substr($excerpt, 0, $length);
			$endPoint   = strrpos($excerptCut, ' ');
			$excerpt    = $endPoint ? substr($excerptCut, 0, $endPoint) : substr($excerptCut, 0);
			$excerpt    .= '&hellip;';
		}
		return $excerpt;
	}


	/**
	 * Parse raw content and return HTML
	 */
	private array $keywords = Array('MathJax/Dummy','[youtube]','[youtubelt]','[vimeo]','[twitter]','[codepen]','[wpvideo','[mermaid]','[gallery]','[markmap]');

	// pass by reference for entry
	public function toHtml(string $content, Entry &$entry) : string {
		$t0 = microtime(true);
		$this->codepenFlag = 0;	// reset for every new blog page
		$this->numOfGalleries = 0;	// reset for every new blog page
		$this->numOfMarkmaps = 0;
		$this->cssGallery = "";
		$this->jsGallery = "";
		$this->cssMarkmap = "";
		$this->jsMarkmap = "";
		$content = $this->moreTag($content);	// more-tag can occur anywhere

		// Performance optimization only, no functional benefit.
		// Only marginally relevant if you have more than a few hundred posts.
		$hasKeyword = 0;	// used as bitset
		for ($i=1; $i<=9; ++$i) {	// 9 = count($keywords)-1
			if (strpos($content,$this->keywords[$i]) === false) continue;
			$hasKeyword |= 1 << $i;
		}
		$hasMath = $entry->data['MathJax'] ?? false;	//isset($frontmatter['MathJax']);
		if ($hasMath) $hasKeyword |= 1;
		$hasYoutube = $hasKeyword & 2;
		$hasYoutubeLT = $hasKeyword & 4;
		$hasVimeo =$hasKeyword & 8;
		$hasTwitter = $hasKeyword & 16;
		$hasCodepen = $hasKeyword & 32;
		$hasWpvideo = $hasKeyword & 64;
		$hasMermaid = $hasKeyword & 128;
		$hasGallery = $hasKeyword & 256;
		$hasMarkmap = $hasKeyword & 512;

		if ($hasKeyword) {
			$arr = explode("`",$content);	// known deficiency: does not cope for HTML comments
			// even elements can be changed, uneven are code-block elements
			// 0 `1`2` 3 `4`5` 6 ...
			// special case: ``` has two empty slots
			$empty = 0;
			$incr = 1;
			$newline = 0;

			//$fp = fopen("/tmp/MathParser.debug","w");
			for ($i=0, $size=count($arr); $i<$size; ++$i) {
				if (strlen($arr[$i]) == 0) {
					if ($i>0  &&  ($empty == 0 || $empty == 2)  &&  substr($arr[$i-1],-1) == "\n") {
						$newline = 1;
					}
					if ($newline == 1) $empty += $incr;
					if ($empty == 2) {
						$incr = -1;
						$newline = 0;
					} else if ($empty == 0) {
						$incr = 1;
						$newline = 0;
					}
				}
				//fprintf($fp,"i=%d, empty=%d, incr=%d, newline=%d, arr[i-1]=%d~%s\n",$i,$empty,$incr,$newline,$i<=0?(-55):ord(substr($arr[$i-1],-1)),$i<=0?"***":substr($arr[$i-1],-19,18));
				if ($i % 2 == 1) continue;	// skip uneven: 0 `1` 2
				if ($incr < 0) continue;	// skip code section starting with ```
				if ($hasMath) {
					$arr[$i] = $this->displayMath($arr[$i]);
					$arr[$i] = $this->inlineMath($arr[$i]);
				}
				if ($hasYoutube) $arr[$i] = $this->youtube($arr[$i]);
				if ($hasYoutubeLT) $arr[$i] = $this->youtubelt($arr[$i]);
				if ($hasVimeo) $arr[$i] = $this->vimeo($arr[$i]);
				if ($hasTwitter) $arr[$i] = $this->twitter($arr[$i]);
				if ($hasCodepen) $arr[$i] = $this->codepen($arr[$i]);
				if ($hasWpvideo) $arr[$i] = $this->wpvideo($arr[$i]);
				if ($hasMermaid) $arr[$i] = $this->mermaid($arr[$i]);
				if ($hasGallery) $arr[$i] = $this->gallery($arr[$i]);
				if ($hasMarkmap) $arr[$i] = $this->markmap($arr[$i]);
			}
			$modContent = implode("`",$arr);
		} else {
			$modContent = $content;
		}

		$t1 = microtime(true);
		$GLOBALS['MathParser'] += $t1 - $t0;
		$GLOBALS['MathParserNcall'] += 1;
		//$html = parent::toHtml($modConent);	// markdown to HTML
		//$html = \FFI::string( $GLOBALS['ffi']->md4c_toHtml($modContent) );
		$html = \FFI::string( \Saaze\Config::$H['global_ffi']->md4c_toHtml($modContent) );
		if ($entry->data) {
			$entry->data['excerpt'] = $this->getExcerpt($html,$entry);
			if ($hasGallery) {
				$entry->data['gallery_css'] = $this->cssGallery;
				$entry->data['gallery_js'] = $this->jsGallery;
			}
			if ($hasMarkmap) {
				$entry->data['markmap_css'] = $this->cssMarkmap;
				$entry->data['markmap_js'] = $this->jsMarkmap;
			}
		}
		//$html = $this->cssGallery . $html . $this->jsGallery;	// does not pass test in https://validator.w3.org/
		$GLOBALS['md2html'] += microtime(true) - $t1;

		return $this->amplink($html);	// fix Markdown ampersand handling
	}


}
