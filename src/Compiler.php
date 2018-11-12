<?php
namespace Able\Sabre;

use \Able\IO\Reader;
use \Able\IO\Abstractions\IReader;

use \Able\Sabre\Exceptions\EDuplicateConfiguration;
use \Able\Sabre\Exceptions\EInvalidConfiguration;
use \Able\Sabre\Exceptions\EUndefinedCommand;

use \Able\Sabre\Utilities\Queue;
use \Able\Sabre\Utilities\Task;

use \Able\Sabre\Structures\SCommand;
use \Able\Sabre\Structures\SInjection;
use \Able\Sabre\Structures\SState;

use \Able\Sabre\Parsers\ArgumentsParser;
use \Able\Sabre\Parsers\BracketsParser;

use \Able\Reglib\Regex;

use \Able\Helpers\Arr;
use \Able\Helpers\Str;
use \Able\Helpers\Src;

use \Able\Prototypes\IIteratable;
use \Exception;

class Compiler {

	/**
	 * @var callable[]
	 */
	private array $Directives = [];

	/**
	 * Registers the given sequence as a directive.
	 *
	 * @param string $token
	 * @param callable $Handler
	 * @return void
	 *
	 * @throws Exception
	 */
	public final function directive(string $token, callable $Handler): void {
		if (isset($this->Directives[$token = strtolower($token)])){
			throw new EDuplicateConfiguration($token);
		}

		if (!preg_match('/^[A-Za-z0-9(){}\[\]!#$%^&*+~-]{4,12}$/', $token)){
			throw new EInvalidConfiguration($token);
		}

		$this->Directives[$token] = $Handler;
	}

	/**
	 * @var SInjection[]
	 */
	private array $Injections = [];

	/**
	 * Registers a new injection by the given signature.
	 *
	 * @param SInjection $Signature
	 * @return void
	 *
	 * @throws EDuplicateConfiguration
	 */
	public final function injection(SInjection $Signature): void {
		if (isset($this->Injections[$Signature->getHash()])){
			throw new EDuplicateConfiguration($Signature->toString());
		}

		$this->Injections[$Signature->toString()] = $Signature;
	}

	/**
	 * @var SCommand[]
	 */
	private array $Commands = [];

	/**
	 * Registers a new command by the given signature.
	 *
	 * @param SCommand $Signature
	 * @throws Exception
	 */
	public final function command(SCommand $Signature) {
		if (isset($this->Commands[$Signature->token])){
			throw new EDuplicateConfiguration($Signature->token);
		}

		/**
		 * Registers the default closing logic
		 * for new commands (can be overloaded any time late).
		 */
		$this->Commands[$Signature->token] = [$Signature,
			new SCommand('end', function(){ return '<?php }?>'; })];
	}

	/**
	 * Overrides the default finalization logic
	 * for an existing command.
	 *
	 * @param string $token
	 * @param SCommand $Signature
	 *
	 * @throws EUndefinedCommand
	 */
	public final function extend(string $token, SCommand $Signature){
		if (!isset($this->Commands[$token = strtolower(trim($token))])){
			throw new EUndefinedCommand($token);
		}

		array_push($this->Commands[$token], $Signature);
	}

