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
	public readonly bool $dbgPrt;
	public readonly string $dbgFile;

	public function __construct(string $saazePath) {
		define('SAAZE_PATH', $saazePath);
		$this->dbgPrt = false;
		$this->dbgFile = '/tmp/saaze.log';
		Config::init();
	}

	public function run() : bool {
		$this->collectionManager = new \Saaze\CollectionManager();
		$this->entryManager = new \Saaze\EntryManager();
		$this->templateManager = new \Saaze\TemplateManager($this->entryManager);
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

		// Below code required so that rbase works correctly in dynamic mode
		// Emulate what Hiawatha web-server does on its own
		if (substr($_SERVER["REQUEST_URI"],-1) !== '/') {
			header('Location: ' . $_SERVER['REQUEST_URI'] . '/'); // Redirect browser to same URL with slash added at end
			if ($this->dbgPrt) file_put_contents($this->dbgFile,"\nRedireccting: REQUEST_URI=|{$_SERVER['REQUEST_URI']}|, QUERY_STRING=|".($_SERVER['QUERY_STRING']??"(null)")."|\n",FILE_APPEND);
			return true;
		}

		// REQUEST_URI is the original URL typed by the end-user.
		// QUERY_STRING can be either empty, or is the string resulting from any rewriting-rules within the web-server.
		// QUERY_STRING usually lacks the leading '/', therefore added below.
		if ($this->dbgPrt) file_put_contents($this->dbgFile,"\nREQUEST_URI=|{$_SERVER['REQUEST_URI']}|, QUERY_STRING=|".($_SERVER['QUERY_STRING']??"(null)")."|\n",FILE_APPEND);
		//$request_uri = rtrim($_SERVER['REQUEST_URI'],'/');
		$query_string = isset($_SERVER['QUERY_STRING']) ? '/' . ltrim($_SERVER['QUERY_STRING'],'/') : null;
		$request_uri = rtrim($query_string ?? $_SERVER['REQUEST_URI'],'/');
		//$request_uri = '/' . ltrim($request_uri,'/');	// required for php -S 0:8000 case
		if ($this->dbgPrt) file_put_contents($this->dbgFile,"request_uri=|{$request_uri}|\n",FILE_APPEND);
		foreach ($this->collectionManager->getCollections() as $collection) {
			if (!isset($collection->data['entry_route'])) continue;
			if (($slugStart = strpos($collection->data['entry_route'],"/{slug}")) === false) continue;	// no correct entry_route in collection yaml-file
			$entryStart = substr($collection->data['entry_route'],0,$slugStart);
			$entryStartLen = strlen($entryStart);
			if ($this->dbgPrt) file_put_contents($this->dbgFile,"collection->slug=|{$collection->slug}|, ->data[entry_route]=|{$collection->data['entry_route']}|, entryStart=|{$entryStart}|\n",FILE_APPEND);
			$page = null;
			if (str_starts_with($request_uri,$entryStart)) {
				// Here we would have to add the uglyURL case, if needed
				$singleFile = \Saaze\Config::$H['global_path_content'] . '/' . $collection->slug . substr($request_uri,strlen($entryStart)) . '.md';
				if ($this->dbgPrt) file_put_contents($this->dbgFile,"collection->slug=|{$collection->slug}|, singleFile1=|{$singleFile}|\n",FILE_APPEND);
				$entry = new Entry($singleFile);
				if (isset($entry->data)) goto entryCase;
				// Special case for index.md
				$singleFile = \Saaze\Config::$H['global_path_content'] . '/' . $collection->slug . substr($request_uri,strlen($entryStart)) . '/index.md';
				if ($this->dbgPrt) file_put_contents($this->dbgFile,"collection->slug=|{$collection->slug}|, singleFile2=|{$singleFile}|\n",FILE_APPEND);
				$entry = new Entry($singleFile);
				if (!isset($entry->data)) goto indexCase;
				entryCase: // process entry case
				if ($this->dbgPrt) file_put_contents($this->dbgFile,"collection->slug=|{$collection->slug}|, 200\n",FILE_APPEND);
				$entry->setCollection($collection);
				$entry->getContentAndExcerpt();	//$entry->getContent();
				$entry->getUrl();
				echo $this->templateManager->renderEntry($entry);
				return true;
			}
			indexCase:	// process index case
			if (!array_key_exists('index_route',$collection->data)) continue;	// no index_route means no index
			$indexStart = rtrim($collection->data['index_route'],'/');
			$indexStartLen = strlen($indexStart);
			if ($this->dbgPrt) file_put_contents($this->dbgFile,"indexStart=|{$indexStart}|, indexStartLen={$indexStartLen},\n",FILE_APPEND);
			if ($request_uri === $indexStart
			|| (str_starts_with($request_uri,$indexStart.'/page/') && ctype_digit($page=substr($request_uri,$indexStartLen+6)))) {	// 6=strlen('/page/')
				if ($this->dbgPrt) file_put_contents($this->dbgFile,"collection->slug=|{$collection->slug}|, match=200: indexStart=|${indexStart}|\n",FILE_APPEND);
				$this->entryManager->setCollection($collection);
				//$this->entryManager->entries = [];	// clear all read entries in EntryManager
				$this->entryManager->getEntries();
				echo $this->templateManager->renderCollection($collection, $page ?? 1);
				return true;
			}
		}
		http_response_code(404); 
		if ($this->dbgPrt) file_put_contents($this->dbgFile,"Not found: 404\n",FILE_APPEND);
		echo $this->templateManager->renderError('Not found', 404);
		return true;
	}
}
