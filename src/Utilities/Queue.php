<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;

use \Able\Reglib\Regexp;
use \Able\Sabre\Utilities\STask;

class Queue {

	/**
	 * @var STask[]
	 */
	private $Stack = [];

	/**
	 * @param IReader $Reader
	 * @param string $prefix
	 * @throws \Exception
	 */
	public final function inject(IReader $Reader, string $prefix = null){
		array_push($this->Stack, new STask($Reader->read(),
			(string)$prefix, $Reader->toString()));
	}

	/**
	 * @return STask
	 */
	protected final function active(){
		return $this->Stack[count($this->Stack) - 1];
	}

	/**
	 * @return string
	 */
	public final function file(){
		return $this->active()->file;
	}

	/**
	 * @return int
	 */
	public final function line(){
		return $this->active()->line;
	}

	/**
	 * @var string
	 */
	private $prefix = null;

	/**
	 * @return string
	 */
	public final function prefix(){
		return $this->prefix;
	}

	/**
	 * @return \Generator
	 */
	public final function take(): \Generator {
		while(count($this->Stack) > 0) {
			while ($this->active()->stream->valid()) {
				$line = $this->active()->stream->current();

				$this->prefix = $this->active()->prefix
					. (new Regexp('/^\s+/'))->take($line);

				$this->active()->line++;
				$this->active()->stream->next();

				yield $this->active()->prefix . $line;
			}

			array_pop($this->Stack);
		}
	}
}
