<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// Magic Quotes entfernen, wenn vorhanden

if (get_magic_quotes_gpc()) {
	function stripslashes_ref(&$value) {
		$value = stripslashes($value);
	}

	array_walk_recursive($_GET,     'stripslashes_ref');
	array_walk_recursive($_POST,    'stripslashes_ref');
	array_walk_recursive($_COOKIE,  'stripslashes_ref');
	array_walk_recursive($_REQUEST, 'stripslashes_ref');
}

// Register Globals entfernen

if (ini_get('register_globals')) {
	$superglobals = array('REX', '_GET', '_POST', '_REQUEST', '_ENV', '_FILES', '_SESSION', '_COOKIE', '_SERVER');
	$keys         = array_keys($GLOBALS);

	foreach ($keys as $key) {
		if (!in_array($key, $superglobals) && $key != 'GLOBALS') {
			unset($$key);
		}
	}

	unset($superglobals, $key, $keys);
}

// So, jetzt haben wir eine saubere Grundlage für unsere Aufgaben.

// Wir gehen davon aus, dass SLY_HTDOCS_PATH existiert. Das ist
// eine Annahme die den Code hier schneller macht und vertretbar ist.
// Wer das falsch setzt, hat es verdient, dass das Script nicht läuft.

define('SLY_BASE',          realpath(SLY_HTDOCS_PATH));
define('SLY_INCLUDE_PATH',  SLY_BASE.DIRECTORY_SEPARATOR.'sally'.DIRECTORY_SEPARATOR.'include');
define('SLY_DATAFOLDER',    SLY_BASE.DIRECTORY_SEPARATOR.'data');
define('SLY_DYNFOLDER',     SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'dyn');
define('SLY_MEDIAFOLDER',   SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'mediapool');
define('SLY_DEVELOPFOLDER', SLY_BASE.DIRECTORY_SEPARATOR.'develop');
define('SLY_ADDONFOLDER',   SLY_INCLUDE_PATH.DIRECTORY_SEPARATOR.'addons');

// Loader initialisieren

require_once SLY_INCLUDE_PATH.'/loader.php';

// Kernkonfiguration laden

$config = sly_Core::config();
$config->loadStatic(SLY_INCLUDE_PATH.'/config/sallyStatic.yml');
$config->loadLocalConfig();
$config->loadLocalDefaults(SLY_INCLUDE_PATH.'/config/sallyDefaults.yml');
$config->loadProjectConfig();
$config->loadDevelop();

// Sync?
if (!$config->get('SETUP')){
	// Standard-Variablen
	sly_Core::registerCoreVarTypes();

	// Sprachen laden
	$REX['CLANG']      = sly_Util_Language::findAll();
	$REX['CUR_CLANG']  = sly_Core::getCurrentClang();
	$REX['ARTICLE_ID'] = sly_Core::getCurrentArticleId();
}

// REDAXO compatibility
$REX = array_merge($REX, $config->get(null));

// Check for system updates
$coreVersion  = sly_Core::getVersion('X.Y.Z');
$knownVersion = sly_Util_Versions::get('sally');

if ($knownVersion !== $coreVersion) {
	// dummy: implement some clever update mechanism (if needed)
	sly_Util_Versions::set('sally', $coreVersion);
}
