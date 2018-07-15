<?php
namespace Able\Sabre\Parsers;

use \Able\Prototypes\TStatic;

use \Able\Reglib\Regexp;
use \Able\Reglib\Reglib;

final class ArgumentsParser {
	use TStatic;

	/**
	 * @param string $source
	 * @return array
	 * @throws \Exception
	 */
	public static final function parse(string &$source): array {
		$Args = [];

		while (!empty($source)) {
			$fragment = '';

			while (strlen($source) > 0 && !preg_match('/^\s*,+/', $source)) {
				$fragment .= Regexp::create('/^(?:' . Reglib::QUOTED
					. '|[^({\[\'",]+)/')->retrieve($source) . BracketsParser::parse($source);
			}

			Regexp::create('/^\s*,+\s*/')->retrieve($source);
			if (!empty($fragment)) {
				array_push($Args, $fragment);
			}
		}

		return $Args;
	}
}
