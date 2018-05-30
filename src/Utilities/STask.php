<?php
namespace Able\Sabre\Utilities;

use \Able\Struct\AStruct;

use \Able\IO\Abstractions\IReader;

/**
 * @property \Generator stream
 * @property int line
 * @property string file
 * @property string prefix
 */
class STask extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['stream', 'prefix', 'file', 'line'];

	/**
	 * @const int
	 */
	protected const defaultLineValue = 0;

	/**
	 * @param \Generator $value
	 * @return \Generator
	 */
	protected final function setStreamProperty(\Generator $value): \Generator {
		return $value;
	}

	/**
	 * @param int $value
	 * @return int
	 */
	protected final function setLineProperty(int $value): int {
		return $value;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected final function setFileProperty(string $value): string {
		return $value;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected final function setPrefixProperty(string $value): string {
		return $value;
	}
}
