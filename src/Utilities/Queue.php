<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;

use \Able\Prototypes\ICallable;
use \Able\Prototypes\TCallable;

use \Able\Reglib\Regexp;
use \Able\Helpers\Arr;

use \Able\IO\Path;

/**
 * @method string indent()
 * @method string line()
 * @method string file()
 * @method int index()
 * @method bool check(int $value)
 */
class Queue implements ICallable {
	use TCallable;

	/**
	 * @var Path
	 */
	private $Source = null;

	/**
	 * Queue constructor.
	 * @param Path $Source
	 */
	public final function __construct(Path $Source) {
		$this->Source = $Source;
	}

	/***
	 * @param string $name
	 * @param array $Args
	 * @return mixed
	 * @throws \Exception
	 */
	public final function call(string $name, array $Args = []) {
		if (count($this->Stack) < 1 || !method_exists($this->active(), $name)
			|| in_array(strtolower($name), ['read, __construct'])){
				throw new \Exception('Undefined method!');
		}

		return $this->active()->{$name}(...$Args);
	}

	/**
	 * @var Task[]
	 */
	private $Stack = [];

	/**
	 * @param Path $Path
	 * @throws \Exception
	 * @return Task
	 */
	public final function add(Path $Path): Task{
		if (!$Path->isAbsolute()){
			$Path->prepend($this->Source);
		}

		$this->Stack = Arr::insert($this->Stack, count($this->Stack) - 2, $Task = new Task($Path->toFile()->toReader()));
		return $Task;
	}

	/**
	 * @param Path $Path
	 * @throws \Exception
	 * @return Task
	 */

	public final function immediately(Path $Path): Task {
		if (!$Path->isAbsolute()){
			$Path->prepend($this->Source);
		}

		array_push($this->Stack, $Task = new Task($Path->toFile()->toReader()));
		return $Task;
	}

	/**
	 * @return Task
	 */
	protected final function active(){
		return $this->Stack[count($this->Stack) - 1];
	}

	/**
	 * @return \Generator
	 */
	public final function take(): \Generator {
		while(count($this->Stack) > 0) {
			while($this->active()->read()){
				yield $this->active()->line();
			}

			array_pop($this->Stack);
		}
	}
}
