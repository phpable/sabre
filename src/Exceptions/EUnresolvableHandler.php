<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EUnresolvable;

class EUnresolvableHandler extends EUnresolvable {

	/**
	 * @var string
	 */
	protected static string $template = "Unresolvable handler!";
}
