<?php
namespace Able\Sabre\Exceptions;

use \Able\Exceptions\EDuplicate;

class EDuplicateConfiguration extends EDuplicate {

	/**
	 * @var string
	 */
	protected static string $template = "The configuration is duplicate: %s!";
}
