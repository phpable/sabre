<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;
use \Able\Reglib\Regexp;

use \Able\Prototypes\IStringable;
use \Able\Prototypes\TStringable;

class Task implements IStringable {
	use TStringable;

	/**
	 * @var Reader
	 */
	private $Reader = null;

	/**
	 * @return string
	 */
	public final function file(){
		return $this->Reader->toString();
	}

	/**
	 * @var IStream
	 */
	private $Stream = null;

	/**
	 * Task constructor.
	 * @param IReader $Reader
	 * @throws \Exception
	 */
	public final function __construct(IReader $Reader) {
		$this->Reader = $Reader;
		$this->Stream = $Reader->read();
	}

	/**
	 * @var string
	 */
	private $prefix = null;

	/**
	 * @param string $value
	 * @return Task
	 */
	public final function withPrefix(string $value): Task {
//		_dumpe($value);
		$this->prefix = $value;
		return $this;
	}

	/**
	 * @var string
	 */
	private $line = null;

	/**
	 * @return string
	 */
	public final function line(): string {
		return $this->prefix . $this->line;
	}

	/**
	 * @var string
	 */
	private $indent = null;

	/**
	 * @return string
	 */
	public final function indent(): string {
		return $this->prefix . $this->indent;
	}

	private $index = 0;

	/**
	 * @return int
	 */
	public final function index(): int{
		return $this->index;
	}

	/**
	 * @return bool
	 */
	public final function valid(){
		return $this->Stream->valid();
	}

	/**
	 * @return bool
	 */
	public final function read(): bool {
		if (!$this->Stream->valid()) {
			return false;
		}

		$this->index++;

		$this->line = (string)$this->Stream->current();
		$this->indent = Regexp::create('/^\s+/')->take($this->line);

		$this->Stream->next();

		return true;
	}

	/**
	 * @return string
	 */
	public final function toString(): string {
		return $this->file();
	}
}
