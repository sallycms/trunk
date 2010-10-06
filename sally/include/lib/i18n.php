<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Sprach-Unterstützung von REDAXO
 *
 * @deprecated  sly_I18N direkt nutzen, die API ist identisch
 */
class i18n extends sly_I18N {
	public function __construct($locale = 'de_de', $path) {
		$type = defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : E_USER_WARNING;
		trigger_error('i18n ist deprecated. Benutzen Sie stattdessen sly_I18N.', $type);
		parent::__construct($locale, $path);
	}
}