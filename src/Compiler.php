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

use \Able\Helpers\Str;
use \Able\Helpers\Src;
use PHPUnit\Runner\Exception;

class Compiler {

	/**
	 * @var Buffer[]
	 */
	private static $Prepend = [];

	/**
	 * @param File $File
	 * @throws $Exception
	 */
	public final static function prepend(File $File){
		if (isset(self::$Prepend[$File->toString()])){
			throw new \Exception('File path "' . $File->toString() . '" is already registered!');
		}

		self::$Prepend[$File->toString()] = $File->toBuffer()->process(function (string $value) use ($File) {
			try {
				token_get_all($value, TOKEN_PARSE);
			}catch (\Throwable $exception){
				throw new \Exception('Invalid file syntax: ' . $File->toString());
			}

			return (new Regexp('/\s*\\?>$/'))->erase(trim($value)) . "\n?>\n";
		});
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
	private static $Tokens = [];

	/**
	 * @param SToken $Signature
	 * @throws \Exception
	 */
	public final static function token(SToken $Signature) {
		if (isset(self::$Tokens[$Signature->token])){
			throw new \Exception('Token @' . $Signature->opening . 'already declared!');
		}

		self::$Tokens[$Signature->token] = [$Signature,
			new SToken('end', function(){ return '}'; })];
	}

	/**
	 * @param string $token
	 * @param SToken $Signature
	 * @throws \Exception
	 */
	public final static function extend(string $token, SToken $Signature){
		if (!isset(self::$Tokens[$token = strtolower(trim($token))])){
			throw new \Exception('Unregistered token ' . $token . '!');
		}

		array_push(self::$Tokens[$token], $Signature);
	}

	/**
	 * @param string $token
	 * @param SToken $Signature
	 * @throws \Exception
	 */
	public final static function finalize(string $token, SToken $Signature){
		if (!isset(self::$Tokens[$token = strtolower(trim($token))])){
			throw new \Exception('Unregistered token ' . $token . '!');
		}

		self::$Tokens[$token][1] = $Signature;
	}


	/**
	 * @var Path
	 */
	private $Source = null;

	/**
	 * @var Queue
	 */
	private $Queue = null;

	/**
	 * @param Path $Source
	 * @throws \Exception
	 */
	public final function __construct(Path $Source) {
		if (!$Source->isReadable()){
			throw new \Exception('Source path not exists or not readable!');
		}

		$this->Source = $Source;
		$this->Queue = new Queue();
	}


	/**
	 * @var array
	 */
	private $Stage = [];

	/**
	 * @param File $File
	 * @return \Generator
	 * @throws \Exception
	 */
	public function compile(File $File): \Generator {

		/**
		 * The initially given source file has to be
		 * in the beginning of the compilation queue.
		 */
		$this->Queue->immediately(new Task($File->toReader()));

		/**
		 * Files defined as a prepared php-code fragment have
		 * to be added to the queue before the compilation process begins.
		 *
		 * The queue always proceeds from last added file to first,
		 * so the reverse order is essential.
		 */
		foreach (array_reverse(static::$Prepend) as $Buffer){
			$this->Queue->immediately(new Task($Buffer, Task::F_VERBATIM));
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
	 * @var array
	 */
	private $Stack = [];


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

				$Signature = array_values(array_filter(self::$Tokens[$this->Stack[count($this->Stack) - 1][0]],
					function(SToken $Signature) use ($token){ return $Signature->token ==  $token; }))[0];

				if ($index < 2){
					array_pop($this->Stack);
				}

				return ($Signature->handler)($condition, $this->Queue);
			}
		}

		if (isset(self::$Tokens[$token])) {
			if (self::$Tokens[$token][0]->multiline) {
				array_push($this->Stack, array_map(function (SToken $Signature) {
					return $Signature->token;
				}, self::$Tokens[$token]));
			}

			return (self::$Tokens[$token][0]->handler)($condition, $this->Queue, $this->Source->toPath());
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

