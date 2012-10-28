<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Noir extends sly_App_Backend {
	public function initialize() {
		$isSetup = sly_Core::isSetup();

		// redirect to legacy backend to do setup
		if ($isSetup) {
			$request = $this->getContainer()->getRequest();
			$target  = $request->getBaseUrl(true).'/backend/';
			$text    = 'Bitte führe das <a href="'.sly_html($target).'">Setup</a> aus, um SallyCMS zu nutzen.';

			sly_Util_HTTP::tempRedirect($target, array(), $text);
		}

		parent::initialize();
	}

	public function getControllerClassPrefix() {
		return 'sly_Controller_Noir';
	}

	/**
	 * Event handler
	 */
	public function initNavigation(array $params) {
		$layout = $this->getContainer()->getLayout();
		$layout->getNavigation()->init();
	}

	protected function loadStaticConfig(sly_Container $container) {
		$container->getConfig()->loadStatic(SLY_SALLYFOLDER.'/noir/config/static.yml');
	}

	protected function initLayout(sly_Container $container) {
		$container->setLayout(new sly_Layout_Noir());
	}

	protected function initI18N(sly_Container $container, $locale) {
		$i18n = new sly_I18N($locale, SLY_SALLYFOLDER.'/backend/lang');
		$i18n->appendFile(SLY_SALLYFOLDER.'/noir/lang'); // overwrite legacy translations
		$container->setI18N($i18n);
	}
}
