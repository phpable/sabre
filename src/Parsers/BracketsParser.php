<?php
namespace Able\Sabre\Parsers;

use \Able\Prototypes\TStatic;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

use \Able\Helpers\Src;

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
	 * @const string
	 */
	public const BT_CIRCLE = '()';

	/**
	 * @const string
	 */
	public const BT_CURLY = '{}';

	/**
	 * @const string
	 */
	public const BT_SQUARE = '[]';

	/**
	 * @param string $source
	 * @param string $type
	 * @param int $count
	 * @return string
	 * @throws \Exception
	 */
	public static function parse(string &$source, string $type = self::BT_CIRCLE, int $count = 0): string {
		if (!in_array($type, [self::BT_CIRCLE, self::BT_CURLY, self::BT_SQUARE])){
			throw new \Exception('Invalid brackets type!');
		}

		if (!preg_match($e = '/^' . preg_quote($type[0], '/') . '/', $source) && $count < 1) {
			return '';
		}

		$out = '';
		do{
			if (!empty($source) && $source[0] == $type[0]) {
				$count++;
			}

			if (!empty($source) && $source[0] == $type[1]) {
				$count--;
			}

			$out .= Regexp::create($e = '/^[' . Src::esc($type, ']') . ']{0,1}' . ($count > 0 ? '(?:'. Reglib::QUOTED
				. '|[^' . Src::esc($type, ']') . ']+)*\s*' : '') . '/')->retrieve($source) ;

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
