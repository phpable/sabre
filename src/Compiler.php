<?php
namespace Able\Sabre;

use \Generator;

use \Able\IO\Abstractions\IReader;
use \Able\Sabre\Utilities\SSignature;

use \Eggbe\Reglib\Reglib;

class Compiler {

	/**
	 * @var array
	 */
	private static $Rules = [];

	/**
	 * @param SSignature $Signature
	 * @throws \Exception
	 */
	public final static function register(SSignature $Signature) {
		if (isset(self::$Rules[$Signature->token])){
			throw new \Exception('Token @' . $Signature->opening . 'already declared!');
		}

		self::$Rules[$Signature->token] = [$Signature,
			new SSignature('end', function(){ return '}'; })];
	}

	/**
	 * @param string $token
	 * @param SSignature $Signature
	 * @throws \Exception
	 */
	public final static function extend(string $token, SSignature $Signature){
		if (!isset(self::$Rules[$token = strtolower(trim($token))])){
			throw new \Exception('Unregistered token ' . $token . '!');
		}

		array_push(self::$Rules[$token], $Signature);
	}

	/**
	 * @var array
	 */
	private $Stack = [];

	/**
	 * @param IReader $Reader
	 * @return Generator
	 * @throws \Exception
	 */
	public function compile(IReader $Reader): Generator {
		foreach ($Reader->read() as $index => $line){
			try {
				yield $this->parse($this->replace($line));
			} catch (\Exception $Exception){
				throw new \Exception('Error in ' . (string)$Reader
					. ' on ' . $index . ': ' . $Exception->getMessage());
			}
		}
	}

	protected final function parse(string $line) : string {
		return preg_replace_callback('/(\W|\A)@(' . Reglib::KEYWORD . ')\s*(.*)$/s', function ($Matches) {
			return $Matches[1] . (!empty($Matches[1]) && !preg_match('/\s+$/', $Matches[1]) ? ' ' : '')
				. "<?php " . $this->process(strtolower($Matches[2]), $this->analize($Matches[3])) . " ?>"
					. $this->parse($Matches[3]);
		}, $line);
	}

	/**
	 * @param string $line
	 * @return string
	 */
	public final function replace(string $line): string {
		return preg_replace_callback('/[{]{2}([^}]*)[}]{2}/', function ($Matches) {
			return '<?=' . trim($Matches[1]) . ';?>'; }, $line);
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
					function(SSignature $Signature) use ($token){ return $Signature->token ==  $token; }))[0];

				if ($index < 2){
					array_pop($this->Stack);
				}

				return ($Signature->handler)($condition);
			}
		}

		if (isset(self::$Rules[$token])) {
			if (self::$Rules[$token][0]->multiline) {
				array_push($this->Stack, array_map(function (SSignature $Signature) {
					return $Signature->token;
				}, self::$Rules[$token]));
			}

			return (self::$Rules[$token][0]->handler)($condition);
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
			throw new \Exception('Condition is not complegted!');
		}

		return preg_replace('/' . preg_quote($source, '/'). '$/', '', $original);
	}
}

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::register(new SSignature('if', function ($condition) {
	return 'if ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SSignature('elseif', function ($condition) {
	return '} elseif ' . $condition . ' {';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::extend('if', new SSignature('else', function ($condition) {
	return '} else {';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::register(new SSignature('for', function ($condition) {
	return 'for ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::register(new SSignature('foreach', function ($condition) {
	return 'foreach ' . $condition . '{';
}));

/** @noinspection PhpUnhandledExceptionInspection */
Compiler::register(new SSignature('param', function ($condition) {
	$Params = array_map(function(string $value){ return trim($value); }, preg_split('/,+/',
		substr($condition, 1, strlen($condition) - 2), 2, PREG_SPLIT_NO_EMPTY));

	if (!preg_match('/\$' . Reglib::VAR. '/', $Params[0])){
		throw new \Exception('Invalid parameter name!');
	}

	return 'if (!isset(' . $Params[0] . ')){ ' . $Params[0] . ' = '
		. (isset($Params[1]) ? $Params[1] : 'null') . '; }';
}, false));
