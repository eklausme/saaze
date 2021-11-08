<?php declare(strict_types=1);

namespace Saaze;


class Collection {
	public string $filePath;

	public array $data;

	public string $slug;	// calculated once and once only in constructor, also called ID in Saaze documentation

	public function __construct(string $filePath) {	//, CollectionParserInterface $collectionParser)
		$this->filePath = $filePath;
		$this->slug = basename($this->filePath, '.yml');

		//$this->data = new Dot($collectionParser->parseCollection($this->filePath));
		$this->data = $this->parseCollection($this->filePath);	//new Dot( $this->parseCollection($this->filePath) );
	}

	public function parseCollection(string $filePath) : array {
		//return Yaml::parse(file_get_contents($filePath));
		$GLOBALS['YamlParserNcall'] += 1;
		$GLOBALS['parseCollectionNcall'] += 1;
		return yaml_parse(file_get_contents($filePath));
	}
}
