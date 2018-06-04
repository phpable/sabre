<?php
namespace Able\Sabre\Utilities;

use \Able\Struct\AStruct;
use \Able\Reglib\Reglib;

/**
 * @property string token
 * @property callable handler
 * @property bool multiline
 */
class SToken extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['token', 'handler', 'multiline'];

	/**
	 * @var bool
	 */
	protected const defaultMultilineValue = true;

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
	 * @param bool $value
	 * @return bool
	 * @throws \Exception
	 */
	protected final function setMultilineProperty(bool $value): bool {
		return $value;
	}

}
