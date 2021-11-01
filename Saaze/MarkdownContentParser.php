<?php
/* Extension modeled after: https://saaze.dev/docs/extending
   Elmar Klausmeier, 05-Apr-2021
   Elmar Klausmeier, 11-Apr-2021
   Elmar Klausmeier, 22-Apr-2021, added wpvideo()
   Elmar Klausmeier, 30-Aug-2021, added codepen()
   Elmar Klausmeier, 06-Sep-2021, no longer extension, but included in class MarkdownContentParser
   Elmar Klausmeier, 18-Sep-2021, added mermaid()
*/

namespace Saaze;

//use ParsedownExtra;	// notoriously slow

class MarkdownContentParser {

	//public function __construct() {
	//	// initialize md4c so that we can use it in parallel
	//}

	/**
	* xxx is string between [codepen] xxx [/codepen], and should be in format user / hash.
	* Example: [codepen] thebabydino/eJrPoa [/codepen]
	*
	* @param string $xxx
	* @return string
	*/
	static public function codepenHelper($xxx,&$flag,&$left,&$right) {
		$code = explode("/",$xxx);
		if (count($code) != 2) return $xxx;
		if ($flag === 1) {
			$flag = 2;
			$right = " style=\"height:300px; box-sizing:border-box; display:flex;"
				. " align-items:center; justify-content:center;"
				. " border:2px solid; margin:1em 0; padding:1em;\">";
		} else if ($flag === 0) $flag = 1;
		return	" data-slug-hash=" . trim($code[1])
			. " data-user=" . trim($code[0]);
	}


	/**
	 * Overall slow. Also has trouble with Github-flavored tables.
	 * But good example how to just convert Markdown to HTML
	 *
	 * @param string $content
	 * @return string
	public function toHtml(string $content,$frontmatter=null)
	{
		return ParsedownExtra::instance()
			->setBreaksEnabled(true)
			->text($content);
	}
	 */

	private $codepenFlag;	// JavaScript code for Codepen should only be included once
	private $mermaidFlag;	// JavaScript code for Mermaid should only be included once

