<?php

/*
 * This file is part of Cranberry\CLI\Command
 */
namespace Cranberry\CLI\Command;

class ApplicationOption extends Command
{
	/**
	 * @var string
	 */
	protected $commandName;

	/**
	 * @return	void
	 */
	public function getCommandName()
	{
		return $this->commandName;
	}

	/**
	 * @return	string
	 */
	public function setCommandName( $commandName )
	{
		$this->commandName = $commandName;
	}
}
