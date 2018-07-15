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
	public const BT_DETECT = 0;

	/**
	 * @const string
	 */
	public const BT_CIRCLE = 1;

	/**
	 * @const string
	 */
	public const BT_SQUARE = 2;

	/**
	 * @const string
	 */
	public const BT_CURLY = 3;

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

		if ($type == self::BT_DETECT && $count > 0) {
			throw new \Exception('Invalid brackets type!');
		}

		$parsed = '';
		if (strlen($source) > 0) {
			if ($type == self::BT_DETECT && ($type = array_search($source[0],
						array_keys(self::$Brackets)) + 1) < 1) {
				throw new \Exception('Invalid brackets type!');
			}

			$pair = Arr::value(self::$Brackets, $type - 1);
			if (!preg_match($e = '/^' . preg_quote($pair[0], '/')
					. '/', $source) && $count < 1) {
				return '';
			}

			do {
				if (!empty($source) && $source[0] == $pair[0]) {
					$count++;
				}

				if (!empty($source) && $source[0] == $pair[1]) {
					$count--;
				}

				$parsed .= Regexp::create($e = '/^[' . Src::esc($pair, ']') . ']{0,1}' . ($count > 0 ? '(?:' . Reglib::QUOTED
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
