<?php declare(strict_types=1);
// Global config variables for Simplified Saaze

namespace Saaze;


class Config {
	static public array $H;	// hash for global config data

	static public function init() : void {
		if (!defined('SAAZE_PATH')) {
			throw new \Exception('SAAZE_PATH is not defined');
		}

		//$entrpp = getenv('ENTRIES_PER_PAGE');
		self::$H = array(
			'global_rbase'			=> "",
			'global_path_base'      => SAAZE_PATH,
			'global_path_cache'     => SAAZE_PATH . '/' . ($_ENV['CACHE_PATH']     ?? 'cache'),
			'global_path_content'   => SAAZE_PATH . '/' . ($_ENV['CONTENT_PATH']   ?? 'content'),
			'global_path_public'    => SAAZE_PATH . '/' . ($_ENV['PUBLIC_PATH']    ?? 'public'),
			'global_path_templates' => SAAZE_PATH . '/' . ($_ENV['TEMPLATES_PATH'] ?? 'templates'),
			'global_config_entries_per_page' => $_ENV['ENTRIES_PER_PAGE'] ?? 30,	//($entrpp ? $entrpp : 30),
			'global_excerpt_length' => 300,
		);
		//printf("Config: H[global_path_public] = %s\n",self::$H['global_path_public']);

		// Statistics for various functions
		$GLOBALS['content'] = 0;
		$GLOBALS['contentCached'] = 0;
		$GLOBALS['excerpt'] = 0;
		$GLOBALS['excerptCached'] = 0;
		$GLOBALS['renderEntry'] = 0;
		$GLOBALS['YamlParser'] = 0;	// time spent in YAML parsing in routine parseEntry()
		$GLOBALS['YamlParserNcall'] = 0;	// number of calls to yaml_parse()
		$GLOBALS['parseCollectionNcall'] = 0;
		$GLOBALS['MathParser'] = 0;	// time spent in MathParser
		$GLOBALS['MathParserNcall'] = 0;	// number of calls
		$GLOBALS['md2html'] = 0;	// time spent in Markdown to HTML conversion

		// md4c called via PHP-FFI
		$GLOBALS['ffi'] = \FFI::cdef("char *md4c_toHtml(const char*);","/srv/http/php_md4c_toHtml.so");

		$GLOBALS['rbase'] = "";
	}

}
