<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Command;

class InvalidCommandException extends \OutOfBoundsException
{
	const UNDEFINED		= 1;
	const UNSPECIFIED	= 2;
}

?>
