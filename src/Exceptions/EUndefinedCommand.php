<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EUndefined;

class EUndefinedCommand extends EUndefined {

	/**
	 * @var string
	 */
	protected static string $template = "Undefined command: %s!";
}
