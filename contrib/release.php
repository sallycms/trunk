<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

if (PHP_SAPI !== 'cli') {
	die('This script has to be run from command line.');
}

define('JSON_UNESCAPED_SLASHES', 64);
define('JSON_PRETTY_PRINT',      128);
define('JSON_UNESCAPED_UNICODE', 256);

////////////////////////////////////////////////////////////////////////////////
// Configuration

$demoRepo = 'Q:\sally\demo';
$buildDir = 'Q:\sally\releases';
$addons   = array(
	'sallycms/be-search',
	'sallycms/image-resize',
	'sallycms/import-export',
	'webvariants/deployer',
	'webvariants/developer-utils',
	'webvariants/global-settings',
	'webvariants/metainfo',
	'webvariants/realurl2',
	'webvariants/wymeditor',
	'webvariants/rbac'
);

$variants = array(
	'starterkit'            => array('tests' => true, 'addons' => $addons, 'demo' => true, 'install' => false),
	'starterkit-standalone' => array('tests' => true, 'addons' => $addons, 'demo' => true, 'install' => true),

	'lite'    => array('tests' => true,  'demo' => false, 'addons' => array()),
	'minimal' => array('tests' => false, 'demo' => false, 'addons' => array())
);

////////////////////////////////////////////////////////////////////////////////
// Check arguments

$args = $_SERVER['argv'];

if (count($args) < 2) {
	print 'Usage: php '.$args[0].' tagname'.PHP_EOL;
	exit(1);
}

$repo = realpath(__DIR__.'/../');
$tag  = $args[1];

////////////////////////////////////////////////////////////////////////////////
// Check tag

chdir($repo);
$output = hg('identify -r "'.$tag.'"');

if (substr($output, 0, 6) == 'abort:') {
	print 'Tag "'.$tag.'" was not found.'.PHP_EOL;
	exit(1);
}

////////////////////////////////////////////////////////////////////////////////
// Create releases directory

if (!is_dir($buildDir)) mkdir($buildDir);
$buildDir = realpath($buildDir);

////////////////////////////////////////////////////////////////////////////////
// Create variants

foreach ($variants as $name => $settings) {
	llog(strtoupper($name));

	$target  = sprintf('%s/sally-%s-%s/sally', $buildDir, $tag, $name);
	$exclude = 'assets;.hg_archival.txt;.hgignore;.hgtags;.travis.yml;contrib;sally/docs';

	if (!$settings['tests']) {
		$exclude .= ';sally/tests';
	}

	// Create repository archive
	archive($repo, $target, $tag, $exclude, 1);

	// Create empty data dir
	pushd($target);
	@mkdir('data');
	@mkdir('sally/addons');

	if (!$settings['demo']) {
		file_put_contents('data/empty', 'This directory is intentionally left blank. Please make sure it\'s chmod to 0777.');
	}

	// bare archive get an empty file
	if (empty($settings['addons'])) {
		file_put_contents('sally/addons/empty', 'Put all your addOns in this directory. PHP does not need writing permissions in here.');
	}

	// run Composer to install all dependencies and addOns
	elseif ($settings['install']) {
		llog('requiring addons', 1);

		foreach ($settings['addons'] as $addon) {
			llog($addon.'...', 2, false);
			exec('composer.phar require "'.$addon.'=*"');
			finish();
		}

		llog('installing...', 1, false);
		exec('composer.phar update');
		finish();
	}

	// update composer.json
	else {
		llog('adding addon requirements...', 1, false);

		$json     = file_get_contents('composer.json');
		$composer = json_decode($json, true);

		foreach ($settings['addons'] as $addon) {
			$composer['require'][$addon] = '*';
		}

		$helper = new JSON_Beautifier();
		$pretty = $helper->prettyprint($composer);

		file_put_contents('composer.json', $pretty);
		finish();
	}

	// add starterkit contents (templates, modules, assets, ...)
	if ($settings['demo']) {
		llog('adding demo project', 1);
		llog('updating...', 2, false);

		pushd($demoRepo);
		hg('fetch');
		popd();

		finish();

		$exclude = '.hg_archival.txt;.hgignore;.hgtags;.travis.yml;make.bat';
		archive($demoRepo, $target, 'tip', $exclude, 2);
	}

	// Create archives
	llog('creating download archives', 1);

	pushd('..');
	$suffix = '-'.$name;

	llog('zip...', 2, false);
	exec('7z a -mx9 "../sally-'.$tag.$suffix.'.zip" "'.$target.'"');
	finish();

	llog('7z...', 2, false);
	exec('7z a -mx9 "../sally-'.$tag.$suffix.'.7z" "'.$target.'"');
	finish();

	popd(); // into archive dir
	popd(); // into repository

	print PHP_EOL;
}

llog('done.');

