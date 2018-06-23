<?php
namespace Able\Sabre;

use \Able\IO\Abstractions\IReader;

use \Able\IO\Buffer;
use \Able\IO\Path;
use \Able\IO\File;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\Sabre\Structures\SToken;
use \Able\Sabre\Structures\STrap;
use \Able\Sabre\Structures\SState;

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
	 * @var array
	 */
	private static $Hooks = [];

	/**
	 * @param string $hook
	 * @param callable $Handler
	 * @throws \Exception
	 */
	public final static function hook(string $hook, callable $Handler){
		if (isset(self::$Hooks[$hook = strtolower($hook)])){
			throw new \Exception('Hook "' . $hook . '" is already registered!');
		}

		if (!preg_match('/^[A-Za-z0-9(){}\[\]!#$%^&*+~-]{1,5}$/', $hook)){
			throw new \Exception('Invalid hook syntax!');
		}

		self::$Hooks[$hook] = $Handler;
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
	 * @var SState
	 */
	private $State = null;

	/**
	 * @var Queue
	 */
	private $Queue = null;

	/**
	 * @param Path $Source
	 * @throws \Exception
	 */
	public final function __construct(Path $Source) {

		/**
		 * The flags registry is used to determine the current parsing mode.
		 *
		 * It includes some special flags uses by the compiler for each line of a source
		 * file to detect the right way this line has to be processed.
		 *
		 * @see SState
		 */
		$this->State = new SState();

		/**
		 * The default path is used as a root for all non-absolute file paths
		 * so it must exist and be writable.
		 */
		if (!$Source->isReadable()){
			throw new \Exception('The source path does not exist or not readable!');
		}

		$this->Queue = new Queue($Source);
	}


	/**
	 * @var array
	 */
	private $Stage = [];

	/**
	 * @param Path $Path
	 * @return \Generator
	 * @throws \Exception
	 */
	public function compile(Path $Path): \Generator {

		/**
		 * The initially given source file has to be
		 * in the beginning of the compilation queue.
		 */
		$this->Queue->immediately($Path);

		/**
		 * Files defined as a prepared php-code fragment have
		 * to be added to the queue before the compilation process begins.
		 */
		foreach (static::$Prepend as $Buffer){
			foreach ($Buffer->read() as $line) {
				yield $line;
			}
		}

		foreach ($this->Queue->take() as $i => $line) {
			try {
				$this->parseSequences($line);

				/**
				 * The traps have to be parsed in the last place because some of them can be added
				 * dynamically during the commands processing sequences like tokens or hooks.
				 */
				$this->parseTraps($line);

				yield $line;
			} catch (\Exception $Exception) {
				throw new \ErrorException($Exception->getMessage(), 0, 1,
					$this->Queue->file(), $this->Queue->index());
			}
		}
	}

	/**
	 * @param string $line
	 * @return void
	 */
	public final function parseTraps(string &$line): void {
		foreach (self::$Traps as $Signature){
			$line = preg_replace_callback('/' . preg_quote($Signature->opening)
				. '\s*(.+?)\s*' . preg_quote($Signature->closing) . '/',

					function (array $Matches) use ($Signature) {
						return call_user_func($Signature->handler, $Matches[1]); }, $line);
		}
	}

	/**
	 * @param string $line
	 */
	protected final function parseSequences(string &$line) : void {
		$line = preg_replace_callback('/^(.*?\W|\A)(@' . Reglib::KEYWORD . '|' . Str::join('|', array_map(function($value){
			return preg_quote($value, '/'); }, array_keys(self::$Hooks))). ')\s*(.*)$/s', function ($Matches) {
				$output = '';

				/**
				 * If the ignorable mode is switched on, the leading part of the string
				 * before the found match has to be skipped and won't be included in the output.
				 */
				if (!$this->State->ignore) {
					$output .= $Matches[1];
				}

				/**
				 * If the found match is a hook's sequence,
				 * it must be processed in the first place.
				 */
				if ($Matches[2][0] !== '@'){
					Str::cast(self::$Hooks[$Matches[2]]($this->Queue, $this->State));
				} else {

					/**
					 * If the ignorable mode is switched on,
					 * none of the found matches has to be processed.
					 */
					if (!$this->State->ignore) {
						$output .= Str::embrace('<?php', $this->process(strtolower($Matches[2]),
							$this->analize($Matches[3])), "?>", ' ');
					}
				}

				/**
				 * Independent from the flags states, the unparsed part of the line
				 * has to be sent to the further analyzing.
				 */
				$this->parseSequences($Matches[3]);

				return $output . $Matches[3];
		}, $line, 1, $count);

		/**
		 * If none matches found and the ignorable mode is switched on,
		 * the entire string has to be ignored.
		 */
		if ($this->State->ignore && $count < 1){
			$line = '';
		}
	}

	/**
	 * @var array
	 */
	private $Stack = [];

	/**
	 * @param string $token
	 * @param string $condition
	 * @return mixed
	 * @throws \Exception
	 */
	protected final function process(string $token, string $condition): string {
		$token = substr($token, 1);
		if (count($this->Stack) > 0){
			if (($index = (int)array_search($token, $this->Stack[count($this->Stack) - 1])) > 0){

				$Signature = array_values(array_filter(self::$Tokens[$this->Stack[count($this->Stack) - 1][0]],
					function(SToken $Signature) use ($token){ return $Signature->token ==  $token; }))[0];

				if ($index < 2){
					array_pop($this->Stack);
				}

				return Str::cast(($Signature->handler)($condition, $this->Queue));
			}
		}

		if (isset(self::$Tokens[$token])) {
			if (self::$Tokens[$token][0]->multiline) {
				array_push($this->Stack, array_map(function (SToken $Signature) {
					return $Signature->token;
				}, self::$Tokens[$token]));
			}

			return Str::cast((self::$Tokens[$token][0]->handler)($condition, $this->Queue));
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

