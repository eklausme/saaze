<?php declare(strict_types=1);

namespace Saaze;


class BuildCommand {
	protected static string $defaultName = 'build';
	protected string $buildDest;	// needed for rbase computation
	protected CollectionArray $collectionArray;
	protected TemplateManager $templateManager;
	private array $cat_and_tag;	// array of array for categories and tags
	private int $pid;	// process id from pcntl_fork()
	private int $procnr;	// serial process number
	private int $nprocs;	// number of processes, 1=parent only, 2=parent+child, 3=parent+2children, ...


	public function __construct(CollectionArray $collectionArray, TemplateManager $templateManager) {
		$this->collectionArray = $collectionArray;
		$this->templateManager = $templateManager;
		$this->cat_and_tag = [ 'categories' => array(), 'tags' => array() ];
	}

	public function buildAllStatic(string $dest, bool $tags, bool $rssXmlFeed, bool $sitemap, bool $overview, int $aprocs) : void {
		$t0 = microtime(true);

		if (strpos($dest, '/') !== 0)	// Does not start with '/'?
			$dest = \Saaze\Config::$H['global_path_base'] . "/{$dest}";

		$this->buildDest = $dest;	// root directory for building

		echo("Building static site in {$dest}...\n");

		$this->clearBuildDirectory($dest);

		$collections     = $this->collectionArray->getCollections();
		$ncollections    = count($collections);	// for debugging
		$collectionCount = 0;
		$totalCollection = 0;
		$entryCount      = 0;

		foreach ($collections as $collection) {
			$entries    = $collection->getEntries();	# finally calls getContentAndExcerpt() and sorts
			$nentries   = count($entries);
			$nSIentries = count($collection->entriesSansIndex);
			$entries_per_page = $collection->data['entries_per_page'] ?? \Saaze\Config::$H['global_config_entries_per_page'];
			$totalPages = ceil($nSIentries / $entries_per_page);
			printf("\texecute(): filePath=%s, nSIentries=%d, totalPages=%d, entries_per_page=%d\n",$collection->filePath,$nSIentries,$totalPages,$entries_per_page);

			$this->beginParallel($nentries,$aprocs);
			$i = 0;
			foreach ($entries as $entry) {
				if ($this->nprocs > 0  &&  ($i++ % $this->nprocs) != $this->procnr) continue;	// distribute work among nprocs processes
				if ($entry->data['entry'] ?? true) {
					$this->buildEntry($collection, $entry, $dest);
					$entryCount++;
				}
			}
			$this->endParallel();

			if ($tags) {	// populate cat_and_tag[][] array
				foreach ($entries as $entry) {
					if ($entry->data['entry'] ?? true)
						$this->build_cat_and_tag($entry,$collection->draftOverride);
				}
			}

			++$totalCollection;
			if ($this->buildCollectionIndex($collection, 0, $dest)) $collectionCount++;

			for ($page=1; $page <= $totalPages; $page++)
				$this->buildCollectionIndex($collection, $page, $dest);
		}
		if ($tags) $this->save_cat_and_tag();
		if ($rssXmlFeed)
			file_put_contents($dest. DIRECTORY_SEPARATOR . 'feed.xml', $this->templateManager->renderGeneral($collections,'rss'));
		if ($sitemap)
			file_put_contents($dest. DIRECTORY_SEPARATOR . 'sitemap.xml', $this->templateManager->renderGeneral($collections,'sitemap'));
		if ($overview)
			file_put_contents($dest. DIRECTORY_SEPARATOR . 'sitemap.html', $this->templateManager->renderGeneral($collections,'overview'));

		$elapsedTime = microtime(true) - $t0;
		$timeString  = number_format($elapsedTime, 2) . ' secs';
		$memString   = $this->humanSize(memory_get_peak_usage());

		echo("Finished creating {$totalCollection} collections, {$collectionCount} with index, and {$entryCount} entries ({$timeString} / {$memString})\n");
		printf("#collections=%d, parseEntry=%.4f/%d-%d, md2html=%.4f, MathParser=%.4f/%d, renderEntry=%.4f/%d, renderCollection=%.4f/%d, content=%d/%d, excerpt=%d/%d\n",
			$ncollections,
			$GLOBALS['parseEntry'], $GLOBALS['parseEntryNcall'], $GLOBALS['parseCollectionNcall'],
			$GLOBALS['md2html'],
			$GLOBALS['MathParser'], $GLOBALS['MathParserNcall'],
			$GLOBALS['renderEntry'], $GLOBALS['renderEntryNcall'],
			$GLOBALS['renderCollection'], $GLOBALS['renderCollectionNcall'],
			$GLOBALS['content'], $GLOBALS['contentCached'],
			$GLOBALS['excerpt'], $GLOBALS['excerptCached']);
	}

