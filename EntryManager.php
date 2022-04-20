<?php declare(strict_types=1);

namespace Saaze;


class EntryManager {
	public bool $draft;
	protected Collection $collection;	// the EntryManager is for this collection only

	public array $entries = [];	// all entries for this collection

	public array $entriesSansIndex = [];	// all entries for this collection WITHOUT index.md, if any

	public function __construct(bool $draft = true) {
		$this->draft = $draft;
	}

	public function setCollection(Collection $collection) : void {
		$this->collection = $collection;
		//$this->entries    = [];	// clear entries
	}

	public function getEntries() : array|null {
		$this->entries = [];	// clear all entries in EntryManager
		$this->entriesSansIndex = [];
		$this->loadEntries();
		if (empty($this->entries)) return null;

		$this->sortEntries();	// sort entriesSansIndex

		//$this->entriesSansIndex = [];
		//foreach ($this->entries as $entry) {	// copy entries except those with 'index.md'
		//	if (substr($entry->filePath,-9) !== '/index.md')
		//		$this->entriesSansIndex[] = $entry;
		//}

		return $this->entries;
	}

	// Sort array entriesSansIndex
	protected function sortEntries() : void {
		if (empty($this->collection->data['sort_field'])) {
			return;
		}

		$field     = $this->collection->data['sort_field'] ?? 'title';
		$direction = strtolower( $this->collection->data['sort_direction'] ?? 'asc' );

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
		$collectionDir = \Saaze\Config::$H['global_path_content'] . '/' . $this->collection->slug;
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
		$entry = new Entry($filePath);	//container()->make(EntryInterface::class, ['filePath' => $filePath]);
		if (!isset($entry->data)) return null;	// only for class Router (=now Saaze.php)
		if ($this->draft == false && array_key_exists('draft',$entry->data)
		&& $entry->data['draft']) return null;
		$entry->setCollection($this->collection);

		$this->entries[$entry->slug()] = $entry;

		// Attempt to reduce massive number of calls to content()
		//$entry->getContent();	# initializes data[]
		$entry->getContentAndExcerpt();
		$entry->getUrl();	# must be computed after getContent()
		//$entry->getExcerpt();

		if (substr($entry->filePath,-9) !== '/index.md')
			$this->entriesSansIndex[] = $entry;

		return $entry;
	}

	public function paginateEntriesForTemplate(array $entries, int $page, int $perPage) : array {
		$totalEntries = count($entries);

		if ($page < 1) {
			$page = 1;
		}
		if ($perPage < 1) {
			$perPage = 1;
		}

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
			'prevUrl'      => $page != $prevPage ? $this->collection->data['index_route'] . "/page/{$prevPage}" : '',
			'nextUrl'      => $page != $nextPage ? $this->collection->data['index_route'] . "/page/{$nextPage}" : '',
			'perPage'      => $perPage,
			'totalEntries' => $totalEntries,
			'totalPages'   => $totalPages,
			'entries'      => $pageEntries,
		];
	}

}
