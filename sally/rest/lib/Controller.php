<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_Rest_Controller implements sly_Controller_Interface {
	protected $request      = null; ///< sly_Request    the current request
	protected $container    = null; ///< sly_Container  the DI container

	/**
	 * Set DI container
	 *
	 * This method is called by the application before the action is executed.
	 *
	 * @param sly_Container $container  the container the controller should use
	 */
	public function setContainer(sly_Container $container) {
		$this->container = $container;
	}

	/**
	 * get DI container
	 *
	 * @return sly_Container
	 */
	public function getContainer() {
		return $this->container;
	}

	/**
	 * Set request
	 *
	 * This method is called by the application before the action is executed.
	 *
	 * @param sly_Request $request  the request the controller should act upon
	 */
	public function setRequest(sly_Request $request) {
		$this->request = $request;
	}

	/**
	 * get request
	 *
	 * @return sly_Request
	 */
	public function getRequest() {
		return $this->request;
	}

	protected function response($content = array(), $status = 200, array $headers = array()) {
		$response = new sly_Response();

		foreach($headers as $name => $value) {
			$response->setHeader($name, $value);
		}

		$response->setStatusCode($status);
		$response->setContentType('application/json', 'UTF-8');
		$response->setContent(json_encode($content));

		return $response;
	}
}