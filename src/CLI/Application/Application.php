<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Application;

use Cranberry\CLI;
use Cranberry\CLI\Command;
use Cranberry\CLI\Output;
use Cranberry\Core\File;

class Application
{
	/**
	 * @var array
	 */
	protected $aliases=[];

	/**
	 * @var	Cranberry\Core\File\Directory
	 */
	protected $applicationDirectory;

	/**
	 * @var	array
	 */
	protected $cleanupActions=[];

	/**
	 * @var array
	 */
	protected $commands=[];

	/**
	 * @var	Cranberry\Core\File\Directory
	 */
	protected $dataDirectory;

	/**
	 * @var Cranberry\CLI\Command\Command
	 */
	protected $defaultCommand;

	/**
	 * @var array
	 */
	protected $executableOptions=[];

	/**
	 * @var int
	 */
	protected $exit=0;

	/**
	 * @var Input
	 */
	protected $input;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var Cranberry\CLI\Output\Output
	 */
	protected $output;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * @param	string							$name				Application name
	 * @param	string							$version
	 * @param	string							$phpMinimumVersion
	 * @param	Cranberry\Core\File\Directory	$dirApp
	 */
	public function __construct( $name, $version, $phpMinimumVersion, File\Directory $applicationDirectory )
	{
		$this->output  = new Output\Output();
		$this->input   = new CLI\Input();

		if( version_compare( PHP_VERSION, $phpMinimumVersion, '>=' ) == false )
		{
			$this->exit = 1;
			$this->output->line( "{$name}: Requires PHP {$phpMinimumVersion}+, found " . PHP_VERSION );
			$this->stop();
		}

		$this->name    = $name;
		$this->version = $version;

		/* Application root directory */
		$this->applicationDirectory = $applicationDirectory;

		/* Default Command */
		$this->defaultCommand = new Command\Command( 'default', 'A placeholder command', function(){ } );

		/* Cookie Controller */
		$fileCookies = $this->applicationDirectory->child( '.cookies' );
		$this->cookies = new CLI\Cookie( $fileCookies );
	}

	/**
	 * @param	Cranberry\CLI\Command\Command	$command
	 * @param	array	$arguments
	 * @return	mixed
	 */
	public function callCommand( Command\Command $command, $arguments=[] )
	{
		// Attempt calling subcommand first
		if(count($arguments) > 0)
		{
			// Subcommand is registered
			if(is_null($command->getSubcommand($arguments[0])) == false)
			{
				$command = $command->getSubcommand($arguments[0]);
				array_shift($arguments);
			}
		}

		// Number of items in $arguments may not be fewer
		// than number of required closure parameters
		$rf = new \ReflectionFunction($command->getClosure());

		if($rf->getNumberOfRequiredParameters() > count($arguments))
		{
			throw new \BadFunctionCallException(sprintf
			(
				"Missing arguments for '%s': %s expected, %s given",
				$command->getName(),
				$rf->getNumberOfRequiredParameters(),
				count($arguments)
			));
		}

		foreach($this->input->getCommandOptions() as $key => $value)
		{
			$command->setOptionValue($key, $value);
		}

		/* App Mirror */
		$appMirror = new Mirror( $this->name, $this->version, $this->applicationDirectory );
		$appMirror->setDataDirectory( $this->dataDirectory );
		$appMirror->registerCommands( $this->commands );
		$appMirror->registerOptions( $this->executableOptions );

		$command->setApplicationMirror( $appMirror );
		$command->setOutput( $this->output );

		/* Lazy-load Cookies controller */
		$command->registerCookieController( $this->cookies );

		return call_user_func_array($command->getClosure(), $arguments);
	}

