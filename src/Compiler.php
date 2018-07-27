<?php
namespace Able\Sabre;

use \Able\IO\Abstractions\IReader;

use \Able\IO\ReadingBuffer;
use \Able\IO\Path;
use \Able\IO\File;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\Sabre\Structures\SToken;
use \Able\Sabre\Structures\STrap;
use \Able\Sabre\Structures\SState;

use \Able\Sabre\Parsers\ArgumentsParser;
use \Able\Sabre\Parsers\BracketsParser;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

use \Able\Helpers\Arr;
use \Able\Helpers\Str;
use \Able\Helpers\Src;

use \Able\Prototypes\IIteratable;

class Compiler {

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

		if (!preg_match('/^[A-Za-z0-9(){}\[\]!#$%^&*+~-]{4,12}$/', $hook)){
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
			new SToken('end', function(){ return '<?php }?>'; })];
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
		$this->Queue = new Queue($Source);

		/**
		 * The flags are used to determine how the source file should be processed.
		 *
		 * The "ignore" flag is responsible for whether or not the processed fragment
		 * will include in the output. It can be pretty useful for particular blocks of data
		 * like comments or notations.
		 *
		 * The "verbatim" flag is responsible for whether or not the found fragment
		 * will be processed. It can be pretty useful in situations when we need
		 * to include some parts of the front-end code to the template, and we don't want
		 * to be worried that the compiler can recognize it mistakenly.
		 *
		 * @see SState
		 */
		$this->State = new SState();
	}

	/**
	 * @param Path $Path
	 * @param ReadingBuffer $RawData
	 * @return \Generator
	 * @throws \Exception
	 */
	public function compile(Path $Path, ReadingBuffer $RawData = null): \Generator {
		if (!is_null($RawData)){

			/**
			 * If any raw data initially given,
			 * it should be output as a matter of priority.
			 */
			yield from $RawData->read();
		}

		/**
		 * The initially given source file should be placed
		 * at the beginning of the compilation queue.
		 */
		$this->Queue->immediately($Path);

		while(!is_null($line = $this->Queue->take())) {
			try {

				$out = '';
				while(strlen(rtrim($line)) > 0) {
					foreach ($this->parse($line) as $i => $fragment) {

						/**
						 * Normally the single line returns by the handler,
						 * but it also possible to have an iterable object here instead.
						 */
						if ($fragment instanceof IIteratable){
							yield $out;

							$out = '';
							foreach ($fragment->iterate() as $item) {
								yield $item;
							}

							continue;
						}

						$out .= $fragment;
					}
				}

				yield $out;

			} catch (\Exception $Exception) {
				throw new \ErrorException($Exception->getMessage(), 0, 1,
					$this->Queue->file(), $this->Queue->index());
			}
		}
	}

	/**
	 * @param string $line
	 * @return \Generator
	 * @throws \Exception
	 */
	protected final function parse(string &$line): \Generator {
		$e = '/^(\A|.*?\W)(?:(@' . Reglib::KEYWORD . '|' . Str::join('|', array_map(function($value){
			return preg_quote($value, '/'); }, array_keys(self::$Hooks))). ')(?:\s*)(.*))?$/s';

		extract(Regexp::create($e)->exec((string)$line, 'prefix', 'token', 'line'));

		if (!empty($prefix)) {
			yield (string)$this->decorate($prefix);
		}

		if (!empty($token)) {
			yield $this->handle($token, substr(BracketsParser::parse($line,
				BracketsParser::BT_CIRCLE, function () { return $this->Queue->take(); }), 1, -1));
		}
	}

	/**
	 * @param string $line
	 * @return null|string
	 */
	public final function decorate(string $line): ?string {
		if ($this->State->ignore){
			return null;
		}

		if ($this->State->verbatim){
			return $line;
		}

		foreach (self::$Traps as $Signature){
			$line = preg_replace_callback('/' . preg_quote($Signature->opening, '/')
				. '\s*(.+?)\s*' . preg_quote($Signature->closing, '/') . '/',

				function (array $Matches) use ($Signature) {
					return call_user_func($Signature->handler, $Matches[1]); }, $line);
		}

		return $line;
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
	protected final function handle(string $token, string $condition) {
		if ($token[0] !== '@') {
			return self::$Hooks[$token]($this->Queue, $this->State);
		}

		if ($this->State->ignore) {
			return null;
		}

		if ($this->State->verbatim) {
			return $token;
		}

		$token = substr($token, 1);
		$Signature = null;

		if (count($this->Stack) > 0
			&& ($index = (int)array_search($token, Arr::last($this->Stack))) > 0){

				$Signature = Arr::first(array_filter(self::$Tokens[Arr::first(Arr::last($this->Stack))],
					function(SToken $Signature) use ($token){ return $Signature->token ==  $token; }));

				if ($index < 2){
					array_pop($this->Stack);
				}

		} elseif (isset(self::$Tokens[$token])) {
			if (Arr::first(self::$Tokens[$token])->multiline) {
				array_push($this->Stack, array_map(function (SToken $Signature) {
					return $Signature->token;
				}, self::$Tokens[$token]));
			}

			$Signature = Arr::first(self::$Tokens[$token]);
		}

		if (is_null($Signature)) {
			throw new \Exception('Undefined token @' . $token . '!');
		}

		return call_user_func_array($Signature->handler,
			Arr::push(Arr::take(ArgumentsParser::parse($condition), $Signature->capacity, null), $this->Queue));
	}
}

