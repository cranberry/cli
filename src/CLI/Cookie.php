<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI;

use Cranberry\Core\File;

class Cookie
{
	/**
	 * @var	array
	 */
	protected $contents=[];

	/**
	 * @var	Cranberry\Core\File\File
	 */
	protected $fileCookies;

	/**
	 * @param	Cranberry\Core\File\File		$fileCookies
	 * @return	void
	 */
	public function __construct( File\File $fileCookies )
	{
		if( $fileCookies->exists() )
		{
			$this->contents = parse_ini_file( $fileCookies, true );
		}

		$this->fileCookies = $fileCookies;
	}

	/**
	 * @param	string	$section
	 * @param	string	$name
	 * @return	void
	 */
	public function delete( $section, $name )
	{
		unset( $this->contents[$section][$name] );
		$this->write();
	}

	/**
	 * @param	string	$section
	 * @param	string	$name
	 * @return	mixed
	 */
	public function get( $section, $name )
	{
		if( isset( $this->contents[$section][$name] ) )
		{
			return $this->contents[$section][$name];
		}
	}

	/**
	 * @param	string	$section
	 * @param	string	$name
	 * @param	mixed	$value
	 * @return	void
	 */
	public function set( $section, $name, $value )
	{
		$this->contents[$section][$name] = $value;
		$this->write();
	}

	/**
	 * @return	void
	 */
	protected function write()
	{
		/* Convert to INI format */
		$fileContents = '';

		foreach( $this->contents as $section => $contents )
		{
			if( count( $contents ) > 0 )
			{
				$fileContents .= "[{$section}]" . PHP_EOL;

				foreach( $contents as $name => $value )
				{
					$fileContents .= "{$name} = \"{$value}\"" . PHP_EOL;
				}
			}
		}

		$this->fileCookies->putContents( $fileContents );
	}
}