	/**
	 * @return	void
	 */
	public function cleanUpSelf()
	{
		/* Check cookies to see if cleanup has been performed for this version */
		$versionSlug = 'v' . str_replace( '.', '_', $this->version );
		$didPerformCleanup = $this->cookies->get( 'cleanup', $versionSlug ) == true;

		if( $didPerformCleanup )
		{
			return;
		}

		foreach( $this->cleanupActions as $cleanupVersion => $cleanupAction )
		{
			/* Even though a newer definition *shouldn't* ever appear, only perform
			   cleanup actions up to the current version just to be safe... */
			if( version_compare( $cleanupVersion, $this->version, "<=" ) )
			{
				$filesToDelete = call_user_func( $cleanupAction, $this->applicationDirectory );

				/* Some cleanup actions might not require file deletion */
				if( !is_array( $filesToDelete ) )
				{
					continue;
				}

				foreach( $filesToDelete as $file )
				{
					if( $file->exists() )
					{
						$file->delete();
					}
				}
			}
		}

		/* Write to cookies file */
		$this->cookies->set( 'cleanup', $versionSlug, "true" );
	}

	/**
	 * Display help information about a registered command
	 *
	 * @param	string	$commandName
	 * @return	string
	 */
	public function commandHelp($commandName=null)
	{
		$output = new Output\Output();

		if(is_null($commandName))
		{
			return $this->getUsage();
		}

		$command     = $this->getCommand($commandName);
		$subcommands = $command->getSubcommands();

		if(count($subcommands) == 0)
		{
			$output->line( sprintf( 'usage: %s %s', $this->name, $command->getUsage() ) );
			return $output->flush();
		}

		$help = <<<OUTPUT
usage: %s

Subcommands for '{$commandName}' are:
OUTPUT;

		$usage = '';
		$descriptions = '';

		foreach($subcommands as $command)
		{
			$usage .= sprintf
			(
				"%s \033[4;29m%s\033[0m %s",
				$this->name,
				$commandName,
				str_replace( $command->getName(), "\033[4;29m{$command->getName()}\033[0m", $command->getUsage() )
			) . PHP_EOL . '       ';

			$output->indentedLine( sprintf('   %-11s%s', $command->getName(), $command->getDescription() ), 14 );
		}

		$help = sprintf( $help, trim( $usage ) );

		$output->unshiftLine( $help );
		return $output->flush();
	}

	/**
	 * @param	string	$name
	 * @return	string
	 */
	protected function getCommand($name)
	{
		if(!isset($this->commands[$name]))
		{
			// check known aliases
			if(isset($this->aliases[$name]))
			{
				if(isset($this->commands[$this->aliases[$name]]))
				{
					return $this->commands[$this->aliases[$name]];
				}
			}

			throw new Command\InvalidCommandException("Unknown command '{$name}'", Command\InvalidCommandException::UNDEFINED);
		}

		return $this->commands[$name];
	}

	/**
	 * @param	string	$name
	 * @return	Cranberry\CLI\Command\Command
	 */
	protected function getExecutableOption( $name )
	{
		if( !isset( $this->executableOptions[$name] ) )
		{
			throw new Command\InvalidCommandException( "Unknown option '{$name}'", Command\InvalidCommandException::UNDEFINED );
		}

		return $this->executableOptions[$name];
	}

	/**
	 * @param	string		$version
	 * @param	Closure		$action
	 * @return	void
	 */
	public function registerCleanupAction( $version, \Closure $action )
	{
		$this->cleanupActions[$version] = $action;
	}

	/**
	 * @param	Cranberry\Command\Command	$command
	 */
	public function registerCommand( Command\Command $command )
	{
		$this->commands[$command->getName()] = $command;

		$aliases = $command->getAliases();
		foreach($aliases as $alias)
		{
			$this->aliases[$alias] = $command->getName();
		}

		if( $command->useAsApplicationDefault() )
		{
			$this->defaultCommand = $command;
		}
	}

	/**
	 * @param	array	$commandFiles	An array of Cranberry\Core\File\File objects
	 */
	public function registerCommandFiles( array $commandFiles )
	{
		foreach( $commandFiles as $commandFile )
		{
			$command = include_once( $commandFile );
			if( $command instanceof Command\Command )
			{
				$this->registerCommand( $command );
			}
		}
	}

	/**
	 * @param	Cranberry\Command\Command	$option
	 */
	public function registerExecutableOption( Command\ApplicationOption $option )
	{
		$this->executableOptions[$option->getName()] = $option;

		if( $option->useAsApplicationDefault() )
		{
			$this->defaultCommand = $option;
		}
	}

