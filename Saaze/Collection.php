<?php

namespace Saaze;


class Collection {
	/**
	 * @var string
	 */
	public $filePath;

	/**
	 * mixed
	 * no longer: @var Dot
	 */
	public $data;

	/**
	 * @var string
	 */
	public $slug;	// calculated once and once only in constructor, also called ID in Saaze documentation

	/**
	 * @param string $filePath
	 */
	public function __construct($filePath) {	//, CollectionParserInterface $collectionParser)
		$this->filePath = $filePath;
		$this->slug = basename($this->filePath, '.yml');

		//$this->data = new Dot($collectionParser->parseCollection($this->filePath));
		$this->data = $this->parseCollection($this->filePath);	//new Dot( $this->parseCollection($this->filePath) );
	}

	/**
	 * @param string $filePath
	 * @return array
	 */
	public function parseCollection($filePath)
	{
		//return Yaml::parse(file_get_contents($filePath));
		$GLOBALS['YamlParserNcall'] += 1;
		$GLOBALS['parseCollectionNcall'] += 1;
		return yaml_parse(file_get_contents($filePath));
	}


	/**
	 * @return bool
	 */
	//protected function indexIsEntry() {
	//	return $this->findIndexMkdwn( \Saaze\Config::$H['global_path_content'] . '/' . $this->slug );
	//}

	/**
	 * @return bool
	 */
	//protected function findIndexMkdwn($dir) {	// recursive search for "index.md"
	//	foreach(scandir($dir) as $fn) {
	//		if ($fn === '.' || $fn === '..') continue;
	//		$fn2 = $dir . DIRECTORY_SEPARATOR . $fn;
	//		if (is_dir($fn2) && $this->findIndexMkdwn($fn2)) return true;
	//		else if ($fn === "index.md") return true;
	//	}
	//	return false;
	//}
}
