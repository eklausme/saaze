<?php declare(strict_types=1);

namespace Saaze;


class Collection {
	public string $filePath;
	public array $data;
	public string $slug;	// calculated once and once only in constructor, also called ID in Saaze documentation
	public bool $draftOverride;
	public array $entries = [];	// all entries for this collection
	public array $entriesSansIndex = [];	// all entries for this collection WITHOUT index.md, if any


	public function __construct(string $filePath, bool $draft = true) {
		$this->filePath = $filePath;
		$this->slug = basename($this->filePath, '.yml');	// used to find corresponding template
		$this->data = $this->parseCollection($this->filePath);
		$this->draftOverride = $draft;
	}

	public function parseCollection(string $filePath) : array {
		//return Yaml::parse(file_get_contents($filePath));
		$GLOBALS['YamlParserNcall'] += 1;
		$GLOBALS['parseCollectionNcall'] += 1;
		if (($yaml = file_get_contents($filePath)) === false) {
			// From CollectionArray::loadCollections() we know that file exists, but might be unreadable
			printf("Cannot read %s\n",$filePath);
			exit(4);
		}
		if (($yaml = yaml_parse($yaml)) === false)
			fprintf(STDERR,"%s: YAML could not be parsed in parseCollection()\n",$filePath);
		return $yaml;
	}

	public function getEntries() : array|null {
		$this->entries = [];	// clear all entries in EntryArray
		$this->entriesSansIndex = [];
		$this->loadEntries();
		if (empty($this->entries)) return null;

		$this->sortEntries();	// sort entriesSansIndex
		return $this->entries;
	}

	// Sort array entriesSansIndex
	protected function sortEntries() : void {
		if (empty($this->data['sort_field'])) return;

		$field     = $this->data['sort_field'] ?? 'title';
		$direction = (strtolower( $this->data['sort_direction'] ?? 'asc' ) === 'asc');

		// Unfortunately we cannot use sort() and rsort() as we sort according a special slice in 'entries'-hash
		usort($this->entriesSansIndex, function ($a, $b) use ($field, $direction) {
			$aData = ($a->data['pinned'] ?? false) ? $a->data[$field] : "\t" . $a->data[$field];
			$bData = ($b->data['pinned'] ?? false) ? $b->data[$field] : "\t" . $b->data[$field];
			return $direction ? ($aData <=> $bData) : ($bData <=> $aData);
		});
	}

	protected function loadEntries() : array {
		$collectionDir = \Saaze\Config::$H['global_path_content'] . '/' . $this->slug;
		if (!is_dir($collectionDir)) return [];

		$this->loadMkdwnRecursive($collectionDir);

		return $this->entries;
	}

	protected function loadMkdwnRecursive(string $dir) : void {	// recursively load Markdown files: *.md
		foreach (scandir($dir) as $fn) {
		    if ($fn === '.' || $fn === '..') continue;
		    $fn = $dir . DIRECTORY_SEPARATOR . $fn;
			if (is_dir($fn)) $this->loadMkdwnRecursive($fn);
			else if (substr($fn,-3) === '.md') {
				//printf("\t\t%s\n",$fn);
				$this->loadEntry($fn);
			}
		}
	}

	protected function loadEntry(string $filePath) : void {
		$entry = new Entry($filePath,$this);
		if (!isset($entry->data)) return;
		if ($this->draftOverride === false  &&  ($entry->data['draft'] ?? false))
			return;

		$this->entries[$entry->slug()] = $entry;

		$entry->getContentAndExcerpt();
		$entry->getUrl();	# must be computed after getContentAndExcerpt()
		//$entry->getExcerpt();

		if (substr($entry->filePath,-9) !== '/index.md' && ($entry->data['index'] ?? true))
			$this->entriesSansIndex[] = $entry;
	}

	public function paginateEntriesForTemplate(array $entries, int $page, int $perPage) : array {
		$totalEntries = count($entries);	// when called from renderCollection(), this is entriesSansIndex[]

		if ($page < 1) $page = 1;
		if ($perPage < 1) $perPage = 1;

		$totalPages = ceil($totalEntries / $perPage);
		$prevPage   = $page > 1 ? $page - 1 : $page;
		$nextPage   = $page < $totalPages ? $page + 1 : $totalPages;

		$pageEntries    = [];
		$pageIndex      = $page - 1;
		$chunkedEntries = array_chunk($entries, $perPage);
		if (isset($chunkedEntries[$pageIndex])) {
			$pageEntries = $chunkedEntries[$pageIndex];
		}

		return [
			'currentPage'  => $page,
			'prevPage'     => $prevPage,
			'nextPage'     => $nextPage,
			'prevUrl'      => $page != $prevPage ? $this->data['index_route'] . "/page/{$prevPage}" : '',
			'nextUrl'      => $page != $nextPage ? $this->data['index_route'] . "/page/{$nextPage}" : '',
			'perPage'      => $perPage,
			'totalEntries' => $totalEntries,
			'totalPages'   => $totalPages,
			'entries'      => $pageEntries,
		];
	}

}
