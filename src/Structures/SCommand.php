<?php
namespace Able\Sabre\Structures;

use \Able\Struct\AStruct;
use \Able\Reglib\Regex;

use \Able\Sabre\Exceptions\EInvalidToken;
use \Able\Sabre\Exceptions\ECapacityOutranged;
use \Able\Sabre\Exceptions\EUnresolvableHandler;

/**
 * @property string token
 * @property callable handler
 * @property int capacity
 * @property bool multiline
 * @property bool composite
 */
class SCommand extends AStruct {

	/**
	 * @var array
	 */
	protected static array $Prototype = [
		'token',
		'handler',
		'capacity',
		'multiline',
		'composite'
	];

	/**
	 * @const int
	 */
	protected const defaultCapacityValue = 0;

	/**
	 * @const bool
	 */
	protected const defaultMultilineValue = true;

	/**
	 * @const bool
	 */
	protected const defaultCompositeValue = false;

	/**
	 * @param string $value
	 * @return string
	 * @throws EInvalidToken
	 */
	protected final function setTokenProperty(string $value): string {
		if (!preg_match('/^' . Regex::RE_KEYWORD . '$/', $value)){
			throw new EInvalidToken($value);
		}

		return strtolower($value);
	}

	/**
	 * @param callable $Handler
	 * @return callable
	 *
	 * @throws EUnresolvableHandler
	 */
	protected final function setHandlerProperty(callable $Handler): callable {
		if (!is_callable($Handler)){
			throw new EUnresolvableHandler();
		}

		return $Handler;
	}

	/**
	 * @param int $value
	 * @return int
	 *
	 * @throws ECapacityOutranged
	 */
	protected final function setCapacityProperty(int $value): int {
		if ($value < 0){
			throw new ECapacityOutranged($value);
		}

		return $value;
	}

	/**
	 * @param bool $value
	 * @return bool
	 */
	protected final function setMultilineProperty(bool $value): bool {
		return $value;
	}

	/**
	 * @param bool $value
	 * @return bool
	 */
	protected final function setCompositeProperty(bool $value): bool {
		return $value;
	}
}
