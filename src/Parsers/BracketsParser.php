<?php
namespace Able\Sabre\Parsers;

use \Able\Prototypes\TStatic;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

use \Able\Helpers\Src;
use \Able\Helpers\Arr;

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
	 * @var array
	 */
	private static $Brackets = ['(' => '()', '[' => '[]', '{' => '{}'];

	/**
	 * @const string
	 */
	public const BT_DETECT = -1;

	/**
	 * @const string
	 */
	public const BT_CIRCLE = 0;

	/**
	 * @const string
	 */
	public const BT_SQUARE = 1;

	/**
	 * @const string
	 */
	public const BT_CURLY = 2;

	/**
	 * @param string $source
	 * @param int $type
	 * @param int $count
	 * @return string
	 * @throws \Exception
	 */
	public static function parse(string &$source, int $type = self::BT_DETECT, int $count = 0): string {
		if ($type < self::BT_DETECT || $type > self::BT_SQUARE) {
			throw new \Exception('Invalid brackets type!');
		}

		$parsed = '';
		if (strlen($source) > 0) {

			if ($type == self::BT_DETECT) {
				if ($count > 0) {
					throw new \Exception('Cannot detect brackets type!');
				}

				$type = (int)array_search($source[0], array_keys(self::$Brackets));
			}

			$pair = Arr::value(self::$Brackets, $type);
			if ($count < 1 && $source[0] != $pair[0]) {
				return '';
			}

			do {
				if (!empty($source) && $source[0] == $pair[0]) {
					$count++;
				}

				if (!empty($source) && $source[0] == $pair[1]) {
					$count--;
				}

				$parsed .= Regexp::create('/^[' . Src::esc($pair, ']') . ']{0,1}' . ($count > 0 ? '(?:' . Reglib::QUOTED
						. '|[^' . Src::esc($pair, ']') . ']+)*\s*' : '') . '/')->retrieve($source);

				if (empty($source) && $count > 0 && !is_null(static::$Resolver)) {
					$source = call_user_func(static::$Resolver);
				}

			} while ($count > 0 && !empty($source));

			if ($count > 0) {
				throw new \Exception('Condition is not completed!');
			}

		}

		return trim($parsed);
	}
}
