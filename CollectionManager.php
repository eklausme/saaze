<?php

namespace Saaze;


class CollectionManager {
	/**
	 * @var array
	 */
	protected $collections = [];

	/**
	 * @return array
	 */
	public function getCollections()
	{
		if (empty($this->collections)) {
			$this->loadCollections();
		}
		if (empty($this->collections)) {
			return $this->collections;
		}

		$this->sortCollections();

		return $this->collections;
	}

	protected function sortCollections()
	{
		uasort($this->collections, function ($a, $b) {
			return count(explode('/', $b->data['entry_route'])) <=> count(explode('/', $a->data['entry_route']));
		});
	}

	/**
	 * @param string $slug
	 * @return \Saaze\Interfaces\CollectionInterface|null
	 */
	public function getCollection($slug)
	{
		$this->getCollections();

		if (empty($this->collections[$slug])) {
			return null;
		}

		return $this->collections[$slug];
	}

	/**
	 * @return array
	 */
	protected function loadCollections() {	// search Yaml files in content directory
		foreach(scandir(\Saaze\Config::$H['global_path_content']) as $fn) {
			if (!is_dir($fn) && substr($fn,-4) === '.yml') {
				$this->loadCollection(\Saaze\Config::$H['global_path_content'] . DIRECTORY_SEPARATOR . $fn);
			}
		}

		return $this->collections;
	}

	/**
	 * @param string $filePath
	 * @return \Saaze\Collection|null
	 */
	protected function loadCollection($filePath)
	{
		if (!is_readable($filePath)) {	//file_exists()
			return null;
		}

		$collection = new Collection($filePath);	//container()->make(CollectionInterface::class, ['filePath' => $filePath]);

		$this->collections[$collection->slug] = $collection;

		return $collection;
	}

}
