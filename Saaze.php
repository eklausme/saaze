<?php declare(strict_types=1);
/* This is only used in case of dynamic content.
   When generating static files, this is not needed or executed.

   For Hiawatha web-server use below config for URL rewriting:

UrlToolkit {
	ToolkitID = PHP_Routing
	RequestURI isfile Return
	Match ^(|/)$ Rewrite /index.php?/blog
	Match ^/(.+) Rewrite /index.php?$1
}

*/

namespace Saaze;


class Saaze {
	public CollectionManager $collectionManager;
	public EntryManager $entryManager;
	public TemplateManager $templateManager;

	public function __construct(string $saazePath) {
		define('SAAZE_PATH', $saazePath);
		Config::init();
	}

	public function run() : bool {
		$this->collectionManager = new \Saaze\CollectionManager();
		$this->entryManager = new \Saaze\EntryManager();
		$this->templateManager = new \Saaze\TemplateManager($this->entryManager);
		//$router = new \Saaze\Router($collectionManager,$entryManager,$templateManager);
		return $this->handle();
	}

	public function handle() : bool {
		// See https://www.php.net/manual/en/features.commandline.webserver.php
		// Not required if web-server handles static files directly.
		// For example, in Hiawatha config: RequestURI isfile Return
		// Also not required for PHP-internal web-server
		//if (preg_match('/\.(?:3gp|apk|avi|bmp|css|csv|doc|docx|flac|gif|gz|gzip|htm|html|ico|ics|jpe|jpeg|jpg|js|kml|kmz|m4a|mov|mp3|mp4|mpeg|mpg|odp|ods|odt|oga|ogg|ogv|pdf|pdf|png|pps|pptx|qt|svg|swf|tar|text|tif|txt|wav|webm|wmv|xls|xlsx|xml|xsl|xsd|zip)$/', $_SERVER["REQUEST_URI"])) {
		//	return false;    // serve the requested resource as-is from the surrounding web-server
		//}

		// Below code required so that rbase work correctly in dynamic mode
		// Emulate what Hiawatha web-server does on its own
		if (substr($_SERVER["REQUEST_URI"],-1) !== '/')
			header('Location: ' . $_SERVER['REQUEST_URI'] . '/'); // Redirect browser to same URL with slash added at end

		$request_uri = rtrim($_SERVER['REQUEST_URI'],'/');
		foreach ($this->collectionManager->getCollections() as $collection) {
			if (!isset($collection->data['entry_route'])) continue;
			if (($slugStart = strpos($collection->data['entry_route'],"/{slug}")) === false) continue;	// no correct entry_route in collection yaml-file
			$entryStart = substr($collection->data['entry_route'],0,$slugStart);
			$entryStartLen = strlen($entryStart);
			$page = null;
			if (str_starts_with($request_uri,$entryStart)) {
				$singleFile = \Saaze\Config::$H['global_path_content'] . '/' . $collection->slug . substr($request_uri,strlen($entryStart)) . '.md';
				$entry = new Entry($singleFile);
				if (isset($entry->data)) goto A;
				$singleFile = \Saaze\Config::$H['global_path_content'] . '/' . $collection->slug . substr($request_uri,strlen($entryStart)) . '/index.md';
				$entry = new Entry($singleFile);
				if (!isset($entry->data)) goto B;
				A: $entry->setCollection($collection);
				$entry->getContent();
				echo $this->templateManager->renderEntry($entry);
				return true;
			}
			B: if ($request_uri === $entryStart
			|| (str_starts_with($request_uri,$entryStart.'/page/') && ctype_digit($page=substr($request_uri,$entryStartLen+6)))) {
				$this->entryManager->setCollection($collection);
				//$this->entryManager->entries = [];	// clear all read entries in EntryManager
				$this->entryManager->getEntries();
				echo $this->templateManager->renderCollection($collection, $page ?? 1);
				return true;
			}
		}
		$msg = 'Not found';
		echo $this->templateManager->renderError($msg, 404);
		return true;
	}
}
