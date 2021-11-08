<?php declare(strict_types=1);

namespace Saaze;


class BuildCommand {
	protected static string $defaultName = 'build';

	protected string $buildDest;	// needed for rbase computation

	protected CollectionManager $collectionManager;

	protected EntryManager $entryManager;

	protected TemplateManager $templateManager;


	public function __construct(CollectionManager $collectionManager, EntryManager $entryManager, TemplateManager $templateManager) {
		//parent::__construct();

		$this->collectionManager = $collectionManager;
		$this->entryManager      = $entryManager;
		$this->templateManager   = $templateManager;
	}

	public function buildAllStatic(string $dest) : void {	//execute(InputInterface $input, OutputInterface $output)
		$t0 = microtime(true);

		if (strpos($dest, '/') !== 0) {	// Does not start with '/'?
			$dest = \Saaze\Config::$H['global_path_base'] . "/{$dest}";
		}

		$this->buildDest = $dest;	// root directory for building

		echo("Building static site in {$dest}...\n");

		$this->clearBuildDirectory($dest);

		$collections     = $this->collectionManager->getCollections();
		$ncollections    = count($collections);	// for debugging
		$collectionCount = 0;
		$entryCount      = 0;

		foreach ($collections as $collection) {
			$this->entryManager->setCollection($collection);
			//$this->entryManager->entries = [];	// clear all read entries in EntryManager

			$entries    = $this->entryManager->getEntries();
			$nentries   = count($this->entryManager->entriesSansIndex);
			$totalPages = ceil($nentries / \Saaze\Config::$H['global_config_entries_per_page']);
			printf("\texecute(): filePath=%s, nentries=%d, totalPages=%d, entries_per_page=%d\n",$collection->filePath,$nentries,$totalPages,\Saaze\Config::$H['global_config_entries_per_page']);

			if ($this->buildCollectionIndex($collection, 0, $dest)) {
				$collectionCount++;
			}

			for ($page=1; $page <= $totalPages; $page++) {
				$this->buildCollectionIndex($collection, $page, $dest);
			}

			foreach ($entries as $entry) {
				$entry->setCollection($collection);

				if ($this->buildEntry($collection, $entry, $dest)) {
					$entryCount++;
				}
			}
		}

		$elapsedTime = microtime(true) - $t0;
		$timeString  = number_format($elapsedTime, 2) . ' secs';
		$memString   = $this->humanSize(memory_get_peak_usage());

		echo("Finished creating {$collectionCount} collections and {$entryCount} entries ({$timeString} / {$memString})\n");
		printf("#collections=%d, YamlParser=%.4f/%d-%d, md2html=%.4f, MathParser=%.4f/%d, renderEntry=%d, content=%d/%d, excerpt=%d/%d\n",
			$ncollections,
			$GLOBALS['YamlParser'], $GLOBALS['YamlParserNcall'], $GLOBALS['parseCollectionNcall'],
			$GLOBALS['md2html'],
			$GLOBALS['MathParser'], $GLOBALS['MathParserNcall'],
			$GLOBALS['renderEntry'],
			$GLOBALS['content'], $GLOBALS['contentCached'],
			$GLOBALS['excerpt'], $GLOBALS['excerptCached']);
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
		$entry = new Entry($singleFile);
		$entry->setCollection($collection);
		$entry->getUrl();
		$entry->getContent();
		if (!$this->buildEntry($collection, $entry, $dest))
			exit("Cannot create entry\n");
		if ($extractFile) {
			$entry->getExcerpt();
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
		printf("YamlParser=%.4f/%d-%d, md2html=%.4f, MathParser=%.4f/%d, renderEntry=%d, content=%d/%d, excerpt=%d/%d\n",
			$GLOBALS['YamlParser'], $GLOBALS['YamlParserNcall'], $GLOBALS['parseCollectionNcall'],
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
		if (!$collection->data['index_route']) {
			return false;
		}

		$collectionDir = $dest;

		if ($collection->data['index_route'] !== '/') {
			$collectionDir = "{$dest}/" . ltrim($collection->data['index_route'], '/');
		}

		$collectionDir = rtrim($collectionDir, '/');

		if ($page != 0) {
			$collectionDir .= "/page/{$page}";
		}

		if (!is_dir($collectionDir)) {
			mkdir($collectionDir, 0777, true);
		}
		$collectionDir .= "/index.html";
		$GLOBALS['fileToRender'] = $collectionDir;
		$GLOBALS['rbase'] = $this->compRbase($collectionDir,$this->buildDest);
		file_put_contents($collectionDir, $this->templateManager->renderCollection($collection, $page));

		return true;
	}

	private function buildEntry(Collection $collection, Entry $entry, string $dest) : bool {
		if (!$collection->data['entry_route']) {
			return false;
		}

		$entryDir = "{$dest}/" . ltrim($collection->data['entry_route'], '/');
		$entryDir = str_replace('{slug}', $entry->slug(), $entryDir);

		// does not work for 2019/08-02-oracle-deadlock-when-using-bitmap-index.md
		//if (substr_compare($entry->slug(), 'index', -strlen('index')) === 0) {
		//    $entryDir = preg_replace('/index$/', '', $entryDir);
		//}
		if (substr($entry->filePath,-9) === '/index.md') {	// 9=strlen('/index.md')
			$entryDir = substr($entryDir,0,strlen($entryDir)-5);	// drop 'index' string, 5=strlen('index')
		}

		$entryDir = rtrim($entryDir, '/');

		if (!is_dir($entryDir)) {
			mkdir($entryDir, 0777, true);
		}
		$entryDir .= "/index.html";
		$GLOBALS['fileToRender'] = $entryDir;
		$GLOBALS['rbase'] = $this->compRbase($entryDir,$this->buildDest);
		file_put_contents($entryDir, $this->templateManager->renderEntry($entry));

		return true;
	}

	private function humanSize(int $bytes) : string {
		$i = floor(log($bytes, 1024));
		return round($bytes / pow(1024, $i), [0,0,2,2,3][$i]).['B','kB','MB','GB','TB'][$i];
	}
}
