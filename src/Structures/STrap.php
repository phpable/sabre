<?php
namespace Able\Sabre\Structures;

use \Able\Struct\AStruct;
use \Able\Reglib\Regex;

/**
 * @property string opening
 * @property string closing
 * @property callable handler
 */
class STrap extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['opening', 'closing', 'handler'];

	/**
	 * @var bool
	 */
	protected const defaultMultilineValue = true;

	/**
	 * @param string $value
	 * @return string
	 * @throws \Exception
	 */
	protected final function setOpeningProperty(string $value): string {
		if (!preg_match('/^[@{}()\[\]!%&*+=-]{1,3}$/', $value)){
			throw new \Exception('Invalid opening format!');
		}

		return strtolower($value);
	}

	/**
	 * @param string $value
	 * @return string
	 * @throws \Exception
	 */
	protected final function setClosingProperty(string $value): string {
		if (!preg_match('/^[@{}()\[\]!%&*+=-]{1,3}$/', $value)){
			throw new \Exception('Invalid closing format!');
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
}
