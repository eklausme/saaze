<?php declare(strict_types=1);

namespace Saaze;


class Config {	// Global config variables for Simplified Saaze
	static public array $H;	// hash for global config data

	static public function getenv2(string $var) : string|null {	// same as getenv(), but return null instead of false
		$s = getenv($var);
		return ($s ? $s : null);
	}

	static public function init() : void {
		if (!defined('SAAZE_PATH')) {
			throw new \Exception('SAAZE_PATH is not defined');
		}

		self::$H = array(
			'global_rbase'          => Config::getenv2('RBASE'),
			'global_path_base'      => SAAZE_PATH,
			'global_path_content'   => SAAZE_PATH . DIRECTORY_SEPARATOR . (Config::getenv2('CONTENT_PATH')   ?? 'content'),
			'global_path_public'    => SAAZE_PATH . DIRECTORY_SEPARATOR . (Config::getenv2('PUBLIC_PATH')    ?? 'public'),
			'global_path_templates' => SAAZE_PATH . DIRECTORY_SEPARATOR . (Config::getenv2('TEMPLATES_PATH') ?? 'templates'),
			'global_config_entries_per_page' => (int)(Config::getenv2('ENTRIES_PER_PAGE') ?? 20),
			'global_excerpt_length' => 300,
			// md4c called via PHP-FFI
			//'global_ffi'            => \FFI::cdef('char *md4c_toHtml(const char*);', SAAZE_PATH . DIRECTORY_SEPARATOR
			//	. 'vendor' . DIRECTORY_SEPARATOR . 'eklausme' . DIRECTORY_SEPARATOR . 'saaze'
			//	. DIRECTORY_SEPARATOR . 'php_md4c_toHtml.so'),
		);
		//printf("Config: H[global_path_public] = %s\n",self::$H['global_path_public']);

		// Statistics for various functions
		$GLOBALS['content'] = 0;
		$GLOBALS['contentCached'] = 0;
		$GLOBALS['excerpt'] = 0;
		$GLOBALS['excerptCached'] = 0;
		$GLOBALS['renderCollection'] = 0;
		$GLOBALS['renderCollectionNcall'] = 0;
		$GLOBALS['renderEntry'] = 0;
		$GLOBALS['renderEntryNcall'] = 0;
		$GLOBALS['parseEntry'] = 0;	// time spent in routine parseEntry()
		$GLOBALS['parseEntryNcall'] = 0;	// number of calls to parseEntry() = yaml_parse()
		$GLOBALS['parseCollectionNcall'] = 0;
		$GLOBALS['MathParser'] = 0;	// time spent in MathParser
		$GLOBALS['MathParserNcall'] = 0;	// number of calls
		$GLOBALS['md2html'] = 0;	// time spent in Markdown to HTML conversion

		$GLOBALS['rbase'] = "";	// relative base
	}
}
