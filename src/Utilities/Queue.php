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
	 * @return Path
	 */
	public final function getSourcePath(){
		return $this->Source->toPath();
	}

	/**
	 * Queue constructor.
	 * @param Path $Source
	 * @throws \Exception
	 */
	public final function __construct(Path $Source) {

		/**
		 * The default path is used as a root for all non-absolute file paths
		 * added to the processing queue.
		 */
		if (!$Source->isReadable()){
			throw new \Exception('The source path does not exist or not readable!');
		}

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
	 * @param string $prefix
	 * @throws \Exception
	 */
	public final function add(Path $Path, string $prefix = ''): void {
		if (!$Path->isAbsolute()){
			$Path->prepend($this->Source);
		}

		$this->Stack = Arr::insert($this->Stack, count($this->Stack) - 2, (
			new Task($Path->toFile()->toReader()))->withPrefix($prefix));
	}

	/**
	 * @param Path $Path
	 * @param string $prefix
	 * @throws \Exception
	 */

	public final function immediately(Path $Path, string $prefix = ''): void {
		if (!$Path->isAbsolute()){
			$Path->prepend($this->Source);
		}

		array_push($this->Stack, $Task = (new Task($Path->toFile()
			->toReader()))->withPrefix($prefix));
	}

	/**
	 * @return Task
	 * @throws \Exception
	 */
	protected final function active(){
		if (count($this->Stack) < 1){
			throw new \Exception('Queue is empty!');
		}

		return $this->Stack[count($this->Stack) - 1];
	}

	/**
	 * @return string|null
	 * @throws \Exception
	 */
	public final function take(): ?string {
		while(count($this->Stack) > 0 && !$this->active()->valid()){
			array_pop($this->Stack);
		}

		return count($this->Stack) > 0 && $this->active()->read()
			? $this->active()->line() : null;
	}

}
