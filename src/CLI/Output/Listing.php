<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI\Output;

use Cranberry\CLI\Format;

class Listing extends Output
{
	/**
	 * @var	array
	 */
	protected $items=[];

	/**
	 * @param	string	$item
	 * @return	void
	 */
	public function item( $item )
	{
		$this->items[] = $item;
	}

	/**
	 * @return	string
	 */
	public function flush()
	{
		$output  = '';

		// Determine padding
		$maxLength = 0;
		foreach( $this->items as $item )
		{
			$itemLength = $item instanceof FormattedString ? $item->length() : strlen( $item );

			if( $itemLength >  $maxLength )
			{
				$maxLength = $itemLength;
			}
		}

		$padding = $maxLength + 4;
		$width = $this->getCols();

		$cols = floor( $width / $padding );
		$rows = ceil( count( $this->items ) / $cols );

		if( count( $this->items ) == 0 )
		{
			return;
		}

		for( $r = 0; $r < $rows; $r++ )
		{
			$line = '';

			for( $c = 0; $c < $cols; $c++ )
			{
				$index = $r + ($c * $rows);
				if( isset( $this->items[$index] ) )
				{
					$item = $this->items[$index];
					$itemLength = $item instanceof FormattedString ? $item->length() : strlen( $item );

					$line .= $item;

					$spaces = $padding - $itemLength;
					for( $s = 1; $s <= $spaces; $s++ )
					{
						$line .= ' ';
					}
				}
			}

			$output .= $line . PHP_EOL;
		}

		return $output;
	}
}