	protected function beginParallel(int $nentries, int $aprocs) : void {
		$this->pid = 0;
		$this->procnr = 0;
		$this->nprocs = 1;
		if ($nentries < 128) return;	// too few entries to warrant forking
		$this->nprocs = $aprocs;	// aprocs = allowed procs, specified on commmand-line
		for ($this->procnr=0; $this->procnr<$this->nprocs; ++$this->procnr) {
			if (($this->pid = pcntl_fork())) {
				printf("\tnentries=%d, procnr=%02d, pid=%d\n",$nentries,$this->procnr,$this->pid);
				return;	// child returns to work
			}
		}
	}
	protected function endParallel() : void {
		if ($this->pid) exit(0);	// exit child process; pid=0 is parent
		//$status = 0;
		//while (pcntl_wait($status) > 0)
		//	;
	}

	// Build HTML and excerpt for one single Markdown file given on the command-line
	public function buildSingleStatic(string $dest, string $singleFile, int $extractFile) : void {
		$t0 = microtime(true);

		if (strpos($dest, '/') !== 0) {	// Does not start with '/'?
			$dest = \Saaze\Config::$H['global_path_base'] . "/{$dest}";
		}

		if (strpos($singleFile,'/') !== 0)	{	// relative path given, assume it is starting from SAAZE_PATH = $H['global_path_base']
			$singleFile = \Saaze\Config::$H['global_path_base'] . "/" . $singleFile;
		}
		// Find out collection-id in question
		$len = strlen(\Saaze\Config::$H['global_path_content']);
		if ( substr($singleFile,0,$len) !== \Saaze\Config::$H['global_path_content'] )
			exit("{$singleFile} not in " . \Saaze\Config::$H['global_path_content'] . "\n");
		$collectionId = substr($singleFile,$len);	// rest after content-path
		$collectionId = ltrim($collectionId,'/');	// strip leading slashes
		if (strlen($collectionId) <= 0)
			exit("{$singleFile} has no directory after content/\n");
		$collen = strpos($collectionId,'/');
		if ($collen !== false)
			$collectionId = substr($collectionId,0,$collen);

		$this->buildDest = $dest;	// root directory for building

		echo("Building single static site in {$dest} for {$collectionId} ...\n");

		$collection = new Collection(\Saaze\Config::$H['global_path_content'] . "/" . $collectionId . ".yml");
		$entry = new Entry($singleFile,$collection);
		$entry->getContentAndExcerpt();
		$entry->getUrl();	# must be computed after getContentAndExcerpt()
		if (!$this->buildEntry($collection, $entry, $dest))
			exit("Cannot create entry\n");
		if ($extractFile) {	// Idea: excerpt is merged into index either manually or via script
			//$entry->getExcerpt();
			file_put_contents("excerpt.txt",sprintf("title:\t<a href=\"%s%s>%s</a>\ndate:\t%s\n\n%s\n",
				$GLOBALS['rbase'], $entry->data['url'], $entry->data['title'],
				date('jS F Y', strtotime($entry->data['date'])),
				$entry->data['excerpt']
			));
		}

		$elapsedTime = microtime(true) - $t0;
		$timeString  = number_format($elapsedTime, 2) . ' secs';
		$memString   = $this->humanSize(memory_get_peak_usage());

		echo("Finished creating entry ({$timeString} / {$memString})\n");
		printf("parseEntry=%.4f/%d-%d, md2html=%.4f, MathParser=%.4f/%d, renderEntry=%d, content=%d/%d, excerpt=%d/%d\n",
			$GLOBALS['parseEntry'], $GLOBALS['parseEntryNcall'], $GLOBALS['parseCollectionNcall'],
			$GLOBALS['md2html'],
			$GLOBALS['MathParser'], $GLOBALS['MathParserNcall'],
			$GLOBALS['renderEntry'],
			$GLOBALS['content'], $GLOBALS['contentCached'],
			$GLOBALS['excerpt'], $GLOBALS['excerptCached']);
	}

	private function clearBuildDirectory(string $dest) : void {
		if (is_dir($dest)) $this->delTree($dest);
	}

	// From: https://www.php.net/manual/en/function.rmdir.php by nbari@dalmp.com
	private function delTree(string $dir) : bool {
		foreach (array_diff(scandir($dir), array('.','..')) as $fn) {
			$fn = $dir . DIRECTORY_SEPARATOR . $fn;
			if (is_link($fn)) continue;	// do not remove symbolic links as they might point to somewhere outside the directory
			(is_dir($fn)) ? $this->delTree($fn) : unlink($fn);
		}
		return rmdir($dir);
	}

