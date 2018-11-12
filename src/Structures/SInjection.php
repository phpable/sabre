<?php
namespace Able\Sabre\Structures;

use \Able\Struct\AStruct;
use \Able\Reglib\Regex;

use \Able\Helpers\Str;

use \Able\Prototypes\IStringable;
use \Able\Prototypes\TStringable;

use \Able\Sabre\Exceptions\EUnresolvableHandler;
use \Able\Sabre\Exceptions\EInvalidToken;

/**
 * @property string opening
 * @property string closing
 * @property callable handler
 */
class SInjection extends AStruct
	implements IStringable {

	use TStringable;

	/**
	 * @var array
	 */
	protected static array $Prototype = ['opening', 'closing', 'handler'];

	/**
	 * @param string $value
	 * @return string
	 *
	 * @throws EInvalidToken
	 */
	protected final function setOpeningProperty(string $value): string {
		if (!preg_match('/^[@{}()\[\]!%&*+=-]{1,3}$/', $value)){
			throw new EInvalidToken($value);
		}

		return strtolower($value);
	}

	/**
	 * @param string $value
	 * @return string
	 *
	 * @throws EInvalidToken
	 */
	protected final function setClosingProperty(string $value): string {
		if (!preg_match('/^[@{}()\[\]!%&*+=-]{1,3}$/', $value)){
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
	protected final function setHanlerProperty(callable $Handler): callable {
		if (!is_callable($Handler)){
			throw new EUnresolvableHandler();
		}

		return $Handler;
	}

	/**
	 * @return string
	 */
	public final function toString(): string {
		return Str::join(' ', $this->opening, $this->closing);
	}

	/**
	 * @return string
	 */
	public final function getHash(): string {
		return md5($this->toString());
	}
}
