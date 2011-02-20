<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup cache
 */
class sly_Cache_XCache extends sly_Cache_Abstract {
	public function getMaxKeyLength() {
		return 200; // unbekannt -> Schätzwert
	}

	public function hasLocking() {
		return false;
	}

	public static function isAvailable() {
		// Wir müssen auch prüfen, ob Werte gespeichert werden können (oder ob nur der Opcode-Cache aktiviert ist).
		return function_exists('xcache_set') && xcache_set('test', 1, 1);
	}

	protected function _getRaw($key) {
		return xcache_get($key);
	}

	protected function _get($key) {
		return unserialize(xcache_get($key));
	}

	protected function _setRaw($key, $value, $expiration) {
		if (is_object($value)) {
			throw new sly_Cache_Exception('Objekte können nicht raw in XCache gespeichert werden!');
		}

		return xcache_set($key, $value, $expiration);
	}

	protected function _set($key, $value, $expiration) {
		return xcache_set($key, serialize($value), $expiration);
	}

	protected function _delete($key) {
		return xcache_unset($key);
	}

	protected function _isset($key) {
		return xcache_isset($key);
	}

	protected function _increment($key) {
		return xcache_inc($key) !== false;
	}
}