<?php

/**
 * Addon Funktionen
 * @package redaxo4
 * @version svn:$Id$
 */

function rex_addons_folder($addon = null)
{
	global $REX;

	if (!is_null($addon)) {
		return $REX['INCLUDE_PATH'].'/addons/'.$addon.'/';
	}

	return $REX['INCLUDE_PATH'].'/addons/';
}

function rex_read_addons_folder($folder = null)
{
	if ($folder === null) {
		$folder = rex_addons_folder();
	}
	
	$addons = array();
	$handle = opendir($folder);
	
	if ($handle) {
		while ($file = readdir($handle)) {
			if ($file[0] != '.' && $file[0] != '_' && is_dir($folder.$file)) {
				$addons[] = $file;
			}
		}
		
		closedir($handle);
		natsort($addons);
	}
	
	return $addons;
}

// ------------------------------------- Helpers

/**
* Importiert die gegebene SQL-Datei in die Datenbank
*
* @return boolean  true bei Erfolg, sonst eine Fehlermeldung
*/
function rex_install_dump($file, $debug = false)
{
	$error = '';
	$sql   = rex_sql::getInstance();
	$sql->debugsql = $debug;

	foreach (rex_read_sql_dump($file) as $query) {
		$sql->setQuery(rex_install_prepare_query($query));
		$sqlerr = $sql->getError();

		if (!empty($sqlerr)) {
			$error .= $sqlerr."<br />\n";
		}
	}

	return $error == '' ? true : $error;
}

function rex_install_prepare_query($qry)
{
	global $REX;

	// $REX['USER'] gibts im Setup nicht.
	if (isset($REX['USER'])) {
		$qry = str_replace('%USER%', $REX['USER']->getValue('login'), $qry);
	}

	$qry = str_replace('%TIME%', time(), $qry);
	$qry = str_replace('%TABLE_PREFIX%', $REX['TABLE_PREFIX'], $qry);
	$qry = str_replace('%TEMP_PREFIX%', $REX['TEMP_PREFIX'], $qry);

	return $qry;
}

/**
* Removes comment lines and splits up large sql files into individual queries
*
* Last revision: September 23, 2001 - gandon
*
* @param   array    the splitted sql commands
* @param   string   the sql commands
* @param   integer  the MySQL release number (because certains php3 versions
*                   can't get the value of a constant from within a function)
*
* @return  boolean  always true
*
* @access  public
*/
// Taken from phpmyadmin (read_dump.lib.php: PMA_splitSqlFile)
function PMA_splitSqlFile(& $ret, $sql, $release) {
	// do not trim, see bug #1030644
	//$sql          = trim($sql);
	$sql = rtrim($sql, "\n\r");
	$sql_len = strlen($sql);
	$char = '';
	$string_start = '';
	$in_string = FALSE;
	$nothing = TRUE;
	$time0 = time();
	
	for ($i = 0; $i < $sql_len; ++ $i) {
		$char = $sql[$i];
	
		// We are in a string, check for not escaped end of strings except for
		// backquotes that can't be escaped
		if ($in_string) {
			for (;;) {
				$i = strpos($sql, $string_start, $i);
				// No end of string found -> add the current substring to the
				// returned array
				if (!$i) {
					$ret[] = $sql;
					return TRUE;
				}
				// Backquotes or no backslashes before quotes: it's indeed the
				// end of the string -> exit the loop
				else
				if ($string_start == '`' || $sql[$i -1] != '\\') {
					$string_start = '';
					$in_string = FALSE;
					break;
				}
				// one or more Backslashes before the presumed end of string...
				else {
					// ... first checks for escaped backslashes
					$j = 2;
					$escaped_backslash = FALSE;
					while ($i - $j > 0 && $sql[$i - $j] == '\\') {
						$escaped_backslash = !$escaped_backslash;
						$j ++;
					}
					// ... if escaped backslashes: it's really the end of the
					// string -> exit the loop
					if ($escaped_backslash) {
						$string_start = '';
						$in_string = FALSE;
						break;
					}
					// ... else loop
					else $i++;
					
				} // end if...elseif...else
			} // end for
		} // end if (in string)
	
		// lets skip comments (/*, -- and #)
		else
		if (($char == '-' && $sql_len > $i +2 && $sql[$i +1] == '-' && $sql[$i +2] <= ' ')
			|| $char == '#' || ($char == '/' && $sql_len > $i +1 && $sql[$i +1] == '*')) {
			
			$i = strpos($sql, $char == '/' ? '*/' : "\n", $i);
			// didn't we hit end of string?
			if ($i === FALSE)
			{
			break;
			}
			if ($char == '/')
			$i ++;
		}
	
		// We are not in a string, first check for delimiter...
		else
		if ($char == ';') {
			// if delimiter found, add the parsed part to the returned array
			$ret[] = array ('query' => substr($sql, 0, $i), 'empty' => $nothing);
			$nothing = TRUE;
			$sql = ltrim(substr($sql, min($i +1, $sql_len)));
			$sql_len = strlen($sql);
			if ($sql_len) $i = -1;
			// The submited statement(s) end(s) here
			else return TRUE;
		} // end else if (is delimiter)
		
		// ... then check for start of a string,...
		else
		if (($char == '"') || ($char == '\'') || ($char == '`')) {
			$in_string = TRUE;
			$nothing = FALSE;
			$string_start = $char;
		} // end else if (is start of string)
	
		elseif ($nothing) $nothing = FALSE;
	
		// loic1: send a fake header each 30 sec. to bypass browser timeout
		$time1 = time();
		if ($time1 >= $time0 +30) {
			$time0 = $time1;
			header('X-pmaPing: Pong');
		} // end if
	} // end for
	
	// add any rest to the returned array
	if (!empty ($sql) && preg_match('@[^[:space:]]+@', $sql))
		$ret[] = array ('query' => $sql, 'empty' => $nothing);
	
	return TRUE;
	
}

/**
* Reads a file and split all statements in it.
*
* @param string $file  Path to the SQL-dump-file
*/
function rex_read_sql_dump($file)
{
	if (!is_file($file) || !is_readable($file)) {
		return false;
	}

	$ret         = array();
	$sqlsplit    = '';
	$fileContent = file_get_contents($file);
	
	PMA_splitSqlFile($sqlsplit, $fileContent, '');

	if (is_array($sqlsplit)) {
		foreach ($sqlsplit as $qry) {
			$ret[] = $qry['query'];
		}
	}
	
	return $ret;
}

/**
* Sucht innerhalb des $REX['ADDON']['page'] Array rekursiv nach der page
* $needle
*
* Gibt bei erfolgreicher Suche den Namen des Addons zurück, indem die page
* gefunden wurde, sonst false
*/
function rex_search_addon_page($needle, $haystack = null)
{
	global $REX;

	if ($haystack === null) {
		$haystack = $REX['ADDON']['page'];
	}

	foreach ($haystack as $key => $value) {
		if (is_array($value)) {
			$found = rex_search_addon_page($needle, $value);
		}
		else {
			$found = $needle == $value;
		}

		if ($found !== false) {
			return $key;
		}
	}

	return false;
}
