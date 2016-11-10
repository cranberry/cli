<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI;

use Cranberry\CLI\Format\String;

class Input
{
	/**
	 * @var array
	 */
	protected $arguments=[];

	/**
	 * @var string
	 */
	protected $command;

	/**
	 * @var array
	 */
	protected $options=[];

	/**
	 * @param	array	$arguments
	 */
	public function __construct(array $arguments=[])
	{
		global $argv;

		$this->arguments['command']	  = [];

		$this->options['application'] = [];
		$this->options['command']	  = [];

		if(empty($arguments))
		{
			$arguments = $argv;

			// Grab piped input if present
			stream_set_blocking(STDIN, 0);
			$stdin = stream_get_contents(STDIN);

			if(strlen($stdin) > 0)
			{
				$arguments[] = trim($stdin);
			}
		}

		$this->parseArguments($arguments);
	}

	/**
	 * @return	array
	 */
	public function getApplicationOptions()
	{
		return $this->options['application'];
	}

	/**
	 * @return	string
	 */
	public function getCommand()
	{
		if(!is_null($this->command))
		{
			return $this->command;
		}

		if(isset($this->options['application'][0]))
		{
			return $this->options['application'][0];
		}
	}

	/**
	 * @return	array
	 */
	public function getCommandArguments()
	{
		return $this->arguments['command'];
	}

	/**
	 * @return	array
	 */
	public function getCommandOptions()
	{
		return $this->options['command'];
	}

	/**
	 * @return	none
	 */
	protected function parseArguments(array $arguments)
	{
		array_shift($arguments);

		// Application options are any options before the command
		while(isset($arguments[0]) && substr($arguments[0], 0, 1) == '-')
		{
			// Short option
			if(substr($arguments[0], 1, 1) != '-')
			{
				$option = substr($arguments[0], 1);

				for($i=0; $i<strlen($option); $i++)
				{
					$this->options['application'][] = substr($option, $i, 1);
				}
			}
			// Long option
			else
			{
				$pieces = explode('=', substr($arguments[0], 2));
				$value  = isset($pieces[1]) ? $pieces[1] : true;

				$this->options['application'][$pieces[0]] = $value;
			}

			array_shift($arguments);
		}

		if(count($arguments) == 0)
		{
			return;
		}

		// Command is the first of remaining arguments
		$this->command = array_shift($arguments);

		$i=0;
		while(isset($arguments[$i]))
		{
			// Command options are any options following the command
			if(substr($arguments[$i], 0, 1) == '-')
			{
				// Short option
				if(substr($arguments[$i], 1, 1) != '-')
				{
					$option = substr($arguments[$i], 1);

					for($j=0; $j<strlen($option); $j++)
					{
						$this->options['command'][substr($option, $j, 1)] = true;
					}
				}
				// Long option
				else
				{
					$pieces = explode('=', substr($arguments[$i], 2));
					$value  = isset($pieces[1]) ? $pieces[1] : true;

					$this->options['command'][$pieces[0]] = $value;
				}
			}
			// Command arguments
			else
			{
				$this->arguments['command'][] = $arguments[$i];
			}

			$i++;
		}
	}

	/**
	 * Present a prompt and return the response
	 *
	 * @param	string		$prompt
	 * @param	boolean		$required
	 * @return	string
	 */
	static public function prompt( $prompt, $required=false )
	{
		$promptString = new String( $prompt );

		$output = sprintf(
			'%s ',
			$promptString
		);

		if( $required )
		{
			$response = '';
			while( strlen( $response ) == 0 )
			{
				$response = readline( $output );
			}
		}
		else
		{
			$response = readline( $output );
		}

		return $response;
	}
}
