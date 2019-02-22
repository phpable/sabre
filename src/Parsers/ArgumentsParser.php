<?php
namespace Able\Sabre\Parsers;

use \Able\Statics\TStatic;

use \Able\Reglib\Regex;

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
				$fragment .= Regex::create('/^(?:' . Regex::RE_QUOTED
					. '|[^({\[\'",]+)/')->retrieve($source) . BracketsParser::parse($source);
			}

			Regex::create('/^\s*,+\s*/')->retrieve($source);
			if (!empty($fragment)) {
				array_push($Args, $fragment);
			}
		}

		return $Args;
	}
}
