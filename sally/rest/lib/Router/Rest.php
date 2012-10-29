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
	protected $formats = array(
		'alpha' => '[a-zA-Z]+',
		'alnum' => '[a-zA-Z0-9]+',
		'digit' => '[0-9]+',
		'hex'   => '[0-9a-f]+',
		'ident' => '[a-zA-Z_][a-zA-Z0-9-_]*',
		'int'   => '(0|[1-9][0-9]*)',
		'lower' => '[a-z]+',
		'upper' => '[A-Z]+'
	);

	public function __construct(sly_Request $request, array $routes = array()) {
		parent::__construct($routes);
		$this->setRequest($request);
	}

	public function setRequest(sly_Request $request) {
		$this->request = $request;
		$this->match   = false;
	}

	public function setParameterFormat($type, $format) {
		if (!ctype_lower($type)) {
			throw new sly_Exception('Parameter type must consist of only lowercase letters.');
		}

		$this->formats[$type] = $format;
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
		$routes = $config->get('rest/routes', array());
	}

	// transform '/:controller/' into '/(?P<controller>[a-z0-9_-])/'
	protected function buildRegex($route) {
		$route = preg_replace('#^([A-Z]+) +#', '', $route);
		$route = rtrim($route, '/');
		$ident = '([a-z_][a-z0-9-_]*)(?:@([a-z]+))?';

		preg_match_all("#:($ident)#iu", $route, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$ident = $match[2];
			$type  = isset($match[3]) ? $match[3] : 'ident';

			if (!isset($this->formats[$type])) {
				throw new Exception('Invalid parameter type '.$type.' requested.');
			}

			$regex = sprintf('(?P<%s>%s)', $ident, $this->formats[$type]);
			$route = str_replace($match[0], $regex, $route);
		}

		return str_replace('#', '\\#', $route);
	}

	protected function getMethodContraint($route) {
		return preg_match('#^([A-Z]+) +#', $route, $match) ? $match[1] : null;
	}
}
