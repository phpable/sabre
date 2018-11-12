<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EInvalid;

class EInvalidConfiguration extends EInvalid {

	/**
	 * @var string
	 */
	protected static string $template = "Invalid configuration: %s!";
}
