<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;
use \Able\Sabre\Utilities\STask;

class Queue {

	/**
	 * @var STask[]
	 */
	private $Stack = [];

	/**
	 * @param IReader $Reader
	 * @throws \Exception
	 */
	public final function inject(IReader $Reader){
		array_push($this->Stack, new STask($Reader,
			$Reader->read(), $Reader->toString()));
	}

	/**
	 * @return STask
	 */
	protected final function current(){
		return $this->Stack[count($this->Stack) - 1];
	}

	/**
	 * @return \Generator
	 */
	protected final function stream(){
		return $this->current()->stream;
	}

	/**
	 * @return IReader
	 */
	protected final function reader(){
		return $this->current()->reader;
	}

	/**
	 * @return string
	 */
	public final function file(){
		return $this->current()->file;
	}

	/**
	 * @return string
	 */
	public final function line(){
		return $this->current()->line;
	}

	/**
	 * @return \Generator
	 */
	public final function take(): \Generator {
		while(count($this->Stack) > 0) {
			while ($this->stream()->valid()) {
				$this->current()->line++;

				$line = $this->stream()->current();
				$this->stream()->next();

				yield $line;
			}

			array_pop($this->Stack);
		}
	}
}