function hg($cmd) {
	$output = array();
	exec('hg --config progress.disable=True '.$cmd.' 2>&1', $output);
	return implode("\n", $output);
}

function llog($msg, $depth = 0, $eol = true) {
	printf('%'.($depth*2).'s* %s', '', $msg);
	if ($eol) print PHP_EOL;
}

function finish() {
	print PHP_EOL;
}

function pushd($dir) {
	$GLOBALS['dirhist'][] = getcwd();
	chdir($dir);
}

function popd() {
	chdir(array_pop($GLOBALS['dirhist']));
}

function archive($source, $target, $rev, $exclude, $depth) {
	$exclude = explode(';', $exclude);
	$params  = array('-r "'.$rev.'"');

	foreach ($exclude as $excl) {
		$params[] = '-X '.$excl;
	}

	$params[] = '"'.$target.'"';

	llog('archiving...', $depth, false);

	pushd($source);
	hg('archive '.implode(' ', $params));
	popd();

	finish();
}

class JSON_Beautifier {
	public function prettyprint($data, $options = 448, $optimize = true) {
		$indented = $this->indent($data, $options);
		return $optimize? $this->optimize($indented) : $indented;
	}

	public function optimize($json) {
		preg_match_all('#^(\s*)"(.+?)": \[([^{}[]+?)^\1\]#m', $json, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$json = str_replace($match[0], $match[1].'"'.$match[2].'": ['.trim(preg_replace('#,\n\s+"#m', ', "', $match[3])).']', $json);
		}

		preg_match_all('#^(\s*)\[([^{}[]+?)^\1\]#m', $json, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$json = str_replace($match[0], $match[1].'['.trim(preg_replace('#,\n\s+"#m', ', "', $match[2])).']', $json);
		}

		preg_match_all('#^(\s*)"(.+?)": \{\n([^\n]+?)\n^\1\}#m', $json, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$json = str_replace($match[0], $match[1].'"'.$match[2].'": { '.trim($match[3]).' }', $json);
		}

		preg_match_all('#^(\s*)\{\n([^\n]+?)\n^\1\}#m', $json, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$json = str_replace($match[0], $match[1].'{ '.trim($match[2]).' }', $json);
		}

		return rtrim($json)."\n";
	}

	public function indent($data, $options = 448) {
		if (version_compare(PHP_VERSION, '5.4', '>=')) {
			return json_encode($data, $options);
		}

		$json = json_encode($data);

		$prettyPrint = (Boolean) ($options & JSON_PRETTY_PRINT);
		$unescapeUnicode = (Boolean) ($options & JSON_UNESCAPED_UNICODE);
		$unescapeSlashes = (Boolean) ($options & JSON_UNESCAPED_SLASHES);

		if (!$prettyPrint && !$unescapeUnicode && !$unescapeSlashes) {
			return $json;
		}

		$result = '';
		$pos = 0;
		$strLen = strlen($json);
		$indentStr = "\t";
		$newLine = "\n";
		$outOfQuotes = true;
		$buffer = '';
		$noescape = true;

		for ($i = 0; $i <= $strLen; $i++) {
			// Grab the next character in the string
			$char = substr($json, $i, 1);

			// Are we inside a quoted string?
			if ('"' === $char && $noescape) {
				$outOfQuotes = !$outOfQuotes;
			}

			if (!$outOfQuotes) {
				$buffer .= $char;
				$noescape = '\\' === $char ? !$noescape : true;
				continue;
			} elseif ('' !== $buffer) {
				if ($unescapeSlashes) {
					$buffer = str_replace('\\/', '/', $buffer);
				}

				if ($unescapeUnicode && function_exists('mb_convert_encoding')) {
					// http://stackoverflow.com/questions/2934563/how-to-decode-unicode-escape-sequences-like-u00ed-to-proper-utf-8-encoded-cha
					$buffer = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($match) {
						return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
					}, $buffer);
				}

				$result .= $buffer.$char;
				$buffer = '';
				continue;
			}

			if (':' === $char) {
				// Add a space after the : character
				$char .= ' ';
			} elseif (('}' === $char || ']' === $char)) {
				$pos--;
				$prevChar = substr($json, $i - 1, 1);

				if ('{' !== $prevChar && '[' !== $prevChar) {
					// If this character is the end of an element,
					// output a new line and indent the next line
					$result .= $newLine;
					for ($j = 0; $j < $pos; $j++) {
						$result .= $indentStr;
					}
				} else {
					// Collapse empty {} and []
					$result = rtrim($result);
				}
			}

			$result .= $char;

			// If the last character was the beginning of an element,
			// output a new line and indent the next line
			if (',' === $char || '{' === $char || '[' === $char) {
				$result .= $newLine;

				if ('{' === $char || '[' === $char) {
					$pos++;
				}

				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
		}

		return $result;
	}
}
