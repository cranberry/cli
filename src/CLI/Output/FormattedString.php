<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Output;

class FormattedString
{
	/**
	 * @var	string
	 */
	protected $backgroundColor;

	/**
	 * @var	string
	 */
	protected $foregroundColor;

	/**
	 * @var	string
	 */
	protected $paddingCharacter;

	/**
	 * @var	number
	 */
	protected $paddingLength;

	/**
	 * Foreground color prefix, which affects properties like bold, underline, etc.
	 *
	 * @var	number
	 */
	protected $prefix=0;

	/**
	 * @var	string
	 */
	protected $string;

	/**
	 * @param	string	$string
	 * @return	void
	 */
	public function __construct( $string='' )
	{
		$this->string = $string;
	}

	/**
	 * @return	string
	 */
	public function __toString()
	{
		if( $this->prefix != 0 && !isset( $this->foregroundColor ) )
		{
			$this->foregroundColor = 'none';
		}

		/**
		 * Substring formatting
		 */
		 // Bold
 		$patternBold = '/\{b\}([^\{]+)\{\/b\}/';
 		preg_match( $patternBold, $this->string, $matchesBold );
 		if( count( $matchesBold) > 0 )
 		{
 			$substring = new self( $matchesBold[1] );
 			$substring->bold();

 			$this->string = str_replace( $matchesBold[0], $substring, $this->string );
 		}

		// Underline
		$patternUnderline = '/\{ul\}([^\{]+)\{\/ul\}/';
		preg_match( $patternUnderline, $this->string, $matchesUnderline );
		if( count( $matchesUnderline) > 0 )
		{
			$substring = new self( $matchesUnderline[1] );
			$substring->underline();

			$this->string = str_replace( $matchesUnderline[0], $substring, $this->string );
		}

		// Foreground color
		$patternForegroundColor = '/\{fg:([^\}]+)\}([^\{]+)\{\/fg:[^\}]+\}/';
		preg_match( $patternForegroundColor, $this->string, $matchesForegroundColor );
		if( count( $matchesForegroundColor) > 0 )
		{
			$substring = new self( $matchesForegroundColor[2] );
			$substring->foregroundColor( $matchesForegroundColor[1] );

			$this->string = str_replace( $matchesForegroundColor[0], $substring, $this->string );
		}

		// Background color
		$patternBgColor = '/\{bg:([^\}]+)\}([^\{]+)\{\/bg:[^\}]+\}/';
		preg_match( $patternBgColor, $this->string, $matchesBgColor );
		if( count( $matchesBgColor) > 0 )
		{
			$substring = new self( $matchesBgColor[2] );
			$substring->backgroundColor( $matchesBgColor[1] );

			$this->string = str_replace( $matchesBgColor[0], $substring, $this->string );
		}

		$foregroundColors =
		[
			'none'		=> "{$this->prefix};29",
			'black'		=> "{$this->prefix};30",
			'red'		=> "{$this->prefix};31",
			'green'		=> "{$this->prefix};32",
			'yellow'	=> "{$this->prefix};33",
			'blue'		=> "{$this->prefix};34",
			'purple'	=> "{$this->prefix};35",
			'cyan'		=> "{$this->prefix};36",
			'gray'		=> "{$this->prefix};37",
			'grey'		=> "{$this->prefix};37",
		];

		$backgroundColors =
		[
			'black'		=> '40',
			'red'		=> '41',
			'green'		=> '42',
			'yellow'	=> '43',
			'blue'		=> '44',
			'purple'	=> '45',
			'cyan'		=> '46',
			'gray'		=> '47',
			'grey'		=> '47',
		];

		$foregroundColor = isset( $foregroundColors[ $this->foregroundColor ] ) ? $foregroundColors[ $this->foregroundColor ] : null;
		$backgroundColor = isset( $backgroundColors[ $this->backgroundColor ] ) ? $backgroundColors[ $this->backgroundColor ] : null;

		$string = '';
		$string .= isset( $foregroundColor) ? "\033[{$foregroundColor}m" : '';
		$string .= isset( $backgroundColor) ? "\033[{$backgroundColor}m" : '';
		$string .= $this->string;
		$string .= isset( $foregroundColor) || isset( $backgroundColor) ? "\033[0m" : '';

		if( isset( $this->paddingCharacter ) )
		{
			$paddingCount = abs( $this->paddingLength ) - strlen( $this->string );
			$padding = '';

			for( $i = 1; $i <= $paddingCount; $i++ )
			{
				$padding .= $this->paddingCharacter;
			}

			// Like sprintf, positive number for right justification...
			if( $this->paddingLength > 0 )
			{
				$string .= $padding;
			}
			// ...negative for left justification
			else
			{
				$string = $padding . $string;
			}
		}

		return $string;
	}

	/**
	 * @param	string	$color
	 * @return	self
	 */
	public function backgroundColor( $color )
	{
		$this->backgroundColor = $color;
		return $this;
	}

	/**
	 * @return	self
	 */
	public function blink()
	{
		$this->prefix = 5;
		return $this;
	}

	/**
	 * @return	self
	 */
	public function bold()
	{
		$this->prefix = 1;
		return $this;
	}

	/**
	 * @param	string	$color
	 * @return	self
	 */
	public function foregroundColor( $color )
	{
		$this->foregroundColor = $color;
		return $this;
	}

	/**
	 * @param	number	$length		Number of leading (or trailing) characters
	 * @param	string	$character	Character to use, defaults to spaces
	 * @return	self
	 */
	public function pad( $length, $character=' ' )
	{
		$this->paddingLength = $length;
		$this->paddingCharacter = $character;

		return $this;
	}

	/**
	 * @return	self
	 */
	public function invert()
	{
		$this->prefix = 7;
		return $this;
	}

	/**
	 * Get length of unformatted string
	 *
	 * @return	number
	 */
	public function length()
	{
		return strlen( $this->string );
	}

	/**
	 * @return	self
	 */
	public function normal()
	{
		$this->prefix = 0;
		return $this;
	}

	/**
	 * @param	string	$string
	 * @return	self
	 */
	public function setString( $string )
	{
		$this->string = $string;
	}

	/**
	 * @return	self
	 */
	public function underline()
	{
		$this->prefix = 4;
		return $this;
	}
}
