<?php

namespace Saaze;


class EntryManager {
	/**
	 * @var CollectionInterface
	 */
	protected $collection;	// the EntryManager is for this collection only

	/**
	 * @var array
	 */
	public $entries = [];	// all entries for this collection

	/**
	 * @var array
	 */
	public $entriesSansIndex = [];	// all entries for this collection WITHOUT index.md, if any

	/**
	 * @param \Saaze\Interfaces\CollectionInterface $collection
	 * @return void
	 */
	public function setCollection(Collection $collection) {
		$this->collection = $collection;
		//$this->entries    = [];	// clear entries
	}

	/**
	 * @return array
	 */
	public function getEntries() {
		//if (empty($this->entries)) {
		//	$this->loadEntries();
		//}
		//if (empty($this->entries)) {
		//	return $this->entries;
		//}
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
	protected function sortEntries() {
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

	/**
	 * @param string $slug
	 * @return EntryInterface|null
	 */
	//protected function getEntry($slug) {	// only used in class Router
	//	$collectionDir = \Saaze\Config::$H['global_path_content'] . '/' . $this->collection->slug;
	//	if (!is_dir($collectionDir)) {
	//		return null;
	//	}

	//	if (empty($this->entries[$slug])) {
	//		$entryPath = $collectionDir . "/{$slug}.md";
	//		$entry = $this->loadEntry($entryPath);

	//		if (!$entry) {
	//			$entryPath = $collectionDir . "/{$slug}/index.md";
	//			$entry = $this->loadEntry($entryPath);
	//		}

	//		if ($entry) {
	//			$this->entries[$slug] = $entry;
	//		}
	//	}

	//	return $this->entries[$slug] ?? null;
	//}

	/**
	 * @return array
	 */
	protected function loadEntries()
	{
		$collectionDir = \Saaze\Config::$H['global_path_content'] . '/' . $this->collection->slug;
		if (!is_dir($collectionDir)) {
			return [];
		}

		$this->loadMkdwnRecursive($collectionDir);

		return $this->entries;
	}

	protected function loadMkdwnRecursive($dir) {	// recursively load Markdown files: *.md
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

	/**
	 * @param string $filePath
	 * @return EntryInterface|null
	 */
	protected function loadEntry($filePath) {
		//if (!file_exists($filePath)) {	// this case cannot occur, as loadEntry() is called after scandir(), but it can occur when called from class Router
		//    return null;
		//}

		$entry = new Entry($filePath);	//container()->make(EntryInterface::class, ['filePath' => $filePath]);
		if (!isset($entry->data)) return null;	// only for class Router
		$entry->setCollection($this->collection);

		$this->entries[$entry->slug()] = $entry;

		// Attempt to reduce massive number of calls to content()
		$entry->getUrl();
		$entry->getContent();
		$entry->getExcerpt();

		if (substr($entry->filePath,-9) !== '/index.md')
				$this->entriesSansIndex[] = $entry;

		return $entry;
	}

	/**
	 * @return array
	 */
	//private function getEntriesForTemplate() {	// no longer used
	//	$entries = $this->getEntries();

		// Quite expensive according XHProf
		//$entries = array_map(function ($entry) {
		//	return $this->getEntryForTemplate($entry);
		//}, $entries);

	//	return $entries;
	//}

	/**
	 * @param array $entries
	 * @param int $page
	 * @param int $perPage
	 * @return array
	 */
	public function paginateEntriesForTemplate($entries, $page, $perPage)
	{
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
