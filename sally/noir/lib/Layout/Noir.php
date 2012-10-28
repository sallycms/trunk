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
 * @ingroup layout
 */
class sly_Layout_Noir extends sly_Layout_Backend {
	public function __construct() {
		$locale  = sly_Core::getI18N()->getLocale();
		$favicon = sly_Core::config()->get('noir/favicon');
		$base    = sly_Util_HTTP::getBaseUrl(true).'/';

		$this->addCSSFile('assets/less/import.less');
		$this->setTitle(sly_Core::getProjectName().' - ');
		$this->addMeta('robots', 'noindex,nofollow');
		$this->setBase($base.'noir/');

		if ($favicon) {
			$this->setFavIcon($base.$favicon);
		}

		$locale = explode('_', $locale, 2);
		$locale = reset($locale);

		if (strlen($locale) === 2) {
			$this->setLanguage(strtolower($locale));
		}
	}

	protected function getViewFile($file) {
		$full = SLY_SALLYFOLDER.'/noir/views/'.$file;
		if (file_exists($full)) return $full;

		return parent::getViewFile($file);
	}
}
