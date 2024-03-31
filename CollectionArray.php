<?php declare(strict_types=1);

namespace Saaze;


class CollectionArray {
	protected array $collections = [];
	public bool $draftOverride;

	public function __construct(bool $draft = false) {
		$this->draftOverride = $draft;
	}

	public function getCollections() : array {
		if (empty($this->collections)) $this->loadCollections();
		if (empty($this->collections)) return $this->collections;
		$this->sortCollections();
		return $this->collections;
	}

	protected function sortCollections() : void {	// sort collections[] descending on 'entry_route', if available
		uasort($this->collections, function ($a, $b) {
			//return count(explode('/', $b->data['entry_route'])) <=> count(explode('/', $a->data['entry_route']));
			return ($b->data['entry_route'] ?? NULL) <=> ($a->data['entry_route'] ?? NULL);
		});
	}

	protected function loadCollections() : array {	// search Yaml files in content directory
		$dir = \Saaze\Config::$H['global_path_content'];
		foreach(scandir($dir) as $fn) {
			$fn = $dir . DIRECTORY_SEPARATOR . $fn;
			if (!is_dir($fn) && substr($fn,-4) === '.yml' && is_readable($fn)) {
				$collection = new Collection($fn,$this->draftOverride);
				$this->collections[$collection->slug] = $collection;
			}
		}

		return $this->collections;
	}

}
