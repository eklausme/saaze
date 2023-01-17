<?php declare(strict_types=1);

namespace Saaze;


class SaazeCli {
	public function __construct(string $saazePath) {
		define('SAAZE_PATH', $saazePath);
		Config::init();
	}

	protected function startXhprof() {
		xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
	}
	protected function stopXhprof() {
		$xhprof_data = xhprof_disable();

		//
		// Saving the XHProf run
		// using the default implementation of iXHProfRuns.
		//
		include_once "/usr/share/webapps/xhprof/xhprof_lib/utils/xhprof_lib.php";
		include_once "/usr/share/webapps/xhprof/xhprof_lib/utils/xhprof_runs.php";

		$xhprof_runs = new \XHProfRuns_Default();

		// Save the run under a namespace "xhprof_foo".
		//
		// **NOTE**:
		// By default save_run() will automatically generate a unique
		// run id for you. [You can override that behavior by passing
		// a run id (optional arg) to the save_run() method instead.]
		//
		$run_id = $xhprof_runs->save_run($xhprof_data, "saaze");

		echo "---------------\n".
			"Assuming you have set up the http based UI for\n".
			"XHProf at some address, you can view run at\n".
			"http://<xhprof-ui-address>/index.php?run=$run_id&source=saaze\n".
			"---------------\n";
	}

	public function run() : void {
		//$this->startXhprof();
		$buildDest = 'build';
		$singleFile = null;
		$extractFile = 0;
		$draft = false;	// =false: do not show drafts, =true: show drafts
		$tags = false;
		$sitemap = false;
		$rssXmlFeed = false;

		$options = getopt("b:efhmrs:tv");
		//var_dump($options);
		if (count($options) > 0) {
			if (isset($options['b']) && strlen($options['b']) > 0 && $options['b'] !== "/") {
				$buildDest = $options['b'];
			}
			if (isset($options['e'])) $extractFile = 1;
			if (isset($options['f'])) $draft = true;
			if (isset($options['h'])) {
				printf("Simplified Saaze, a static site generator\n"
					."\t-b <buildDir> specify build directory, e.g., /tmp/build\n"
					."\t-e            generate extract file for single file (only with -s)\n"
					."\t-f            include draft posts when generating static content\n"
					."\t-h            this help message\n"
					."\t-m            generate sitemap\n"
					."\t-r            generate RSS feed\n"
					."\t-s <file>     only generate static content for single file\n"
					."\t-t            generate categories and tags\n"
					."\t-v            version information\n");
				return;
			}
			if (isset($options['m'])) $sitemap = true;
			if (isset($options['r'])) $rssXmlFeed = true;
			if (isset($options['s']) && strlen($options['s']) > 0 && $options['s'] !== "/") {
				$singleFile = $options['s'];
			}
			if (isset($options['t'])) $tags = true;
			if (isset($options['v'])) {
				printf("Version 1.20, 17-Jan-2023, written by Elmar Klausmeier\n");
				return;
			}
		}

		$collectionArray = new CollectionArray($draft);
		$templateManager = new TemplateManager();
		$buildMgr = new BuildCommand($collectionArray,$templateManager);

		if (is_null($singleFile)) $buildMgr->buildAllStatic($buildDest,$tags,$rssXmlFeed,$sitemap);
		else $buildMgr->buildSingleStatic($buildDest,$singleFile,$extractFile);

		//$this->stopXhprof();
	}
}
