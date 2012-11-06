<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Rest_Controller_Login extends sly_Rest_Controller {

	public function loginAction() {
		$request   = $this->getRequest();
		$container = $this->getContainer();
		$username = $request->post('username', 'string');
		$password = $request->post('password', 'string');
		$loginOK  = $container['sly-service-user']->login($username, $password);

		if ($loginOK !== true) {
			$status = 403;
		}
		else {
			// notify system
			$container['sly-dispatcher']->notify('SLY_BE_LOGIN', $user);
			$status = 200;
		}
		return $this->response(array(), $status);
	}

	public function logoutAction() {
		$container = $this->getContainer();
		$user      = sly_Util_User::getCurrentUser();

		if ($user) {
			// notify system
			$container['sly-dispatcher']->notify('SLY_BE_LOGOUT', $user);
			$container['sly-service-user']->logout();
		}

		return $this->response();
	}

	public function checkPermission($action) {
		return true;
	}
}

