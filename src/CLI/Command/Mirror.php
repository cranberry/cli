<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Command;

class Mirror
{
	/**
	 * @var	array
	 */
	public $aliases=[];

	/**
	 * @var	string
	 */
	public $description;

	/**
	 * @var	string
	 */
	public $name;

	/**
	 * @var	array
	 */
 	public $subcommands=[];

	/**
	 * @var	string
	 */
	public $usage;

	/**
	 * @param	string	$name
	 * @param	string	$description
	 */
	public function __construct( $name, $description )
	{
		$this->name = $name;
		$this->description = $description;
	}

	/**
	 * @param	array	$aliases
	 */
	public function setAliases( array $aliases )
	{
		$this->aliases = $aliases;
	}

	/**
	 * @param	string	$usage
	 */
	public function setUsage( $usage )
	{
		$this->usage = $usage;
	}
}
