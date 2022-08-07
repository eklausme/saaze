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
		return yaml_parse(file_get_contents($filePath));
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
		if (empty($this->data['sort_field'])) {
			return;
		}

		$field     = $this->data['sort_field'] ?? 'title';
		$direction = strtolower( $this->data['sort_direction'] ?? 'asc' );

		// Unfortunately we cannot use sort() and rsort() as we sort according a special slice in 'entries'-hash
		usort($this->entriesSansIndex, function ($a, $b) use ($field, $direction) {
			$aData = $a->data[$field];
			$bData = $b->data[$field];

			if ($direction === 'asc') {
				return $aData <=> $bData;
			}

			return $bData <=> $aData;
		});
	}

	protected function loadEntries() : array {
		$collectionDir = \Saaze\Config::$H['global_path_content'] . '/' . $this->slug;
		if (!is_dir($collectionDir)) {
			return [];
		}

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

	protected function loadEntry(string $filePath) : Entry|null {
		$entry = new Entry($filePath);
		if (!isset($entry->data)) return null;	// relevant for Saaze.php
		if ($this->draftOverride == false  &&  array_key_exists('draft',$entry->data)
		&& $entry->data['draft']) return null;
		$entry->setCollection($this);

		$this->entries[$entry->slug()] = $entry;

		$entry->getContentAndExcerpt();
		$entry->getUrl();	# must be computed after getContent()
		//$entry->getExcerpt();

		if (substr($entry->filePath,-9) !== '/index.md')
			$this->entriesSansIndex[] = $entry;

		return $entry;
	}

	public function paginateEntriesForTemplate(array $entries, int $page, int $perPage) : array {
		$totalEntries = count($entries);

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