	private function compRbase(string $full, string $dest) : string {
		if (strpos($full,$dest) != 0) return "";	// this is an error
		$cnt = substr_count(substr($full,strlen($dest)),"/") - 1;	// count slashes in overlapping part of $full
		if ($cnt <= 0) return ".";
		return rtrim(str_repeat("../",$cnt),"/");
	}

	private function buildCollectionIndex(Collection $collection, int $page, string $dest) : bool {
		if (!array_key_exists('index_route',$collection->data))	// no index_route means no index
			return false;

		$collectionDir = $dest;

		if ($collection->data['index_route'] !== '/')
			$collectionDir = "{$dest}/" . ltrim($collection->data['index_route'], '/');

		$collectionDir = rtrim($collectionDir, '/');

		if ($page != 0) $collectionDir .= "/page/{$page}";

		if (!is_dir($collectionDir)) mkdir($collectionDir, 0777, true);
		$collectionDir .= "/index.html";
		$GLOBALS['fileToRender'] = $collectionDir;
		$GLOBALS['rbase'] = $this->compRbase($collectionDir,$this->buildDest);
		file_put_contents($collectionDir, $this->templateManager->renderCollection($collection, $page));

		return true;
	}

	private function buildEntry(Collection $collection, Entry $entry, string $dest) : void {
		if (!$collection->data['entry_route']) {
			fprintf(STDERR,"%s: buildEntry() found no 'entry_route' in data[]\n",$collection->filePath);
			return;
		}

		$indexSpecial = 0;
		$entryDir = $dest . DIRECTORY_SEPARATOR . ltrim($collection->data['entry_route'], '/');
		$entryDir = str_replace('{slug}', $entry->slug(), $entryDir);

		if (substr($entry->filePath,-9) === DIRECTORY_SEPARATOR . 'index.md') {	// 9=strlen('/index.md')
			$entryDir = substr($entryDir,0,strlen($entryDir)-5);	// drop 'index' string, 5=strlen('index')
			$indexSpecial = 1;
		}

		$entryDir = rtrim($entryDir, '/');

		if (!$indexSpecial && isset($collection->data['uglyURL'])) {
			$lastSlash = strrpos($entryDir,'/');
			if ($lastSlash !== false) {
				$lastDir = substr($entryDir,0,$lastSlash);
				if (!is_dir($lastDir)) mkdir($lastDir, 0777, true);
			}
			$entryDir .= '.html';	// ugly entries are {slug}.html
		} else {	// non-ugly entries are in {slug}/index.html
			if (!is_dir($entryDir)) mkdir($entryDir, 0777, true);
			$entryDir .= DIRECTORY_SEPARATOR . 'index.html';
		}
		$GLOBALS['fileToRender'] = $entryDir;
		$GLOBALS['rbase'] = $this->compRbase($entryDir,$this->buildDest);
		if (file_put_contents($entryDir, $this->templateManager->renderEntry($entry)) === false)
			fprintf(STDERR,"%s: buildEntry() could not write to\n",$entryDir);
	}

	private function humanSize(int $bytes) : string {
		$i = floor(log($bytes, 1024));
		return round($bytes / pow(1024, $i), [0,0,2,2,3][$i]).['B','kB','MB','GB','TB'][$i];
	}

	private function build_cat_and_tag(Entry $entry, bool $draftOverride) : void {
		if ($draftOverride === false && array_key_exists('draft',$entry->data) && $entry->data['draft']) return;
		$prefix = '../..';
		foreach (array('categories','tags') as $i) {
			if (!array_key_exists($i,$entry->data)) continue;
			foreach ($entry->data[$i] as $k) {
				//$date = substr($entry->data['date'],0,10);	// only yyyy-mm-dd
				//$url = "<a href=\"{$prefix}{$entry->data['url']}\">{$date}: {$entry->data['title']}</a>";
				if (array_key_exists($k,$this->cat_and_tag[$i]))
					array_push($this->cat_and_tag[$i][$k],array($entry->data['url'],$entry->data['date'],$entry->data['title']));	// push to existing
				else
					$this->cat_and_tag[$i][$k] = [ array($entry->data['url'],$entry->data['date'],$entry->data['title']) ];	// create single element list
			}
		}
	}

	private function save_cat_and_tag() : void {
		foreach (array('categories','tags') as $i) {
			if (!array_key_exists($i,$this->cat_and_tag)) continue;
			ksort($this->cat_and_tag[$i]);	// sort keys
			foreach ($this->cat_and_tag[$i] as $k) sort($k);	// sort values, i.e., pushed values in list
		}
		$fname = \Saaze\Config::$H['global_path_content'] . DIRECTORY_SEPARATOR . 'cat_and_tag.json';
		file_put_contents($fname, json_encode($this->cat_and_tag,JSON_PRETTY_PRINT));
	}
}