	/**
	 * @param	array	$optionFiles	An array of Cranberry\Core\File\File objects
	 */
	public function registerExecutableOptionFiles( array $optionFiles )
	{
		foreach( $optionFiles as $optionFile )
		{
			$option = include_once( $optionFile );

			if( $option instanceof Command\ApplicationOption )
			{
				$this->registerExecutableOption( $option );
			}
		}
	}

	/**
	 *
	 */
	public function run()
	{
		ksort($this->commands);

		$commandName = $this->input->getCommand();
		$command = $this->defaultCommand;

		try
		{
			/*
			 * Executable options (ex., '--version')
			 */
			$applicationOptionValues = $this->input->getApplicationOptions();

			if( count( $applicationOptionValues ) > 0 )
			{
				// The first option value set is our executable option candidate
				$applicationOptionName = key( $applicationOptionValues );

				if( isset( $this->executableOptions[$applicationOptionName] ) )
				{
					$command = $this->getExecutableOption( $applicationOptionName );
					$command->setCommandName( $commandName );
				}
			}

			/*
			 * Command
			 */
			else
			{
				if( !is_null( $commandName ) )
				{
					$command = $this->getCommand( $commandName );
				}
			}

			$this->callCommand( $command, $this->input->getCommandArguments() );
		}

		catch( Command\InvalidCommandException $e )
		{
			switch($e->getCode())
			{
				case Command\InvalidCommandException::UNDEFINED:

					$outputContents = sprintf
					(
						"%s: '%s' is not a %s command. See '%s --help'",
						$this->name,
						$this->input->getCommand(),
						$this->name,
						$this->name
					);
					$this->output->line( $outputContents );
					break;

				case Command\InvalidCommandException::UNSPECIFIED:

					// Client has defined a default command
					if (!is_null ($this->defaultCommand))
					{
						call_user_func( $this->defaultCommand->getClosure() );
						$this->stop();
					}
					else
					{
						$this->output->string( $this->getUsage() );
					}
					break;
			}

			$this->exit = 1;
		}
		// Incorrect parameters given
		catch(\BadFunctionCallException $e)
		{
			$command   = $this->getCommand($this->input->getCommand());
			$usage     = $command->getUsage();
			$arguments = $this->input->getCommandArguments();

			// Attempt calling subcommand first
			if(count($arguments) > 0)
			{
				// Subcommand is registered
				if(is_null($command->getSubcommand($arguments[0])) == false)
				{
					$usage    = $command->getName();
					$command  = $command->getSubcommand($arguments[0]);
					$usage   .= ' '.$command->getUsage();

					array_shift($arguments);
				}
			}

			$outputContents = sprintf('usage: %s %s', $this->name, $usage);
			$this->output->line( $outputContents );
			$this->exit = 1;
		}

		// Incorrect options given
		catch( \InvalidArgumentException $e )
		{
			$outputContents = sprintf( "%s: %s for command '%s'", $this->name, $e->getMessage(), $this->input->getCommand() );
			$this->output->line( $outputContents );
			$this->exit = 1;
		}

		// Exception thrown by command
		catch( Command\CommandInvokedException $e )
		{
			$outputContents = sprintf( '%s: %s', $this->name, $e->getMessage() );
			$this->output->line( $outputContents );
			$this->exit = $e->getCode();
		}
		// Exception thrown by command: show usage
		catch( Command\IncorrectUsageException $e )
		{
			$outputContents = sprintf( 'usage: %s %s', $this->name, $e->getMessage() );
			$this->output->line( $outputContents );
			$this->exit = 1;
		}
	}

	/**
	 * @param	Cranberry\Core\File\Directory	$dataDirectory
	 */
	public function setDataDirectory( File\Directory $dataDirectory )
	{
		$this->dataDirectory = $dataDirectory;
	}

	/**
	 * Terminate the application
	 */
	public function stop()
	{
		echo $this->output->flush();
		exit( $this->exit );
	}
}
