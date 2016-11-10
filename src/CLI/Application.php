<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI;

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
	 * @var	array
	 */
	protected $cleanupActions=[];

	/**
	 * @var array
	 */
	protected $commands=[];

	/**
	 * @var Cranberry\CLI\Command\Command
	 */
	protected $defaultCommand;

	/**
	 * @var	Cranberry\Core\File\Directory
	 */
	protected $dirApp;

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
	 * @var string
	 */
	protected $output='';

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
	public function __construct( $name, $version, $phpMinimumVersion, File\Directory $dirApp )
	{
		$this->output  = new Output\Output();
		$this->input   = new Input();

		if( version_compare( PHP_VERSION, $phpMinimumVersion, '>=' ) == false )
		{
			$this->exit = 1;
			$this->output->line( "{$name}: Requires PHP {$phpMinimumVersion}+, found " . PHP_VERSION );
			$this->stop();
		}

		$this->name    = $name;
		$this->version = $version;

		/* Application root directory */
		$this->dirApp = $dirApp;

		/* Register default commands */
		$commandHelp = new Command\Command( 'help', "Display help information about {$this->name}", [$this, 'commandHelp'] );
		$commandHelp->setUsage( 'help <command>' );
		$this->registerCommand( $commandHelp );

		/* Cookie Controller */
		$fileCookies = $this->dirApp->child( '.cookies' );
		$this->cookies = new Cookie( $fileCookies );
	}

	/**
	 * @param	string	$name
	 * @param	array	$arguments
	 * @return	mixed
	 */
	public function callCommand($name, $arguments=[])
	{
		if(is_null($name))
		{
			throw new Command\InvalidCommandException( "No command specified", Command\InvalidCommandException::UNSPECIFIED );
		}

		$command = $this->getCommand($name);

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
				$name,
				$rf->getNumberOfRequiredParameters(),
				count($arguments)
			));
		}

		foreach($this->input->getCommandOptions() as $key => $value)
		{
			$command->setOptionValue($key, $value);
		}

		$command->setAppDirectory( $this->dirApp );
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
		$didPerformCleanup = $this->getCookie( 'cleanup', $versionSlug ) == true;

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
				$filesToDelete = call_user_func( $cleanupAction, $this->dirApp );

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
		$this->setCookie( 'cleanup', $versionSlug, "true" );
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
	 * Display version number
	 *
	 * @return	string
	 */
	protected function commandVersion()
	{
		return sprintf('%s version %s', $this->name, $this->version);
	}

	/**
	 * @param	string	$section
	 * @param	string	$name
	 * @return	void
	 */
	public function deleteCookie( $section, $name )
	{
	   $this->cookies->delete( $section, $name );
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
	 * @param	string	$section
	 * @param	string	$name
	 * @return	mixed
	 */
	public function getCookie( $section, $name )
	{
		return $this->cookies->get( $section, $name );
	}

	/**
	 * @return	string
	 */
	public function getUsage()
	{
		$output = new Output\Output();

		$output->line( "usage: {$this->name} [--version] <command> [<args>]" );
		$output->line();
		$output->line( "Commands are:" );

		foreach($this->commands as $command)
		{
			$commandName = $command->getName();
			$commandDescription = $command->getDescription();

			if( $commandName == 'help' )
			{
				continue;
			}

			$commandString = new Format\String( $commandName );
			$commandString->pad( 11 );

			$line = "   {$commandString}{$commandDescription}";
			$output->wrappedLine( $line, 14 );
		}

		$output->line();
		$output->indentedLine( "See '{$this->name} help <command>' to read about a specific command", 0 );

		return $output->flush();
	}

	/**
	 * @return	string
	 */
	public function getVersion()
	{
		return $this->version;
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
	}

	/**
	 * Optionally specify a command to run if the application is run without arguments
	 *
	 * @return	void
	 */
	public function registerDefaultCommand( Command\Command $command )
	{
		$this->defaultCommand = $command;
	}

	/**
	 *
	 */
	public function run()
	{
		ksort($this->commands);

		$options = $this->input->getApplicationOptions();

		// --version
		if( isset($options['version'] ) )
		{
			$this->output->line( $this->commandVersion() );
			return;
		}

		try
		{
			$this->callCommand( $this->input->getCommand(), $this->input->getCommandArguments() );
		}

		// Command not registered
		catch( Command\InvalidCommandException $e )
		{
			switch($e->getCode())
			{
				case Command\InvalidCommandException::UNDEFINED:

					$outputContents = sprintf
					(
						"%s: '%s' is not a %s command. See '%s help'",
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
						$this->output->line( $this->getUsage() );
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
	 * @param	string	$section
	 * @param	string	$name
	 * @param	mixed	$value
	 * @return	void
	 */
	public function setCookie( $section, $name, $value )
	{
		$this->cookies->set( $section, $name, $value );
	}

	/**
	 * Terminate the application
	 */
	public function stop()
	{
		echo $this->output->flush();
		exit( $this->exit );
	}

	/**
	 * @param	string	$name		Name of command to unregister
	 */
	public function unregisterCommand($name)
	{
		if(isset($this->commands[$name]))
		{
			unset($this->commands[$name]);
		}
	}
}
