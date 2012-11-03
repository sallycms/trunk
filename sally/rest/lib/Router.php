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
 * @author christoph@webvariants.de
 * @since  0.8
 */
class sly_Rest_Router extends sly_Router_Base {
	protected $appBaseUrl;

	public function __construct($appBaseUrl, array $routes = array()) {
		parent::__construct($routes);
		$this->appBaseUrl = $appBaseUrl;
	}

	public function match(sly_Request $request) {
		$requestUri = $this->getRequestUri($request);

		foreach ($this->routes as $route => $values) {
			$method = $this->getMethodContraint($route);

			// skip if method does not match
			if ($method !== null && !$request->isMethod($method)) {
				continue;
			}

			$regex = $this->buildRegex($route);
			$match = null;

			if (preg_match("#^$regex#u", $requestUri, $match)) {
				$this->setupRequest($request, $match, $values);
				return true;
			}
		}

		return false;
	}

	public function loadConfiguration(sly_Configuration $config) {
		$restConfig = $config->get('rest');
		$verbs      = array(
			'index'  => 'GET %s',
			'get'    => 'GET %s/%s',
			'create' => 'POST %s',
			'update' => 'PUT %s/%s',
			'delete' => 'DELETE %s/%s'
		);

		foreach ($restConfig['controller_routes'] as $className => $config) {
			$base = $config['base'];
			$id   = $config['identifier'];
			$data = isset($routeConfig['values']) ? $routeConfig['values'] : array();

			foreach ($config['verbs'] as $verb) {
				if (!isset($verbs[$verb])) {
					throw new sly_Exception('Unknown verb "'.$verb.'" in REST routing config for controller '.$className.'.');
				}

				$route = sprintf($verbs[$verb], $base, $id);

				$data['controller'] = $className;
				$data['action']     = $verb;

				$this->addRoute($route, $data);
			}
		}

		foreach ($restConfig['routes'] as $route) {
			$data = isset($route['values']) ? $route['values'] : array();

			$data['controller'] = $route['controller'];
			$data['action']     = $route['action'];

			$this->addRoute($route['pattern'], $data);
		}
	}

	// transform '/:controller/' into '/(?P<controller>[a-z0-9_-])/'
	protected function buildRegex($route) {
		// remove method constraint
		$route = preg_replace('#^([A-Z]+) +#', '', $route);
		$route = rtrim($route, '/');

		// make sure we anchor all REST routes on the same base URL (/rest/....)
		if (!sly_Util_String::startsWith($route, $this->appBaseUrl)) {
			$route = ltrim($route, '/');
			$route = $this->appBaseUrl.'/'.$route;
		}

		return parent::buildRegex($route);
	}

	protected function getMethodContraint($route) {
		return preg_match('#^([A-Z]+) +#', $route, $match) ? $match[1] : null;
	}
}
