<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup util
 */
class sly_Util_Requirements {
	const OK      = 2; ///< int
	const WARNING = 1; ///< int
	const FAILED  = 0; ///< int

	/**
	 * @return array
	 */
	public function phpVersion() {
		$version = $this->numPHPVersion();
		$current = $this->versionValue($version);
		$best    = $this->versionValue('5.4.3');
		$ok      = $this->versionValue('5.2.1');

		return $this->result($version, $current >= $best ? self::OK : ($current >= $ok ? self::WARNING : self::FAILED));
	}

	/**
	 * @return array
	 */
	public function pdoDriverVersion(sly_DB_PDO_Connection $connection, array $constraints) {
		$pdo     = $connection->getPDO();
		$version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

		if (version_compare($version, $constraints[self::WARNING], '<')) {
			return $this->failed(t('setup_database_server_too_old', $version, $constraints[self::WARNING]));
		}

		if (version_compare($version, $constraints[self::WARNING], '>=') && version_compare($version, $constraints[self::OK], '<')) {
			return $this->warning(t('setup_database_server_old_but_okay', $version, $constraints[self::OK]));
		}

		return $this->ok(t('setup_database_server_is_up_to_date', $version, $constraints[self::OK]));
	}

	/**
	 * @return array
	 */
	public function execTime() {
		$maxTime = ini_get('max_execution_time');

		if ($maxTime >= 20) {
			return $this->ok($maxTime.'s');
		}
		else {
			if (ini_set('max_execution_time', 20) === false) {
				return $this->failed($maxTime.'s');
			}
			else {
				return $this->warning(t('exec_time', $maxTime));
			}
		}
	}

	/**
	 * @return array
	 */
	public function memoryLimit() {
		$mem = sly_ini_get('memory_limit');
		$mem/=1024*1024;

		if ($mem >= 64) return $this->ok($mem.'MB');
		else if (ini_set('memory_limit', '64M') !== false) return $this->warning($mem.'MB');
		else if ($mem >= 16) return $this->ok($mem.'MB');
		else if (empty($mem)) return $this->warning(t('unknown'));
		else return $this->failed($mem.'MB');
	}

	/**
	 * @return array
	 */
	public function nonsenseSecurity() {
		$safe_mode    = ini_get('safe_mode');
		$open_basedir = ini_get('open_basedir');

		if (!$safe_mode && !$open_basedir) return $this->ok(t('none'));
		else if (!$safe_mode && $open_basedir) return $this->warning('open_basedir');
		else return $this->failed($open_basedir ? t('safemode_openbasedir') : 'safe_mode');
	}

	/**
	 * @param  string $ext
	 * @return string
	 */
	private function numPHPVersion($ext = '') {
		$result = empty($ext) ? phpversion() : phpversion($ext);
		$pos    = strpos($result, '-');

		return $pos === false ? $result : substr($result, 0, $pos);
	}

	/**
	 * @param  string $version
	 * @param  double $versishifton
	 * @return int
	 */
	private function versionValue($version, $shift = 0.01) {
		$result = 0;
		$factor = 1.0;

		do {
			$pos = strpos($version, '.');
			if ($pos === false) $pos = strlen($version);

			$cur     = substr($version, 0, $pos);
			$version = substr($version, $pos+1);

			$result += $factor * $cur;
			$factor *= $shift;
		}
		while ($version != '');

		return $result;
	}

	/**
	 * @param  mixed $result
	 * @return string
	 */
	public function getClassName($result) {
		static $classes = array(self::WARNING => 'warning', self::OK => 'ok', self::FAILED => 'failed');
		$status = is_array($result) ? $result['status'] : (int) $result;
		return isset($classes[$status]) ? $classes[$status] : 'unknown';
	}

	/**
	 * @param  string $text
	 * @param  int    $status
	 * @return array
	 */
	private function result($text, $status) {
		return array('text' => sly_translate($text), 'status' => $status);
	}

	/**
	 * @param  string $text
	 * @return array
	 */
	private function ok($text) {
		return $this->result($text, self::OK);
	}

	/**
	 * @param  string $text
	 * @return array
	 */
	private function failed($text) {
		return $this->result($text, self::FAILED);
	}

	/**
	 * @param  string $text
	 * @return array
	 */
	private function warning($text) {
		return $this->result($text, self::WARNING);
	}
}
