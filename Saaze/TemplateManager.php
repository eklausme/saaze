<?php

namespace Saaze;

//use Jenssegers\Blade\Blade;


class TemplateManager {
	/**
	 * @var EntryManagerInterface
	 */
	protected $entryManager;


	public function __construct(EntryManager $entryManager) {
		$this->entryManager = $entryManager;
	}

	/**
	 * @param string $template
	 * @return boolean
	 */
	public function templateExists($template) {
		//return file_exists(\Saaze\Config::$H['global_path_templates'] . "/{$template}.blade.php");
		return file_exists(\Saaze\Config::$H['global_path_templates'] . "/{$template}.php");
	}


	public function renderCollection(Collection $collection, $page) {
		$this->entryManager->setCollection($collection);

		$template = 'index';	//'collection';
		if ($this->templateExists($collection->slug . '/index')) {
			$template = $collection->slug . '/index';
		}

		//$entries    = $this->entryManager->entries;	//$this->entryManager->getEntriesForTemplate();
		$entries    = $this->entryManager->entriesSansIndex;
		$page       = filter_var($page, FILTER_SANITIZE_NUMBER_INT);	// end-user might fiddle with page in class Router
		$perPage    = \Saaze\Config::$H['global_config_entries_per_page'];
		$pagination = $this->entryManager->paginateEntriesForTemplate($entries, $page, $perPage);
		$pagination['entries'] = array_map(function ($entry) { return $entry->data; }, $pagination['entries']);	// flatten entry

		$collection = $collection->data;	// make some elements invisible in template
		$rbase = $GLOBALS['rbase'];

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . '/' . $template . ".php";
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}


	public function renderEntry (Entry $entry) {
		$GLOBALS['renderEntry'] += 1;
		//$this->entryManager->setCollection($entry->collection);	// not used

		$entryData = $entry->data;
		$template  = 'entry';

		if (!empty($entryData['template']) && $this->templateExists($entryData['template'])) {
			// Individual entries can override which template is used to display them by specifying a template in their Yaml frontmatter.
			$template = $entryData['template'];
		} elseif ($this->templateExists($entry->collection->slug . '/entry')) {
			$template = $entry->collection->slug . '/entry';
		}

		$entry = $entryData;	// make some elements invisible in template
		$title = $entry['title'];
		$rbase = $GLOBALS['rbase'];

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . "/" . $template . ".php";
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}

	/**
	 * @param string $message
	 * @param int $code
	 * @return string
	 */
	public function renderError($message, $code)
	{
		$template = 'error';
		if ($this->templateExists("error{$code}")) {
			$template = "error{$code}";
		}

		if (!$this->templateExists($template)) {
			return "{$code} {$message}";
		}

		$rbase = $GLOBALS['rbase'];

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . "/" . $template . ".php";
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}
}
