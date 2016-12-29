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
	public $phpMinimumVersion;

	/**
	 * @var	string
	 */
	public $name;

	/**
	 * @var	array
	 */
	public $options=[];

	/**
	 * @var	string
	 */
	public $version;

	/**
	* @param	string							$name
	* @param	string							$version
	* @param	string							$phpMinimumVersion
	* @param	Cranberry\Core\File\Directory	$dirApp
	 */
	public function __construct( $name, $version, $phpMinimumVersion, File\Directory $applicationDirectory )
	{
		$this->name = $name;
		$this->version = $version;
		$this->phpMinimumVersion = $phpMinimumVersion;
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
	 * Register options in Options mirror
	 *
	 * @param	array	$options
	 */
	public function registerOptions( array $options )
	{
		foreach( $options as $option )
		{
			$optionName = $option->getName();
			$optionName = "-{$optionName}";

			if( strlen( $optionName ) > 2 )
			{
				$optionName = "-{$optionName}";
			}

			$optionMirror = new Command\Mirror( $optionName, $option->getDescription() );
			$this->options[] = $optionMirror;
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
