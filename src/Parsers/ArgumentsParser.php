<?php
namespace Able\Sabre\Parsers;

use \Able\Prototypes\TStatic;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

final class ArgumentsParser {
	use TStatic;

	/**
	 * @var array
	 */
	private static $Brackets = ['(' => ')', '[' => ']', '{' => '}'];

	/**
	 * @param string $source
	 * @return array
	 * @throws \Exception
	 */
	public static final function parse(string &$source): array {
		if (empty($source = preg_replace('/^[\s,]+/', '', $source))){
			return [];
		}

		$out = [];

		if (in_array($source[0], array_keys(self::$Brackets))){
			array_push($out, BracketsParser::parse($source,
				$source[0].self::$Brackets[$source[0]]));

		}else{
			array_push($out, Regexp::create('/^' . Reglib::PARAMS . '/')->retrieve($source));
		}

		return array_filter(array_merge($out, self::parse($source)));
	}
}
