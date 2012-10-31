<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// determine application
$slyAppName = isset($_SERVER['REDIRECT_SLYAPP']) ? strtolower($_SERVER['REDIRECT_SLYAPP'])  : 'frontend';

if (isset($_SERVER['REDIRECT_SLYBASE'])) {
	$slyAppBase = $_SERVER['REDIRECT_SLYBASE'];
}
else {
	// deduct from app name
	$slyAppBase = isset($_SERVER['REDIRECT_SLYAPP']) ? strtolower($_SERVER['REDIRECT_SLYAPP'])  : '';
}

// load core system
require 'sally/core/master.php';

// load and run the requested application
require 'sally/'.$slyAppName.'/boot.php';
