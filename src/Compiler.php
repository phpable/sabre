<?php
namespace Able\Sabre;

use \Able\IO\Abstractions\IReader;

use \Able\IO\Buffer;
use \Able\IO\Path;
use \Able\IO\File;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\Sabre\Utilities\SToken;
use \Able\Sabre\Utilities\STrap;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

use \Eggbe\Helper\Str;
use \Eggbe\Helper\Src;

class Compiler {

	/**
	 * @var Buffer[]
	 */
	private static $Prepend = [];

	/**
	 * @param File $File
	 */
	public final static function prepend(File $File){
		array_push(self::$Prepend, $File->toBuffer()->process(function (string $value) use ($File) {
			if (!Src::check($value)){
				throw new \Exception('Invalid file syntax: ' . $File->toString());
			}

			return (new Regexp('/\s*\\?>$/'))->erase(trim($value)) . "\n?>\n";
		}));
	}

	/**
	 * @var STrap[]
	 */
	private static $Traps = [];

	/**
	 * @param STrap $Signature
	 * @throws \Exception
	 */
	public final static function trap(STrap $Signature){
		if (isset(self::$Traps[$name = Str::join('-', $Signature->opening, $Signature->closing)])){
			throw new \Exception('Trap limited with "' . $Signature->opening
				. '" and "' . $Signature->closing. '" are already declared!');
		}

		self::$Traps[$Signature->opening] = $Signature;
	}

	/**
	 * @var array
	 */
	private static $Rules = [];

	/**
	 * @param SToken $Signature
	 * @throws \Exception
	 */
	public final static function token(SToken $Signature) {
		if (isset(self::$Rules[$Signature->token])){
			throw new \Exception('Token @' . $Signature->opening . 'already declared!');
		}

		self::$Rules[$Signature->token] = [$Signature,
			new SToken('end', function(){ return '}'; })];
	}

	/**
	 * @param string $token
	 * @param SToken $Signature
	 * @throws \Exception
	 */
	public final static function extend(string $token, SToken $Signature){
		if (!isset(self::$Rules[$token = strtolower(trim($token))])){
			throw new \Exception('Unregistered token ' . $token . '!');
		}

		array_push(self::$Rules[$token], $Signature);
	}

	/**
	 * @var Queue
	 */
	private $Queue = null;

	/**
	 * Compiler constructor.
	 * @throws \Exception
	 */
	public final function __construct() {
		$this->Queue = new Queue();
	}

	/**
	 * @var Path
	 */
	private $Source = null;

	/**
	 * @param File $File
	 * @return \Generator
	 * @throws \Exception
	 */
	public function compile(File $File): \Generator {
		$this->Source = $File->toPath()->getParent();

		/**
		 * The initially given source file has to be
		 * in the beginning of the compilation queue.
		 */
		$this->Queue->inject(new Task($File->toReader()));

		/**
		 * Files defined as a prepared php-code fragment have
		 * to be added to the queue before the compilation process begins.
		 *
		 * The queue always proceeds from last added file to first,
		 * so the reverse order is essential.
		 */
		foreach (array_reverse(static::$Prepend) as $Buffer){
			$this->Queue->inject(new Task($Buffer, Task::F_VERBATIM));
		}

		foreach ($this->Queue->take() as $i => $line) {
			try {
				if (!$this->Queue->check(Task::F_VERBATIM)) {
					$line = $this->parse($this->replace($line));
				}

				yield $line;
			} catch (\Exception $Exception) {
					throw new \Exception('Error in ' . $this->Queue->file()
						. ' on ' . $this->Queue->line() . ': ' . $Exception->getMessage());
			}
		}
	}

	/**
	 * @var array
	 */
	private $Stack = [];

	/**
	 * @param string $line
	 * @return string
	 */
	protected final function parse(string $line) : string {
		return preg_replace_callback('/(\W|\A)@(' . Reglib::KEYWORD . ')\s*(.*)$/s', function ($Matches) {
			return $Matches[1] . (!empty($Matches[1]) && !preg_match('/\s+$/', $Matches[1]) ? ' ' : '') .
				(!empty($out = $this->process(strtolower($Matches[2]), $this->analize($Matches[3])))
					? "<?php " . $out . " ?>" : "") . $this->parse($Matches[3]);
		}, $line);
	}

	/**
	 * @param string $line
	 * @return string
	 */
	public final function replace(string $line): string {
		foreach (self::$Traps as $Signature){
			$line = preg_replace_callback('/' . preg_quote($Signature->opening) . '\s*(.+?)\s*'
				. preg_quote($Signature->closing) . '/', function (array $Matches) use ($Signature) {
					return call_user_func($Signature->handler, $Matches[1]); }, $line);
		}

		return $line;
	}

	/**
	 * @param string $token
	 * @param string $condition
	 * @return mixed
	 * @throws \Exception
	 */
	protected final function process(string $token, string $condition){
		if (count($this->Stack) > 0){
			if (($index = (int)array_search($token, $this->Stack[count($this->Stack) - 1])) > 0){

				$Signature = array_values(array_filter(self::$Rules[$this->Stack[count($this->Stack) - 1][0]],
					function(SToken $Signature) use ($token){ return $Signature->token ==  $token; }))[0];

				if ($index < 2){
					array_pop($this->Stack);
				}

				return ($Signature->handler)($condition, $this->Queue);
			}
		}

		if (isset(self::$Rules[$token])) {
			if (self::$Rules[$token][0]->multiline) {
				array_push($this->Stack, array_map(function (SToken $Signature) {
					return $Signature->token;
				}, self::$Rules[$token]));
			}

			return (self::$Rules[$token][0]->handler)($condition, $this->Queue, $this->Source->toPath());
		}

		throw new \Exception('Undefined token @' . $token . '!');
	}

	/**
	 * @param string $source
	 * @return string
	 * @throws \Exception
	 */
	protected final function analize(string &$source): string {
		if (empty($source) || !preg_match('/^\(/', $source)) {
			return $source;
		}

		$original = $source;

		$count = 1;
		$source = substr($source, 1);

		while ($count > 0 && strlen($source) > 0) {
			$source = ltrim(preg_replace('/^(?:' . Reglib::QUOTED . '|[^)(]+)/', '', $source));

			if (!empty($source) && $source[0] == '(') {
				$count++;
			}

			if (!empty($source) && $source[0] == ')') {
				$count--;
			}

			$source = preg_replace('/^[()]/', '', $source);
		}

		if ($count > 0) {
			throw new \Exception('Condition is not completed!');
		}

		return preg_replace('/' . preg_quote($source, '/'). '$/', '', $original);
	}
}

