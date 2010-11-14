<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * Abstrakte Basisklasse für REX_VARS
 *
 * @ingroup redaxo
 */
abstract class rex_var
{
  // --------------------------------- Actions

  /**
   * Actionmethode:
   * Zum füllen des sql aus dem $REX_ACTION Array
   */
  function setACValues($sql, $REX_ACTION, $escape = false, $prependTableName = true)
  {
    // nichts tun
  }

  /**
   * Actionmethode:
   * Zum füllen des $REX_ACTION Arrays aus den Input Formularwerten
   * @return REX_ACTION Array
   */
  function getACRequestValues($REX_ACTION)
  {
    return $REX_ACTION;
  }

  /**
   * Actionmethode:
   * Zum füllen des $REX_ACTION Arrays aus der Datenbank (rex_sql)
   * @return REX_ACTION Array
   */
  function getACDatabaseValues($REX_ACTION, $sql)
  {
    return $REX_ACTION;
  }

  /**
   * Actionmethode:
   * Ersetzen der Werte in dem Aktionsscript
   * @return output String
   */
  function getACOutput($REX_ACTION, $content)
  {
    $sql = new rex_sql();
    $this->setACValues($sql, $REX_ACTION);
    return $this->getBEOutput($sql, $content);
  }

  // --------------------------------- Ouput

  /**
   * Ausgabe eines Modules fürs Frontend
   * sql Objekt mit der passenden Slice
   *
   * FE = Frontend
   */
  function getFEOutput($sql, $content)
  {
    return $this->getBEOutput($sql, $content);
  }

  /**
   * Ausgabe eines Modules im Backend bei der Ausgabe
   * sql Objekt mit der passenden Slice
   *
   * BE = Backend
   */
  function getBEOutput($sql, $content)
  {
    return $content;
  }

  /**
   * Ausgabe eines Modules im Backend bei der Eingabe
   * sql Objekt mit der passenden Slice
   *
   * BE = Backend
   */
  function getBEInput($sql, $content)
  {
    return $this->getBEOutput($sql, $content);
  }

  /**
   * Ausgabe eines Templates
   */
  function getTemplate($content)
  {
  	return $content;
  }

  /**
   * Wandelt PHP Code in Einfache Textausgaben um
   */
  protected static function stripPHP($content)
  {
    $content = str_replace('<?', '&lt;?', $content);
    $content = str_replace('?>', '?&gt;', $content);
    return $content;
  }

  /**
   * GetValue Wrapper, da hier immer auf die gleiche Tabelle gearbeitet wird und
   * mit MySQL 3.x mit Tabellenprefix angegeben werden muss, da der SQL gleichnamige
   * Spalten unterschiedlicher Tabellen enthält.
   */
  function getValue($sql, $value)
  {
    global $REX;
	if(!$sql instanceof rex_sql) debug_print_backtrace();
    //return $sql->getValue($REX['DATABASE']['TABLE_PREFIX'] . 'article_slice.' . $value);
  }
  /**
   * setValue Wrapper, da hier immer auf die gleiche Tabelle gearbeitet wird und
   * mit MySQL 3.x mit Tabellenprefix angegeben werden muss, da der SQL gleichnamige
   * Spalten unterschiedlicher Tabellen enth�lt.
   */
  function setValue($sql, $fieldname, $value, $escape = false, $prependTableName = true)
  {
    global $REX;

	 $table = $prependTableName ? $REX['DATABASE']['TABLE_PREFIX'].'article_slice.' : '';

    if ($escape)
      return $sql->setValue($table.$fieldname, addslashes($value));

    return $sql->setValue($table.$fieldname, $value);
  }

  /**
   * Callback um nicht explizit gehandelte OutputParameter zu behandeln
   */
  function handleDefaultParam($varname, $args, $name, $value)
  {
    switch($name)
    {
      case '0'       : $name = 'id';
      case 'id'      :
      case 'prefix'  :
      case 'suffix'  :
      case 'ifempty' :
      case 'instead' :
      case 'callback':
      $args[$name] = (string) $value;
    }
    return $args;
  }

  /**
   * Parameter aus args auf die Ausgabe eines Widgets anwenden
   */
  function handleGlobalWidgetParams($varname, $args, $value)
  {
    return $value;
  }

  /**
   * Parameter aus args auf den Wert einer Variablen anwenden
   */
  function handleGlobalVarParams($varname, $args, $value)
  {
    if(isset($args['callback']))
    {
      $args['subject'] = $value;
      return call_user_func($args['callback'], $args);
    }

    $prefix = '';
    if(isset($args['prefix']))
      $prefix = $args['prefix'];

    $suffix = '';
    if(isset($args['suffix']))
      $suffix = $args['suffix'];

    if(isset($args['instead']) && $value != '')
      $value = $args['instead'];

    if(isset($args['ifempty']) && $value == '')
      $value = $args['ifempty'];

    return $prefix . $value . $suffix;
  }

  /**
   * Parameter aus args zur Laufzeit auf den Wert einer Variablen anwenden.
   * Wichtig f�r Variablen, die Variable ausgaben haben.
   */
  function handleGlobalVarParamsSerialized($varname, $args, $value)
  {
    $varname = str_replace('"', '\"', $varname);
    $args = str_replace('"', '\"', serialize($args));
    return 'rex_var::handleGlobalVarParams("'. $varname .'", unserialize("'. $args .'"), '. $value .')';
  }

  /**
   * Findet die Parameter der Variable $varname innerhalb des Strings $content.
   *
   * @access protected
   */
  function getVarParams($content, $varname)
  {
    $result = array ();

    $match = $this->matchVar($content, $varname);

    foreach ($match as $param_str)
    {
    	$args = array();
    	$params = $this->splitString($param_str);
    	foreach ($params as $name => $value)
    	{
        $args = $this->handleDefaultParam($varname, $args, $name, $value);
    	}

      $result[] = array (
        $param_str,
        $args
      );
    }

    return $result;
  }

  /**
   * Durchsucht den String $content nach Variablen mit dem Namen $varname.
   * Gibt die Parameter der Treffer (Text der Variable zwischen den []) als Array zur�ck.
   */
  function matchVar($content, $varname)
  {
    $result = array ();

    if (preg_match_all('/' . preg_quote($varname, '/') . '\[([^\]]*)\]/ms', $content, $matches))
    {
      foreach ($matches[1] as $match)
      {
        $result[] = $match;
      }
    }

    return $result;
  }



  function extractArg($name, $args, $default = null)
  {
  	$val = $default;
  	if(isset($args[$name]))
  	{
  		$val = $args[$name];
  		unset($args[$name]);
  	}
  	return array($val, $args);
  }

  /**
   * Trennt einen String an Leerzeichen auf.
   * Abschnitte die in "" oder '' stehen, werden als ganzes behandelt und
   * darin befindliche Leerzeichen nicht getrennt.
   * @access protected
   */
  protected static function splitString($string)
  {
    return rex_split_string($string);
  }

  public static function isAddEvent()
  {
    return rex_request('function', 'string') == 'add';
  }

  public static function isEditEvent()
  {
    return rex_request('function', 'string') == 'edit';
  }

  public static function isDeleteEvent()
  {
    return rex_request('function', 'string') == 'delete';
  }
}