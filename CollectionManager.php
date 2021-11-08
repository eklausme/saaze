<?php declare(strict_types=1);

namespace Saaze;


class CollectionManager {
	protected array $collections = [];

	public function getCollections() : array {
		if (empty($this->collections)) {
			$this->loadCollections();
		}
		if (empty($this->collections)) {
			return $this->collections;
		}

		$this->sortCollections();

		return $this->collections;
	}

	protected function sortCollections() : void {
		uasort($this->collections, function ($a, $b) {
			return count(explode('/', $b->data['entry_route'])) <=> count(explode('/', $a->data['entry_route']));
		});
	}

	public function getCollection(string $slug) : Collection|null {
		$this->getCollections();

		if (empty($this->collections[$slug])) {
			return null;
		}

		return $this->collections[$slug];
	}

	protected function loadCollections() : array {	// search Yaml files in content directory
		foreach(scandir(\Saaze\Config::$H['global_path_content']) as $fn) {
			if (!is_dir($fn) && substr($fn,-4) === '.yml') {
				$this->loadCollection(\Saaze\Config::$H['global_path_content'] . DIRECTORY_SEPARATOR . $fn);
			}
		}

		return $this->collections;
	}

	protected function loadCollection(string $filePath) : Collection|null {
		if (!is_readable($filePath)) {	//file_exists()
			return null;
		}

		$collection = new Collection($filePath);	//container()->make(CollectionInterface::class, ['filePath' => $filePath]);

		$this->collections[$collection->slug] = $collection;

		return $collection;
	}

}
