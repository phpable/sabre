<?php
namespace Able\Sabre\Parsers;

use \Able\Prototypes\TStatic;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

class BracketsParser {
	use TStatic;

	/**
	 * @var callable
	 */
	protected static $Resolver = null;

	/**
	 * @param callable $Resolver
	 */
	public final static function assignResolver(callable $Resolver): void {
		static::$Resolver = $Resolver;
	}

	/**
	 * @param string $source
	 * @param int $count
	 * @return string
	 * @throws \Exception
	 */
	public static function parse(string &$source, int $count = 0): string {
		if (!preg_match('/^\s*\(/', $source) && $count < 1) {
			return '';
		}

		$out = '';

		do{
			if (!empty($source) && $source[0] == '(') {
				$count++;
			}

			if (!empty($source) && $source[0] == ')') {
				$count--;
			}

			$out .= Regexp::create('/^[()]{0,1}' . ($count > 0 ? '(?:'. Reglib::QUOTED
				. '|[^)(]+)*\s*' : '') . '/')->retrieve($source) ;

			if (empty($source) && $count > 0 && !is_null(static::$Resolver)){
				$source = call_user_func(static::$Resolver);
			}

		} while ($count > 0 && !empty($source));

		if ($count > 0) {
			throw new \Exception('Condition is not completed!');
		}

		return trim($out);
	}
}
