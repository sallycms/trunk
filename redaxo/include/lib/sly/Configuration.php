<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

/**
 * Ist noch ein wrapper für $REX wird irgendwann mal umgebaut
 * 
 * @author zozi@webvariants.de
 *
 */
class sly_Configuration {
	
	const STORE_PROJECT       = 1;
	const STORE_LOCAL         = 2;
	const STORE_LOCAL_DEFAULT = 3;
	const STORE_STATIC        = 4;

	private $mode              = array();
	private $loadedConfigFiles = array();
	
	private $staticConfig;
	private $localConfig;
	private $projectConfig;
	
	private static $instance;

	private function __construct() {
		global $REX;
		
		$this->staticConfig = new sly_Util_Array();
		$this->localConfig  = new sly_Util_Array();
		
		$this->loadStatic($REX['INCLUDE_PATH'].'/config/sallyStatic.yaml');
		$this->loadLocalDefaults($REX['INCLUDE_PATH'].'/config/sallyDefaults.yaml');
		
		if (file_exists($this->getLocalCacheFile())) {
			include $this->getLocalCacheFile();
			$this->localConfig = $config;
		}
		if (sly_Core::getPersistentRegistry()->has('sly_ProjectConfig')) {
			$this->projectConfig = sly_Core::getPersistentRegistry()->has('sly_ProjectConfig');
		}
	}
	
	protected function getCacheDir() {
		global $REX;
		$dir = $REX['DYNFOLDER'].'/internal/sally/config';
		if (!is_dir($dir) && !mkdir($dir, '0755', true)) {
			throw new Exception('Cache-Verzeichnis '.$dir.' konnte nicht erzeugt werden.');
		}
		return $dir;
	}
	
	protected function getCacheFile($filename) {
		$dir = $this->getCacheDir();
		$filename = str_replace('\\', '/', realpath($filename));
		return $dir.'/config_'.str_replace('/', '_', $filename).'.cache.php';
	}
	
	protected function getLocalCacheFile() {
		return $this->getCacheDir().'/sly_local.cache.php';
	}
	
	protected function isCacheValid($origfile, $cachefile) {
		return file_exists($cachefile) && filemtime($origfile) < filemtime($cachefile);
	}
	
	public function loadStatic($filename) {
		return $this->loadInternal($filename, self::STORE_STATIC);
	}
	
	public function loadLocalDefaults($filename, $force = false) {
		return $this->loadInternal($filename, self::STORE_LOCAL_DEFAULT, $force);
	}
	
	protected function loadInternal($filename, $mode, $force = false) {
		if ($mode != self::STORE_LOCAL_DEFAULT || $mode != self::STORE_STATIC) {
			throw new Exception('Konfigurationsdateien können nur mit STORE_STATIC oder STORE_LOCAL_DEFAULT geladen werden.');
		}
		if (empty($filename) || !is_string($filename)) throw new Exception('Keine Konfigurationsdatei angegeben.');
		if (!file_exists($filename)) throw new Exception('Konfigurationsdatei '.$filename.' konnte nicht gefunden werden.');
		
		$isStatic = self::STORE_STATIC;

		// force gibt es nur bei STORE_LOCAL_DEFAULT
		$force = $force && !$isStatic;
		
		$cachefile = $this->getCacheFile($filename);
		
		// prüfen ob konfiguration in diesem request bereits geladen wurde
		if (!$force && isset($this->loadedConfigFiles[$filename])) {
			// statisch geladene konfigurationsdaten werden innerhalb des requests nicht mehr überschrieben 
			if ($isStatic && file_exists($cachefile) && filemtime($filename) > filemtime($cachefile)) {
				trigger_error('Statische Konfigurationsdatei '.$filename.' wurde bereits in einer anderen Version geladen! Daten wurden nicht überschrieben.', E_USER_WARNING);
			}
			return false;
		}
		
		$config = array();
		
		// konfiguration aus cache holen, wenn cache aktuell
		if ($this->isCacheValid($filename, $cachefile)) include $cachefile;
		// konfiguration aus yaml laden
		else $config = $this->loadYaml($filename, $cachefile);
		
		// geladene konfiguration in globale konfiguration mergen
		$this->setRecursive($config, $mode, $force);
		
		$this->loadedConfigFiles[$filename] = true;
		
		return $config;
	}
	
	protected function loadYaml($filename, $cachefile) {
		if (!file_exists($filename)) throw new Exception('Konfigurationsdatei '.$filename.' konnte nicht gefunden werden.');
		$config = sfYaml::load($filename);
		file_put_contents($cachefile, '<?php $config = '.var_export($config, true).';');
		return $config;
	}
	
