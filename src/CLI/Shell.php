<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI;

class Shell
{
	/**
	 * Execute an external command, generate friendly output and return the result
	 *
	 * @param	string	$command
	 * @param	boolean	$formatted	Return formatted output in addition to raw
	 * @param	string	$prefix
	 * @return	array
	 */
	static public function exec( $command, $formatted=false, $prefix='> ' )
	{
		$commandOriginal = $command;
		$command = $command . ' 2>&1';	// stifle output; we'll include it in the returned array

		$result = exec( $command, $commandOutput, $exitCode );

		$output['raw'] = '';

		if( $formatted )
		{
			$output['formatted'] = '';
			$color = $exitCode == 0 ? 'green' : 'red';

			$formattedString = new Format\String;
			$formattedString->foregroundColor( $color );
		}

		$formattedString = new Format\String;
		$formattedString->foregroundColor( $color );

		foreach( $commandOutput as $line )
		{
			$output['raw'] .= $line . PHP_EOL;

			if( $formatted )
			{
				$formattedString->setString( $prefix . $line );
				$output['formatted'] .= $formattedString . PHP_EOL;
			}
		}

		$output['raw'] = trim( $output['raw'] );

		return [
			'command' => $commandOriginal,
			'output' => $output,
			'exitCode' => $exitCode
		];
	}

	/**
	 * Gets the value of an environment variable
	 *
	 * @param	string	$varname	The variable name
	 * @return	string
	 */
	static public function getenv( $varname )
	{
		return getenv( $varname );
	}
}