	/**
	 * Overrides the finalization logic for an existing command.
	 *
	 * @param string $token
	 * @param SCommand $Signature
	 *
	 * @throws EUndefinedCommand
	 */
	public final function finalize(string $token, SCommand $Signature){
		if (!isset($this->Commands[$token = strtolower(trim($token))])){
			throw new EUndefinedCommand($token);
		}

		$this->Commands[$token][1] = $Signature;
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
	 * @var array
	 */
	private $History = [];

	/**
	 * @return string[]
	 */
	public final function history(): array {
		return $this->History;
	}

	/**
	 * @throws Exception
	 */
	public final function __construct() {
		$this->Queue = new Queue(function(string $filepath){
			array_push($this->History, $filepath);
		});

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
	 * @param IReader $Reader
	 * @return \Generator
	 * @throws Exception
	 */
	public function compile(IReader $Reader): \Generator {
		/**
		 * Can't compile new sources until
		 * the current compilation queue is empty!
		 */
		if (!$this->Queue->empty()){
			throw new Exception('The compilation queue is not empty!');
		}

		/**
		 * The initial source should always be placed
		 * at the beginning of the compilation queue.
		 */
		$this->Queue->immediately($Reader);

		$this->History = [];
		while(!is_null($line = $this->Queue->take())) {
			try {

				$out = '';
				while (strlen(rtrim($line)) > 0) {
					foreach ($this->parse($line) as $i => $fragment) {

						/**
						 * Normally the single line returns by the handler,
						 * but it also possible to have an iterable object here instead.
						 */
						if ($fragment instanceof IIteratable) {
							yield Str::rtrim($out);

							$out = '';
							foreach ($fragment->iterate() as $item) {
								yield Str::ltrim($item);
							}

							continue;
						}

						$out .= $fragment;
					}
				}

				yield Str::ltrim($out);
			}catch (\ErrorException $Exception){
				throw new \ErrorException($Exception->getMessage(), 0, 1,
					$Exception->getFile(), $Exception->getLine());

			} catch (Exception $Exception) {
				throw new \ErrorException($Exception->getMessage(), 0, 1,
					$this->Queue->file(), $this->Queue->index());
			}
		}
	}

	/**
	 * @param string $line
	 * @return \Generator
	 * @throws Exception
	 */
	protected final function parse(string &$line): \Generator {
		$List = Arr::sort(array_unique(array_map(function(SCommand $Token){ return $Token->token; },
			Arr::simplify($this->Commands))), function($a, $b){ return strlen($b) - strlen($a); });

		/**
		 * @todo Refactoring needed.
		 * Undesirable behavior when the list of tokens is empty.
		 */
		extract(Regex::create('/^(.*?)(?:((?:(?<=\A|\W)@(?:' . Str::join('|', $List) . '))|' . Str::join('|', array_map(function($value){
			return preg_quote($value, '/'); }, array_keys($this->Directives))). ')(?:\s*)(.*))?$/s')->parse((string)$line, 'prefix', 'token', 'line'));

		if (!empty($prefix)) {
			yield (string)$this->decorate($prefix);
		}

		if (!empty($token)) {
			yield $this->handle($token, $line);
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

		foreach (Arr::sort($this->Injections, function(SInjection $f, SInjection $s){
				return strlen($s->opening) - strlen($f->opening);
			}) as $Signature){

			$line = preg_replace_callback('/' . preg_quote($Signature->opening, '/')
				. '\s*(.+?)\s*' . preg_quote($Signature->closing, '/') . '/', function (array $Matches) use ($Signature) {
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
	 * @param string $line
	 * @return mixed
	 * @throws Exception
	 */
	protected final function handle(string $token, string &$line) {
		if ($token[0] !== '@') {
			return $this->Directives[$token]($this->Queue, $this->State);
		}

		if ($this->State->ignore) {
			return null;
		}

		if ($this->State->verbatim) {
			return $token;
		}

		$token = substr($token, 1);
		$condition = substr(BracketsParser::parse($line, BracketsParser::BT_CIRCLE,
			function () { return $this->Queue->take(); }), 1, -1);

		$Signature = null;
		if (count($this->Stack) > 0
			&& ($index = (int)array_search($token, Arr::last($this->Stack))) > 0){

				$Signature = Arr::first(array_filter($this->Commands[Arr::first(Arr::last($this->Stack))],
					function(SCommand $Signature) use ($token){ return $Signature->token ==  $token; }));

				if ($index < 2){
					array_pop($this->Stack);
				}

		} elseif (isset($this->Commands[$token])) {
			if (Arr::first($this->Commands[$token])->multiline) {
				array_push($this->Stack, array_map(function (SCommand $Signature) {
					return $Signature->token;
				}, $this->Commands[$token]));
			}

			$Signature = Arr::first($this->Commands[$token]);
		}

		if (is_null($Signature)) {
			throw new \ErrorException('Undefined token @' . $token . '!', 0, 1,
				$this->Queue->file(), $this->Queue->index());
		}

		$Args = ArgumentsParser::parse($condition);
		if ($Signature->capacity != count($Args)){
			$Args = Arr::take($Args, $Signature->capacity, null);
		}

		$Args = Arr::push($Args, $this->Queue);
		if ($Signature->composite){
			$Args = Arr::push($Args, clone $this);
		}

		return call_user_func_array($Signature->handler, $Args);
	}

	/**
	 * Clone compiler instance and restore it
	 * to the initial state.
	 */
	public final function __clone() {
		$this->Queue = clone $this->Queue;
		$this->Queue->flush();

		$this->State = clone $this->State;
		$this->State->flush();

		$this->Stack = [];
	}
}

