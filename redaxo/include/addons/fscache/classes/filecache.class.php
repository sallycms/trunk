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

class FileCache implements sly_ICache{
	
	const NAMESPACE = 'corecache';
	
	private $cache;
	
	public function __construct(){
		$this->cache = WV_DeveloperUtils::getCache();
	}
	
	public function set($namespace, $key, $value){
		$this->cache->set(self::NAMESPACE.'.'.$namespace, $key, $value);
	}
	
	public function get($namespace, $key, $default){
		return $this->cache->get(self::NAMESPACE.'.'.$namespace, $key, $default);
	}
	
	public function delete($namespace, $key){
		$this->cache->delete(self::NAMESPACE.'.'.$namespace, $key);
	}
	
	public function flush(){
		self::flushstatic();
	}
	
	public static function flushstatic(){
		$cache = WV_DeveloperUtils::getCache();
		$cache->flush(self::NAMESPACE, true);
	}
}<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

class FileCache implements sly_ICache{
	
	const MY_NAMESPACE = 'corecache';
	
	private $cache;
	
	public function __construct(){
		$this->cache = WV_DeveloperUtils::getCache();
	}
	
	public function set($namespace, $key, $value){
		$this->cache->set(self::MY_NAMESPACE.'.'.$namespace, $key, $value);
	}
	
	public function get($namespace, $key, $default){
		return $this->cache->get(self::MY_NAMESPACE.'.'.$namespace, $key, $default);
	}
	
	public function delete($namespace, $key){
		$this->cache->delete(self::MY_NAMESPACE.'.'.$namespace, $key);
	}
	
	public function flush(){
		self::flushstatic();
	}
	
	public static function flushstatic(){
		$cache = WV_DeveloperUtils::getCache();
		$cache->flush(self::MY_NAMESPACE, true);
	}
}