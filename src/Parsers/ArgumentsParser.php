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

		if (!empty($source = preg_replace('/^[\s,]+/', '', $source))) {
			while (!empty($source)) {

				$fragment = '';
				while (strlen($source) > 0 && !preg_match('/^\s*,+/', $source)) {

					$fragment .= BracketsParser::parse($source)
						. Regexp::create('/^(?:' . Reglib::QUOTED . '|[^({\[\'",]+)/')->retrieve($source);
				}

				Regexp::create('/^\s*,+\s*/')->retrieve($source);

				if (!empty($fragment)) {
					array_push($Args, $fragment);
				}
			}
		}

		return $Args;
	}
}