	/**
	 * Work on abc $$uvw$$ xyz.
	 * Needs MathJax. For this you have to include:
	 *    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
	 *    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
	 *
	 * @param string $content
	 * @return string
	 */
	private function displayMath($content) {
		$last = 0;
		for (;;) {
			$start = strpos($content,"$$",$last);
			if ($start === false) break;
			$end = strpos($content,"$$",$start+2);
			if ($end === false) break;
			$last = $end + 2;
			$math = substr($content,$start,$last-$start);
			$math = str_replace("\\>","\\: ",$math);
			$math = str_replace("<","\\lt ",$math);
			$math = str_replace(">","\\gt ",$math);
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
	 * @param string $content
	 * @return string
	 */
	private function inlineMath($content) {
		$last = 0;
		$i = 0;
		for (;;) {
			//if (++$i > 10) break;
			$start = strpos($content,"$",$last);
			if ($start === false) break;
			// Check if display math with double dollar found?
			if (substr($content,$start+1,1) == "$") { $last = $start + 2; continue; }
			$end = strpos($content,"$",$start+1);
			if ($end === false) break;
			// Check for display math again, just in case
			if (substr($content,$end+1,1) == "$") { $last = $end + 2; continue; }
			// Replace $xyz$" with \\(xyz\\)
			$last = $end + 1;
			$math = substr($content,$start+1,$end-$start-1);
			$math = str_replace("_","\\_",$math);
			$math = str_replace("\\{","\\\\{",$math);
			$math = str_replace("\\}","\\\\}",$math);
			$content = substr($content,0,$start)
				. "\\\\("
				. $math
				. "\\\\)"
				. substr($content,$end+1);
			$last = $start + strlen("\\\\(") + strlen($math) + strlen("\\\\)");
		}
		return $content;
	}


	/**
	 * Convert [abc]xxx[/uvw] tags in your markdown to HTML:
	 * $begintag xxx $endtag -> $left xxx $right
	 *
	 * @param string $content, $begintag, $endtag, $left, $right
	 * @return string
	 */
	private function myTag($content,$begintag,$endtag,$left,$right,$callback=NULL) {
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
	 *
	 * @param string $content
	 * @return string
	 */
	private function youtube($content) {
		return $this->myTag($content, "[youtube]", "[/youtube]",
			"<iframe width=560 height=315 src=https://www.youtube.com/embed/",
			" frameborder=0 allow=\"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>"
		);
	}


	/**
	 * Convert [vimeo]xxx[/vimeo] tags in your markdown to HTML:
	 * <iframe src="https://player.vimeo.com/video/126529871" width="640" height="360"
	 *     frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
	 * Example: [vimeo] 126529871 [/vimeo]
	 *
	 * @param string $content
	 * @return string
	 */
	private function vimeo($content) {
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
	 *
	 * @param string $content
	 * @return string
	 */
	private function twitter($content) {
		return $this->myTag($content, "[twitter]", "[/twitter]",
			"<blockquote class=\"twitter-tweet\"><a href=\"",
			"\"</a></blockquote>"
		);
	}


	/**
	 * Convert [codepen] user / hash [/codepen] tags in your markdown to HTML.
	 * Example: [codepen] thebabydino/eJrPoa [/codepen]
	 * Needs codepenHelper() function to separate user/hash pair.
	 *
	 * @param string $content
	 * @return string
	 */
	private function codepen($content) {
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
	 *
	 * @param string $content
	 * @return string
	 */
	private function mermaid($content) {
		return $this->myTag($content,"[mermaid]","[/mermaid]","\n<div class=mermaid>\n","\n</div>\n");
	}


	/**
	 * Simply drop [more_WP_Tag], the WordPress <!--more--> tag
	 *
	 * @param string $content
	 * @return string
	 */
	private function moreTag($content) {
		return str_replace("[more_WP_Tag]","",$content);
	}


	/**
	 * [wpvideo xxx w=400 h=224] -> <iframe...></iframe>
	 * <iframe width='400' height='224' src='https://video.wordpress.com/embed/RLkLgz2V?hd=0&amp;autoPlay=0&amp;permalink=0&amp;loop=0' frameborder='0' allowfullscreen></iframe>
	 *
	 * @param string $content
	 * @return string
	 */
	private function wpvideo($content) {
		return preg_replace(
			'/\[wpvideo\s+(\w+)\s+w=(\w+)\s+h=(\w+)\s*\]/',
			"<iframe width='$2' height='$3' src='https://video.wordpress.com/embed/$1&amp;autoplay=0' allowfullscreen></iframe>",
			$content
		);
	}


	/**
	 * Correct Markdown bug: ampersands wrongly htmlified in links
	 * Convert href="http://a.com&amp;22" to href="http://a.com&22"
	 * @param string $html
	 * @return string
	 */
	private function amplink($html) {
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


	/**
	 * Parse raw content and return HTML
	 * @param string $content
	 * @return string
	 */
	private $keywords = Array("[youtube]","[vimeo]","[twitter]","[codepen]","[wpvideo","[mermaid]");

	public function toHtml(string $content,$frontmatter=null) {
		$t0 = microtime(true);
		$this->codepenFlag = 0;	// reset for every new blog page
		$this->mermaidFlag = 0;	// reset for every new blog page
		$content = $this->moreTag($content);	// more-tag can occur anywhere

		// Performance optimization only, no functional benefit.
		// Only marginally relevant if you have more than a few hundred posts.
		$hasKeyword = 0;	// used as bitset
		for ($i=0; $i<6; ++$i) {
			if (strpos($content,$this->keywords[$i]) === false) continue;
			$hasKeyword |= 1 << $i;
		}
		$hasYoutube = $hasKeyword & 1;
		$hasVimeo =$hasKeyword & 2;
		$hasTwitter = $hasKeyword & 4;
		$hasCodepen = $hasKeyword & 8;
		$hasWpvideo = $hasKeyword & 16;
		$hasMermaid = $hasKeyword & 32;
		$hasMath = isset($frontmatter['MathJax']);
		if ($hasMath) $hasKeyword |= 64;

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
				if ($hasVimeo) $arr[$i] = $this->vimeo($arr[$i]);
				if ($hasTwitter) $arr[$i] = $this->twitter($arr[$i]);
				if ($hasCodepen) $arr[$i] = $this->codepen($arr[$i]);
				if ($hasWpvideo) $arr[$i] = $this->wpvideo($arr[$i]);
				if ($hasMermaid) $arr[$i] = $this->mermaid($arr[$i]);
			}
			$modContent = implode("`",$arr);
		} else {
			$modContent = $content;
		}

		$t1 = microtime(true);
		$GLOBALS['MathParser'] += $t1 - $t0;
		$GLOBALS['MathParserNcall'] += 1;
		//$html = parent::toHtml($modConent);	// markdown to HTML
		$html = \FFI::string( $GLOBALS['ffi']->md4c_toHtml($modContent) );
		$GLOBALS['md2html'] += microtime(true) - $t1;

		return $this->amplink($html);	// fix Markdown ampersand handling
	}


}
