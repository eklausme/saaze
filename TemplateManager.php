<?php declare(strict_types=1);

namespace Saaze;


class TemplateManager {
	public function templateExists(string $template) : bool {
		return file_exists(\Saaze\Config::$H['global_path_templates'] . "/{$template}.php");
	}


	public function renderCollection(Collection $collection, int|string $page) : string {
		$template = 'index';	//'collection';
		if ($this->templateExists($collection->slug . '/index')) {
			$template = $collection->slug . '/index';
		}

		$entries = $collection->entriesSansIndex;
		$page    = (int) filter_var($page, FILTER_SANITIZE_NUMBER_INT);	// end-user might fiddle with page in Saaze.php
		$entries_per_page = $collection->data['entries_per_page'] ?? \Saaze\Config::$H['global_config_entries_per_page'];
		$pagination = $collection->paginateEntriesForTemplate($entries, $page, $entries_per_page);
		$pagination['entries'] = array_map(function ($entry) { return $entry->data; }, $pagination['entries']);	// flatten entry

		$url = $collection->data['index_route'];
		if ($page > 1) $url .= "/page/{$page}";
		$collection = $collection->data;	// make some elements invisible in template
		$rbase = \Saaze\Config::$H['global_rbase'] ?? $GLOBALS['rbase'] ?? "/";

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . DIRECTORY_SEPARATOR . $template . ".php";
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}


	public function renderEntry(Entry $entry) : string {
		$GLOBALS['renderEntry'] += 1;
		$entryData = $entry->data;
		$template  = 'entry';

		if (!empty($entryData['template']) && $this->templateExists($entryData['template'])) {
			// Individual entries can override which template is used to display them by specifying a template in their Yaml frontmatter.
			$template = $entryData['template'];
		} elseif ($this->templateExists($entry->collection->slug . '/entry')) {
			$template = $entry->collection->slug . '/entry';
		}

		$url = $entryData['url'];
		$title = $entryData['title'];
		$entry = $entryData;	// make some elements invisible in template
		$rbase = \Saaze\Config::$H['global_rbase'] ?? $GLOBALS['rbase'] ?? "/";

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . DIRECTORY_SEPARATOR . $template . '.php';
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}


	public function renderError(string $message, int $code) : string {
		$template = 'error';
		if ($this->templateExists("error{$code}")) $template = "error{$code}";
		if (!$this->templateExists($template)) return "{$code} {$message}";
		$rbase = \Saaze\Config::$H['global_rbase'] ?? $GLOBALS['rbase'] ?? "/";

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . DIRECTORY_SEPARATOR . $template . '.php';
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}


	public function renderGeneral(array $collections, string $template) : string {
		if (!$this->templateExists($template)) return "";
		$rbase = \Saaze\Config::$H['global_rbase'] ?? $GLOBALS['rbase'] ?? "/";

		ob_start();
		require \Saaze\Config::$H['global_path_templates'] . "/" . $template . ".php";
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}
}
