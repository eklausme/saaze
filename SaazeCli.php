<?php

namespace Saaze;


class SaazeCli
{
	/**
	 * @param string $saazePath
	 */
	public function __construct($saazePath) {
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

	public function run() {
		//$this->startXhprof();
		$dest = "build";
		$singleFile = null;
		$extractFile = 0;

		$options = getopt("d:es:");
		//var_dump($options);
		if (count($options) > 0) {
			if (isset($options['d']) && strlen($options['d']) > 0 && $options['d'] !== "/") {
				$dest = $options['d'];
			}
			if (isset($options['e'])) $extractFile = 1;
			if (isset($options['s']) && strlen($options['s']) > 0 && $options['s'] !== "/") {
				$singleFile = $options['s'];
			}
		}

		$collectionManager = new \Saaze\CollectionManager();
		$entryManager = new \Saaze\EntryManager();
		$templateManager = new \Saaze\TemplateManager($entryManager);
		$buildMgr = new \Saaze\BuildCommand($collectionManager,$entryManager,$templateManager);

		if (is_null($singleFile)) $buildMgr->buildAllStatic($dest);
		else $buildMgr->buildSingleStatic($dest,$singleFile,$extractFile);

		//$this->stopXhprof();
	}
}
