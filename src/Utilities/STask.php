<?php
namespace Able\Sabre\Utilities;

use \Able\Struct\AStruct;

use \Able\IO\Abstractions\IReader;

/**
 * @property IReader reader
 * @property \Generator stream
 * @property string line
 * @property string file
 */
class STask extends AStruct {

	/**
	 * @var array
	 */
	protected static $Prototype = ['reader', 'stream', 'file', 'line'];

	/**
	 * @const int
	 */
	protected const defaultLineValue = 0;

	/**
	 * @param IReader $value
	 * @return IReader
	 */
	protected final function setReaderProperty(IReader $value): IReader {
		return $value;
	}

	/**
	 * @param \Generator $value
	 * @return \Generator
	 */
	protected final function setStreamProperty(\Generator $value): \Generator {
		return $value;
	}

	/**
	 * @param string $value
	 * @return stgring
	 */
	protected final function setLineProperty(string $value): string {
		return $value;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected final function setFileProperty(string $value): string {
		return $value;
	}
}
