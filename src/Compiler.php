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
	 * @var ReadingBuffer[]
	 */
	private $RawData = [];

	/**
	 * @param ReadingBuffer $Buffer
	 */
	public final function addRawData(ReadingBuffer $Buffer){
		array_push($this->RawData, $Buffer);
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
		 * The flags are used to determine the current parsing mode
		 * and accordingly the way in that source strings will process.
		 *
		 * The ignore flag is responsible for whether or not the processed
		 * fragment will include in the output. It can be useful for particular
		 * blocks like comments or notations.
		 *
		 * The verbatim flag is responsible for whether or not the found match will process.
		 * It can be useful in situations when we need to include any part of a front-end code to the template,
		 * but we worried about this fragment can be mistakenly recognized by the template compiler.
		 *
		 * @see SState
		 */
		$this->State = new SState();
	}


	/**
	 * @var array
	 */
	private $Stage = [];

	/**
	 * @param Path $Path
	 * @param ReadingBuffer $RawData
	 * @return \Generator
	 * @throws \Exception
	 */
	public function compile(Path $Path, ReadingBuffer $RawData = null): \Generator {
		if (!is_null($RawData)){
			$this->addRawData($RawData);
		}

		/**
		 * The initially given source file should be at the beginning
		 * of the compilation queue.
		 */
		$this->Queue->immediately($Path);

		while(!is_null($line = $this->Queue->take())) {
			try {
				$this->parseSequences($line);

				/**
				 * If any raw data was added during the iteration, it should be output
				 * as a matter of priority.
				 */
				while(count($this->RawData) > 0) {
					foreach (Arr::first($this->RawData)->read() as $output){
						yield $output;
					}

					array_shift($this->RawData);
				}

				yield trim($line);
			} catch (\Exception $Exception) {
				throw new \ErrorException($Exception->getMessage(), 0, 1,
					$this->Queue->file(), $this->Queue->index());
			}
		}
	}

	/**
	 * @param string $line
	 */
	protected final function parseSequences(string &$line) : void {
		$line = preg_replace_callback('/^(.*?(?:\s|\A))(@' . Reglib::KEYWORD . '|' . Str::join('|', array_map(function($value){
			return preg_quote($value, '/'); }, array_keys(self::$Hooks))). ')(\s*)(.*)$/s', function ($Matches) {
				$output = '';

				/**
				 * If the ignore mode is switched on, the part of the string before the found match
				 * must be skipped out and doesn't need to include in the final output.
				 */
				if (!$this->State->ignore) {

					/**
					 * If the verbatim mode is switched on, the part of the string
					 * before the found match doesn't need to be processed
					 */
					$output .= !$this->State->verbatim
						? $this->parseTraps($Matches[1]) : $Matches[1];
				}

				/**
				 * If the match is a hook's sequence, it needs to process
				 * in the first place because hooks can change the flags states
				 * and eventually the way of further processing.
				 */
				if ($Matches[2][0] !== '@') {
					Str::cast(self::$Hooks[$Matches[2]]($this->Queue, $this->State));
				} else {

					/**
					 * If the ignore mode is switched on, none of the
					 * matches needs to process
					 */
					if (!$this->State->ignore) {

						/**
						 * If the verbatim mode is switched on, the string
						 * doesn't need to process.
						 */
						if (!$this->State->verbatim) {
							$output .= Str::embrace('<?php', $this->handle(strtolower(substr($Matches[2], 1)),
								substr(trim(BracketsParser::parse($Matches[4], BracketsParser::BT_CIRCLE, function(){
									return $this->Queue->take(); })), 1, -1)), "?>", ' ');

						} else {
							$output .= $Matches[2] . $Matches[3];
						}
					}
				}

				/**
				 * Independent from the flags states, the unparsed part of the string
				 * needs to be subjected to further analyzing.
				 */
				$this->parseSequences($Matches[4]);

				return $output . $Matches[4];
		}, $line, 1, $count);

		/**
		 * If none match found and the ignore mode is switched on,
		 * the entire string needs to be ignored.
		 */
		if ($this->State->ignore && $count < 1){
			$line = '';
		}

		/**
		 * If none match found and the verbatim mode is switched off
		 * the string needs to be processed by a traps parser because
		 * it still can consist a trap's injection inside.
		 */
		if (!$this->State->verbatim && $count < 1){
			$line = $this->parseTraps($line);
		}
	}

	/**
	 * @param string $line
	 * @return string
	 */
	public final function parseTraps(string $line): string {
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
	protected final function handle(string $token, string $condition): string {
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

		return Str::cast(call_user_func_array($Signature->handler,
			Arr::push(Arr::take(ArgumentsParser::parse($condition), $Signature->capacity, null), $this->Queue)));
	}

}

