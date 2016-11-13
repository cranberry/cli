<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Application;

use Cranberry\CLI\Command;
use Cranberry\Core\File;

class Mirror
{
	/**
	 * @var	Cranberry\Core\File\Directory
	 */
	public $applicationDirectory;

	/**
	 * @var	array
	 */
	public $commands=[];

	/**
	 * @var	Cranberry\Core\File\Directory
	 */
	public $dataDirectory;

	/**
	 * @var	string
	 */
	public $name;

	/**
	 * @var	string
	 */
	public $version;

	/**
	 * @param	string	$name
	 * @param	string	$version
	 * @return	void
	 */
	public function __construct( $name, $version, File\Directory $applicationDirectory )
	{
		$this->name = $name;
		$this->version = $version;
		$this->applicationDirectory = $applicationDirectory;
	}

	/**
	 * Register commands in Commands mirror
	 *
	 * @param	array	$commands
	 */
	public function registerCommands( array $commands )
	{
		foreach( $commands as $command )
		{
			$commandMirror = new Command\Mirror( $command->getName(), $command->getDescription() );
			$commandMirror->setAliases( $command->getAliases() );
			$commandMirror->setUsage( $command->getUsage() );

			$this->commands[] = $commandMirror;
		}
	}

	/**
	 * @param
	 * @return	void
	 */
	public function setDataDirectory( File\Directory $dataDirectory=null )
	{
		$this->dataDirectory = $dataDirectory;
	}
}
