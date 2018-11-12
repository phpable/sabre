<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EOutranged;

class ECapacityOutranged extends EOutranged {

	/**
	 * @var string
	 */
	protected static string $template = "The capacity is out of range: %d!";
}
