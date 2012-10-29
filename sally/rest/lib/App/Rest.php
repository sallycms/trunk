<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Rest extends sly_App_Base {
	protected $request = null;

	public function isBackend() {
		return false;
	}

	public function initialize() {
		$container = $this->getContainer();

		// init request
		$this->request = $container->getRequest();

		// load static config
		$this->loadStaticConfig($container);

		// load base english translations
		$i18n = new sly_I18N(sly_Core::getDefaultLocale(), SLY_SALLYFOLDER.'/backend/lang', false);
		$container->setI18N($i18n);

		// and now init the rest (addOns, listeners, ...)
		parent::initialize();
	}

	public function run() {
		$container  = $this->getContainer();
		$dispatcher = $container->getDispatcher();

		// find controller
		$this->router = new sly_Router_Rest($container->getRequest());
		$this->router->loadConfiguration($container->getConfig());

		// let addOns extend our router rule set
		$router = $dispatcher->filter('SLY_REST_ROUTER', $this->router, array('app' => $this));

		if (!($router instanceof sly_Router_Rest)) {
			throw new LogicException('Expected a sly_Router_Rest as the result from SLY_REST_ROUTER.');
		}

		$this->router = $router;

		// if no special controller was found, we use the article controller
		if (!$this->router->hasMatch()) {
			$response = new sly_Response('Invalid URL given.', 404);
			$response->send();
			return;
		}

		$controller = $this->router->getController();
		$action     = $this->router->getAction();

		// test the controller
		$className = $this->getControllerClass($controller);

		try {
			$this->getController($className);
		}
		catch (sly_Controller_Exception $e) {
			if ($e->getCode() === 404) {
				$response = new sly_Response('', 404);
				$response->send();
				return;
			}
		}

		// let the core know where we are
		$this->controller = $controller;
		$this->action     = $action;

		// notify the addOns
		$this->notifySystemOfController();

		// do it, baby
		$response = $this->dispatch($controller, $action);

		if (!($retval instanceof sly_Response)) {
			throw new LogicException('Controllers must return a Response, got '.gettype($response).'.');
		}

		// send the response :)
		$response->send();
	}

	public function getControllerClassPrefix() {
		return 'sly_Controller_Rest';
	}

	public function getCurrentControllerName() {
		return $this->controller;
	}

	public function getCurrentAction() {
		return $this->action;
	}

	protected function handleControllerError(Exception $e, $controller, $action) {
		// throw away all content (including notices and warnings)
		while (ob_get_level()) ob_end_clean();

		// TODO
	}

	protected function loadStaticConfig(sly_Container $container) {
		$container->getConfig()->loadStatic(SLY_SALLYFOLDER.'/rest/config/static.yml');
	}
}
