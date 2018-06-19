<?php
namespace Able\Sabre\Structures;

use \Able\Struct\AStruct;

/**
 * @property bool verbatim
 * @property bool ignore
 */
class SState extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['verbatim', 'ignore'];

	/**
	 * @var bool
	 */
	protected const defaultVerbatimValue = false;

	/**
	 * @var bool
	 */
	protected const defaultIgnoreValue = false;

	/**
	 * @param bool $value
	 * @return bool
	 */
	public final function setVerbatimProperty(bool $value): bool {
		return $value;
	}

	/**
	 * @param bool $value
	 * @return bool
	 */
	public final function setIgnoreProperty(bool $value): bool {
		return $value;
	}
}
