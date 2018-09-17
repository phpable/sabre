<?php
namespace Able\Sabre\Utilities;

use \Able\IO\Abstractions\IReader;

use \Able\Prototypes\ICallable;
use \Able\Prototypes\ICountable;
use \Able\Prototypes\TCallable;

use \Able\Reglib\Regexp;
use \Able\Helpers\Arr;

use \Able\IO\Path;
use \Able\IO\Reader;

/**
 * @method string line()
 * @method string file()
 * @method int index()
 * @method bool check(int $value)
 */
class Queue implements ICallable, ICountable {
	use TCallable;

	/**
	 * @var callable
	 */
	private $Handler = null;

	/**
	 * Queue constructor.
	 * @param callable $Handler
	 * @throws \Exception
	 */
	public final function __construct(callable $Handler = null) {
		if (is_callable($Handler)){
			$this->Handler = $Handler;
		}
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
	 * @return int
	 */
	public final function count(): int {
		return count($this->Stack);
	}

	/**
	 * @return void
	 */
	public final function flush(): void {
		while(count($this->Stack) > 0){
			array_pop($this->Stack);
		}
	}

	/**
	 * @param IReader $Reader
	 * @throws \Exception
	 */
	public final function add(IReader $Reader): void {
		$this->Stack = Arr::insert($this->Stack,
			count($this->Stack) - 2, (new Task($Reader)));
	}

	/**
	 * @param IReader $Reader
	 * @throws \Exception
	 */

	public final function immediately(IReader $Reader): void {
		array_push($this->Stack, (new Task($Reader)));
	}

	/**
	 * @return Task
	 * @throws \Exception
	 */
	protected final function active(){
		if (count($this->Stack) < 1){
			throw new \Exception('Queue is empty!');
		}

		return Arr::last($this->Stack);
	}

	/**
	 * @return string|null
	 * @throws \Exception
	 */
	public final function take(): ?string {
		while(count($this->Stack) > 0 && !$this->active()->valid()){
			if (!is_null($this->Handler)){
				call_user_func($this->Handler, $this->active()->toString());
			}

			array_pop($this->Stack);
		}

		return count($this->Stack) > 0 && $this->active()->read()
			? $this->active()->line() : null;
	}

}
