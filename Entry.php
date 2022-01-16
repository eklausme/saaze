<?php declare(strict_types=1);

namespace Saaze;


class Entry {
	public Collection|null $collection = null;	// "father" collection for this entry

	public string $filePath;

	public array|null $data;	// hash containing frontmatter Yaml parsed, Markdown content in 'content_raw'

	protected MarkdownContentParser $contentParser;

	public function __construct(string $filePath) {	//, ContentParserInterface $contentParser), EntryParserInterface $entryParser)
		$this->filePath      = $filePath;
		//$this->slug = $this->computeSlug();
		$this->contentParser = new MarkdownContentParser; //$contentParser;

		//$this->data = new Dot($entryParser->parseEntry($this->filePath));
		$this->data = $this->parseEntry($this->filePath);
	}


	private function parseEntry(string $filePath) : array|null {
		$t0 = microtime(true);
		// We proved that filePath as many times as collection has pages
		//file_put_contents("/tmp/parseEntry.txt",$filePath." ".debug_backtrace()[1]['function']." ".debug_backtrace()[2]['function']." ".debug_backtrace()[3]['function']."\n",FILE_APPEND);
		$content = @file_get_contents($filePath);
		if ($content === false) return null;

		$n3dash = 0;	// count number of triple dashes
		$pos1 = 0;
		$pos2 = 0;
		$len = strlen($content);

		for ($pos=0;; $pos+=3) {
			$pos = strpos($content,"---",$pos);
			if ($pos === false) {	// no pair of triple dashes at all
				$data = [];
				$data['content_raw'] = $content;
				$GLOBALS['YamlParser'] += microtime(true) - $t0;
				return $data;
			}
			// Are we at end or is next character white space?
			if ( $pos + 3 == $len  ||  ctype_space(substr($content,$pos+3,1)) ) {
				if ($n3dash == 0  &&  ($pos == 0 || $pos > 0 && substr($content,$pos-1,1)=="\n")) {
					$n3dash = 1;	// found first triple dash
					$pos1 = $pos + 3;
				} else if ($n3dash == 1  &&  substr($content,$pos-1,1) == "\n") {
					// found 2nd properly enclosed triple dash
					$n3dash = 2; $pos2 = $pos + 3; break;
				}
			}
		}
		$matter = substr($content,$pos1,$pos2-3-$pos1);
		$body = substr($content,$pos2);
		//$matter = Yaml::parse($matter);	// slow and additional dependency
		$matter = yaml_parse($matter);	// cuts almost 40% of the runtime, so highly recommended

		// $object = EntryParser::parse(file_get_contents($filePath));
		//$data = $object->matter();
		//$data['content_raw'] = $object->body();

		$data = $matter;
		$data['content_raw'] = $body;

		$GLOBALS['YamlParser'] += microtime(true) - $t0;
		$GLOBALS['YamlParserNcall'] += 1;
		return $data;
	}


	public function setCollection(Collection $collection) : void {
		$this->collection = $collection;
	}

	public function slug() : string {
		$dotPos = strrpos($this->filePath, '.');
		if ($dotPos === false) exit("{$this->filePath} does not contain dot.");
		$slugStr = substr($this->filePath, 0, $dotPos);
		$slugStr = str_replace(\Saaze\Config::$H['global_path_content'], '', $slugStr);
		$slugStr = str_replace("/{$this->collection->slug}", '', $slugStr);
		$slugStr = ltrim($slugStr, '/');

		return $slugStr;
	}

	public function getUrl() : string {
		if (array_key_exists('url',$this->data)) {	//if ($this->data->has('url')) {
			return $this->data['url'];
		}

		$slugStr = $this->slug();

		//return rtrim(str_replace('{slug}', $slugStr, $this->collection->data['entry_route']), '/');
		$this->data['url'] = rtrim(str_replace('{slug}', $slugStr, $this->collection->data['entry_route']), '/');
		return $this->data['url'];
	}

	public function getContent() : string {
		$GLOBALS['content'] += 1;
		if (array_key_exists('content',$this->data)) {	//if ($this->data->has('content')) {
			$GLOBALS['contentCached'] += 1;
			return $this->data['content'];
		}

		//return $this->contentParser->toHtml($this->data['content_raw'],$this->data);
		$this->data['content'] = $this->contentParser->toHtml($this->data['content_raw'],$this->data);
		return $this->data['content'];
	}

	public function getExcerpt(int $length = 300) : string {
		$GLOBALS['excerpt'] += 1;
		if (array_key_exists('excerpt',$this->data)) {	//if ($this->data->has('excerpt')) {
			$GLOBALS['excerptCached'] += 1;
			return $this->data['excerpt'];
		}

		$content = $this->getContent();
		if (!$content) {
			return $content;
		}

		$excerpt = strip_tags($content);

		if (strlen($excerpt) > $length) {
			$excerptCut = substr($excerpt, 0, $length);
			$endPoint   = strrpos($excerptCut, ' ');
			$excerpt    = $endPoint ? substr($excerptCut, 0, $endPoint) : substr($excerptCut, 0);
			$excerpt    .= '&hellip;';
		}

		$this->data['excerpt'] = $excerpt;
		return $excerpt;
	}
}
