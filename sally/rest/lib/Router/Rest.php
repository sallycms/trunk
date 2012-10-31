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
class sly_Router_Rest extends sly_Router_Base {
	protected $request;
	protected $appBaseUrl;

	public function __construct(sly_Request $request, $appBaseUrl, array $routes = array()) {
		parent::__construct($routes);

		$this->setRequest($request);
		$this->appBaseUrl = $appBaseUrl;
	}

	public function setRequest(sly_Request $request) {
		$this->request = $request;
		$this->match   = false;
	}

	public function match() {
		if ($this->match === false) {
			$this->match = null;
			$requestUri  = $this->getRequestUri();

			foreach ($this->routes as $route => $values) {
				$method = $this->getMethodContraint($route);

				// skip if method does not match
				if ($method !== null && !$this->request->isMethod($method)) {
					continue;
				}

				$regex = $this->buildRegex($route);
				$match = null;

				if (preg_match("#^$regex$#u", $requestUri, $match)) {
					$match['method'] = $this->request->getMethod();
					$this->match = array($match, $values);
					break;
				}
			}
		}

		return $this->match;
	}

	public function getMethod() {
		return $this->get('method');
	}

	public function getRequestUri() {
		$requestUri = $this->request->getRequestUri();

		if (empty($requestUri)) {
			throw new LogicException('Cannot route without a request URI.');
		}

		$host    = sly_Util_HTTP::getBaseUrl();     // 'http://example.com'
		$base    = sly_Util_HTTP::getBaseUrl(true); // 'http://example.com/sallyinstall'
		$request = $host.$requestUri;               // 'http://example.com/sallyinstall/backend/system'

		if (mb_substr($request, 0, mb_strlen($base)) !== $base) {
			throw new LogicException('Base URI mismatch.');
		}

		$req = mb_substr($request, mb_strlen($base)); // '/backend/system'

		// remove query string
		if (($pos = mb_strpos($req, '?')) !== false) {
			$req = mb_substr($req, 0, $pos);
		}

		// remove script name
		if (sly_Util_String::endsWith($req, '/index.php')) {
			$req = mb_substr($req, 0, -10);
		}

		return rtrim($req, '/');
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
