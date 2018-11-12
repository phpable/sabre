<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EInvalid;

class EInvalidToken extends EInvalid {

	/**
	 * @var string
	 */
	protected static string $template = "Undefined command: %s!";
}
