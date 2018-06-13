<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;
use \Able\Reglib\Regexp;

class Task {

	/**
	 * @const int
	 */
	public const F_VERBATIM = 0b0001;

	/**
	 * @var int
	 */
	private $mode = 0;

	/**
	 * @param int $value
	 * @return bool
	 * @throws \Exception
	 */
	public final function check(int $value): bool {
		return in_array($value = abs($value), [
			self::F_VERBATIM]) && $this->mode & $value;
	}

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
	 * @param int $mode
	 * @throws \Exception
	 */
	public final function __construct(IReader $Reader, int $mode = 0) {
		$this->Reader = $Reader;

		if ($mode < 0 || $mode > self::F_VERBATIM){
			throw new \Exception('Unsupported mode!');
		}

		$this->mode = $mode;
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
	 * @return string
	 */
	public final function index(): int{
		return $this->index;
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
}
