<?php
namespace Able\Sabre\Structures;

use \Able\Struct\AStruct;
use \Able\Reglib\Reglib;

/**
 * @property string token
 * @property callable handler
 * @property int capacity
 * @property bool multiline
 * @property bool composite
 */
class SToken extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['token', 'handler', 'capacity', 'multiline', 'composite'];

	/**
	 * @const bool
	 */
	protected const defaultMultilineValue = true;
	/**
	 * @const bool
	 */
	protected const defaultCompositeValue = false;

	/**
	 * @const int
	 */
	protected const defaultCapacityValue = 0;

	/**
	 * @param string $value
	 * @return string
	 * @throws \Exception
	 */
	protected final function setTokenProperty(string $value): string {
		if (!preg_match('/^' . Reglib::KEYWORD . '$/', $value)){
			throw new \Exception('Invalid opening format!');
		}

		return strtolower($value);
	}

	/**
	 * @param callable $Handler
	 * @return callable
	 * @throws \Exception
	 */
	protected final function setHanlerProperty(callable $Handler): callable {
		if (!is_callable($Handler)){
			throw new \Exception('Unresolvable handler!');
		}

		return $Handler;
	}

	/**
	 * @param int $value
	 * @return int
	 */
	protected final function setCapacityProperty(int $value): int {
		return $value;
	}

	/**
	 * @param bool $value
	 * @return bool
	 * @throws \Exception
	 */
	protected final function setMultilineProperty(bool $value): bool {
		return $value;
	}

	/**
	 * @param bool $value
	 * @return bool
	 * @throws \Exception
	 */
	protected final function setCompositeProperty(bool $value): bool {
		return $value;
	}
}
