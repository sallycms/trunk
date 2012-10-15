<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_User extends sly_Controller_Backend implements sly_Controller_Interface {
	protected function init() {
		$layout = sly_Core::getLayout();
		$layout->pageHeader(t('users'));
	}

	public function indexAction() {
		$this->init();
		$this->listUsers();
	}

	public function addAction() {
		$this->init();

		if (sly_post('save', 'boolean', false)) {
			$password = sly_post('userpsw', 'string');
			$login    = sly_post('userlogin', 'string');
			$timezone = sly_post('timezone', 'string');
			$service  = sly_Service_Factory::getUserService();
			$flash    = sly_Core::getFlashMessage();
			$params   = array(
				'login'       => $login,
				'name'        => sly_post('username', 'string'),
				'description' => sly_post('userdesc', 'string'),
				'status'      => sly_post('userstatus', 'boolean', false),
				'timezone'    => $timezone ? $timezone : null,
				'psw'         => $password,
				'rights'      => $this->getRightsFromForm(null)
			);

			try {
				$service->create($params);
				$flash->prependInfo(t('user_added'), true);

				return $this->redirectResponse();
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
			}
		}

		$this->render('user/edit.phtml', array('user' => null, 'func' => 'add'), false);
	}

	public function editAction() {
		$this->init();

		$user = $this->getUser();

		if ($user === null) {
			return $this->listUsers();
		}

		$save        = sly_post('save', 'boolean', false);
		$service     = sly_Service_Factory::getUserService();
		$currentUser = sly_Util_User::getCurrentUser();
		$isSelf      = $currentUser->getId() === $user->getId();
		$safeMode    = $user->isAdmin() && !$currentUser->isAdmin();

		if ($save) {
			$status = sly_post('userstatus', 'boolean', false) ? 1 : 0;
			$tz     = sly_post('timezone', 'string', '');

			if ($isSelf || $safeMode) {
				$status = $user->getStatus();
			}

			$user->setName(sly_post('username', 'string'));
			$user->setDescription(sly_post('userdesc', 'string'));
			$user->setStatus($status);
			$user->setUpdateColumns();
			$user->setTimezone($tz ? $tz : null);

			// change password

			$password = sly_post('userpsw', 'string');

			if (!empty($password) && $password != $user->getPassword()) {
				$user->setPassword($password);
			}

			$user->setRights($this->getRightsFromForm($user));

			// save it
			$apply = sly_post('apply', 'string');
			$flash = sly_Core::getFlashMessage();

			try {
				$user = $service->save($user);
				$flash->prependInfo(t('user_updated'), true);

				return $this->redirectResponse($apply ? array('func' => 'edit', 'id' => $user->getId()) : '');
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
				$apply = true;
			}

			if (!$apply) {
				$this->listUsers();
				return true;
			}
		}

		$params = array('user' => $user, 'func' => 'edit');
		$this->render('user/edit.phtml', $params, false);
	}

	public function deleteAction() {
		$this->init();

		$user = $this->getUser(true);

		if ($user === null) {
			return $this->redirectResponse();
		}

		$service = sly_Service_Factory::getUserService();
		$current = sly_Util_User::getCurrentUser();
		$flash   = sly_Core::getFlashMessage();

		try {
			if ($current->getId() == $user->getId()) {
				throw new sly_Exception(t('you_cannot_delete_yourself'));
			}

			if ($user->isAdmin() && !$current->isAdmin()) {
				throw new sly_Exception(t('you_cannot_delete_admins'));
			}

			$user->delete();
			$flash->prependInfo(t('user_deleted'), true);
		}
		catch (Exception $e) {
			$flash->preprendWarning($e->getMessage(), true);
		}

		return $this->redirectResponse();
	}

	public function viewAction() {
		$this->init();

		$user = $this->getUser();

		if ($user === null) {
			return $this->listUsers();
		}

		$params = array('user' => $user);
		$this->render('user/view.phtml', $params, false);
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('add', 'edit', 'delete'))) {
			$session = sly_Core::getSession();
			if (!$session->checkCsrfToken()) return false;
		}

		if ($user->isAdmin()) {
			return true;
		}

		if (!$user->hasRight('pages', 'user')) {
			return false;
		}

		if (in_array($action, array('add', 'edit', 'delete'))) {
			return $user->hasRight('user', $action);
		}

		return true;
	}

	protected function listUsers() {
		sly_Table::setElementsPerPageStatic(20);

		$search  = sly_Table::getSearchParameters('users');
		$paging  = sly_Table::getPagingParameters('users', true, false);
		$service = sly_Service_Factory::getUserService();
		$where   = null;

		if (!empty($search)) {
			$db    = sly_DB_Persistence::getInstance();
			$where = 'login LIKE ? OR description LIKE ? OR name LIKE ?';
			$where = str_replace('?', $db->quote('%'.$search.'%'), $where);
		}

		// allow addOns to filter on their own and append something like ' AND id IN (the,ids,the,addon,found)'
		// do not only do this when !empty($search) to allow addOns to have their own filtering GUI
		$where = sly_Core::dispatcher()->filter('SLY_USER_FILTER_WHERE', $where, array('search' => $search, 'paging' => $paging));

		$users = $service->find($where, null, 'name', $paging['start'], $paging['elements']);
		$total = $service->count($where);

		$this->render('user/list.phtml', compact('users', 'total'), false);
	}

	protected function getUser($forcePost = false) {
		$userID  = $forcePost ? sly_post('id', 'int', 0) : sly_request('id', 'int', 0);
		$service = sly_Service_Factory::getUserService();
		$user    = $service->findById($userID);

		return $user;
	}

	protected function getBackendLocales() {
		$langpath = SLY_SALLYFOLDER.'/backend/lang';
		$locales  = sly_I18N::getLocales($langpath);
		$result   = array('' => t('use_default_locale'));

		foreach ($locales as $locale) {
			$i18n = new sly_I18N($locale, $langpath, false);
			$result[$locale] = $i18n->msg('lang');
		}

		return $result;
	}

	protected function getPossibleStartpages() {
		$service = sly_Service_Factory::getAddOnService();
		$addons  = $service->getAvailableAddOns();

		$startpages = array();
		$startpages['structure'] = t('structure');
		$startpages['profile']   = t('profile');

		foreach ($addons as $addon) {
			$page = $service->getComposerKey($addon, 'page', null);
			$name = $service->getComposerKey($addon, 'name', $addon);

			if ($page) {
				$startpages[$page] = sly_translate($name);
			}
		}

		return $startpages;
	}

	protected function getRightsFromForm($user) {
		$permissions = array();
		$curUser     = sly_Util_User::getCurrentUser();
		$isAdmin     = $curUser->isAdmin();
		$isSelfEdit  = $user !== null && $curUser->getId() === $user->getId();
		$safeMode    = $user && $user->isAdmin() && !$curUser->isAdmin();

		// admin status

		if ($safeMode || ($isAdmin && ($isSelfEdit || sly_post('is_admin', 'boolean', false)))) {
			$permissions[] = 'admin[]';
		}

		// backend locale and startpage

		$backendLocale  = sly_post('userperm_mylang', 'string');
		$backendLocales = $this->getBackendLocales();
		$startpage      = sly_post('userperm_startpage', 'string');
		$startpages     = $this->getPossibleStartpages();

		if (isset($backendLocales[$backendLocale])) {
			$permissions[] = 'be_lang['.$backendLocale.']';
		}

		if (isset($startpages[$startpage])) {
			$permissions[] = 'startpage['.$startpage.']';
		}

		// and build the permission string

		return '#'.implode('#', $permissions).'#';
	}
}
