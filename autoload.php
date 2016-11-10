<?php

/*
 * This file is part of Cranberry\CLI
 */
namespace Cranberry\CLI;

$pathCranberryCLIBase = __DIR__;
$pathCranberryCLISrc = $pathCranberryCLIBase . '/src/CLI';
$pathCranberryCLIVendor = $pathCranberryCLIBase . '/vendor';

/*
 * Initialize autoloading
 */
include_once( $pathCranberryCLISrc . '/Autoloader.php' );
Autoloader::register();

/*
 * Initialize vendor autoloading
 */
include_once( $pathCranberryCLIVendor . '/cranberry/core/autoload.php' );