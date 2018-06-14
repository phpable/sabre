<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;

use \Able\Prototypes\ICallable;
use \Able\Prototypes\TCallable;

use \Able\Reglib\Regexp;
use \Able\Helpers\Arr;

/**
 * @method string indent()
 * @method string line()
 * @method string file()
 * @method int index()
 * @method bool check(int $value)
 */
class Queue implements ICallable {
	use TCallable;

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
	 * @param Task $Task
	 * @throws \Exception
	 */
	public final function add(Task $Task){
		$this->Stack = Arr::insert($this->Stack, count($this->Stack) - 2, $Task);
	}

	/**
	 * @param Task $Task
	 * @throws \Exception
	 */

	public final function immediately(Task $Task){
		array_push($this->Stack, $Task);
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