	/**
	 * Setzt rekursiv eine menge von Optionen aus einem Konfigurationsarray.
	 * 
	 * Achtung!!! - Settings dürfen KEINE Arrays sein, da die Rekursion in 
	 * sie hineinlaufen würde.
	 * 
	 * @param Array  $array  Array mit zu ladenden Konfiguration
	 * @param int    $mode   Zu setzender Modus für die einzelnen Einträge
	 */
	public function setMany($config, $mode) {
		$this->setRecursive($config, $mode);
	}
	
	private function setRecursive($config, $mode, $force = false, $path = '') {
		foreach ($config as $key => $value) {
			$currentPath = trim($path.'/'.$key, '/');
			if (is_array($value)) $this->setEntryModesRecursive($value, $mode, $currentPath);
			else $this->setInternal($currentPath, $value, $mode, $force);
		}
	}

	/**
	 * @return sly_Configuration
	 */
	public static function getInstance() {
		if (!self::$instance) self::$instance = new self();
		return self::$instance;
	}

	public function get($key) {
		if (empty($key)) {
			$a1 = $this->staticConfig->getArrayCopy();
			$a2 = $this->localConfig->getArrayCopy();
			return array_replace_recursive();
		}
		
		if (strpos($key, '/') === false) {
			return $this->config[$key];
		}
		
		$path = array_filter(explode('/', $key));
		$res  = $this->config;
		
		foreach ($path as $step) {
			if (!array_key_exists($step, $res)) break;
			$res = $res[$step];
		}
		
		return $res;
	}

	public function has($key) {
		if (strpos($key, '/') === false) {
			return $this->config->offsetExists($key);
		}
		
		$path = array_filter(explode('/', $key));
		$res  = $this->config;
		
		foreach ($path as $step){
			if (!array_key_exists($step, $res)) return false;
			$res = $res[$step];
		}
		
		return !empty($res);
	}
	
	public function setStatic($key, $value) {
		return $this->setInternal($key, $value, sly_Configuration::STORE_STATIC);
	}

	public function setLocal($key, $value) {
		return $this->setInternal($key, $value, sly_Configuration::STORE_LOCAL);
	}

	public function setLocalDefault($key, $value, $force = false) {
		return $this->setInternal($key, $value, sly_Configuration::STORE_LOCAL, $force);
	}
	
	public function set($key, $value, $mode = sly_Configuration::STORE_PROJECT) {
		return $this->setInternal($key, $value, $mode);
	}
	
	protected function setInternal($key, $value, $mode, $force = false) {
		if (empty($key) || !is_string($key)) throw new Exception('Key '.$key.' existiert nicht!');
		if (is_array($value)) throw new Exception('Wert darf kein Array sein. Bitte ArrayObject stattdessen nehmen!');
		if (empty($mode)) $mode = sly_Configuration::STORE_PROJECT;
		
		$this->setMode($key, $mode);
		if ($mode == sly_Configuration::STORE_STATIC) {
			return $this->staticConfig->set($key, $value);
		}
		
		if ($mode == sly_Configuration::STORE_LOCAL) {
			return $this->localConfig->set($key, $value);
		}
		
		if ($mode == sly_Configuration::STORE_LOCAL_DEFAULT) {
			if ($force || !$this->localConfig->has($key)) {
				return $this->localConfig->set($key, $value);
			}
			return false;
		}
		
		// case: sly_Configuration::STORE_PROJECT
		return $this->projectConfig->set($key, $value);
	}
	
	protected function setMode($key, $mode) {
		if ($mode == sly_Configuration::STORE_LOCAL_DEFAULT) $mode = sly_Configuration::STORE_LOCAL;
		if (checkMode($key, $mode)) return;
		if (array_key_exists($key, $this->mode)) {
			throw new Exception('Mode für '.$key.' wurde bereits auf '.$this->mode[$key].' gesetzt.');
		}
		$this->mode[$key] = $mode;
	}

	protected function getMode($key) {
		if (!array_key_exists($key, $this->mode)) {
			if (empty($key)) trigger_error('Key '.$key.' existiert nicht!', E_USER_NOTICE);
			return null;
		}
		return $this->mode[$key];
	}
	
	protected function checkMode($key, $mode) {
		if ($mode == sly_Configuration::STORE_LOCAL_DEFAULT) $mode = sly_Configuration::STORE_LOCAL;
		return !isset($this->mode[$key]) || $this->mode[$key] == $mode;
	}

	protected function flush() {
		file_put_contents($this->getLocalCacheFile(), '<?php $config = '.var_export($this->localConfig, true).';');
		sly_Core::getPersistentRegistry()->set('sly_ProjectConfig', $this->projectConfig);
	}
	
	private function __destruct() {
		$this->flush();
	}
	
}
