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
class sly_Layout_Navigation_Backend {
	private $groups       = array();
	private $currentGroup = null;

	public function __construct() {
		$this->addGroup('system', t('base_navigation'));
		$this->addGroup('addon', t('addons'));
	}

	public function init() {
		$user = sly_Util_User::getCurrentUser();

		if ($user !== null) {
			$isAdmin = $user->isAdmin();

			// Core-Seiten initialisieren

			if ($isAdmin || $user->hasRight('pages', 'structure')) {
				$hasClangPerm = $isAdmin || count($user->getAllowedCLangs()) > 0;

				if ($hasClangPerm) {
					$this->addPage('system', 'structure');
				}
			}

			if ($isAdmin || $user->hasRight('pages', 'mediapool')) {
				$this->addPage('system', 'mediapool', null, true);
			}

			if ($isAdmin || $user->hasRight('pages', 'user')) {
				$this->addPage('system', 'user');
			}

			if ($isAdmin || $user->hasRight('pages', 'addons')) {
				$this->addPage('system', 'addon', t('addons'));
			}

			if ($isAdmin) {
				$system = $this->addPage('system', 'system');
				$system->addSubpage('system', t('settings'));
				$system->addSubpage('system_languages', t('languages'));

				if (!sly_Core::isDeveloperMode()) {
					$handler = sly_Core::getErrorHandler();

					if (get_class($handler) === 'sly_ErrorHandler_Production') {
						$system->addSubpage('system_errorlog', t('errorlog'));
					}
				}
			}
		}
	}

	/**
	 * Creates a new navigation group and returns it.
	 *
	 * @param string $name
	 * @param string $title
	 * @return sly_Layout_Navigation_Group
	 */
	public function addGroup($name, $title) {
		$group = new sly_Layout_Navigation_Group($name, $title);
		$this->addGroupObj($group);
		return $group;
	}

	/**
	 * Creates a new Page and returns it.
	 *
	 * @param string $group
	 * @param string $name
	 * @param string $title
	 * @param boolean $popup
	 * @param string $pageParam
	 * @return sly_Layout_Navigation_Page
	 */
	public function addPage($group, $name, $title = null, $popup = false, $pageParam = null) {
		$page = new sly_Layout_Navigation_Page($name, $title, $popup, $pageParam);
		$this->addPageObj($group, $page);
		return $page;
	}

	/**
	 * Adds a navigation group object to the navigation.
	 *
	 * @param sly_Layout_Navigation_Group $group
	 */
	public function addGroupObj(sly_Layout_Navigation_Group $group) {
		$this->groups[$group->getName()] = $group;
		$this->currentGroup = $group;
	}

	/**
	 * Adds a navigation page object to the navigation/group.
	 *
	 * @param sly_Layout_Navigation_Group $group
	 * @param sly_Layout_Navigation_Page $page
	 */
	public function addPageObj($group, sly_Layout_Navigation_Page $page) {
		$group = $group === null ? $this->currentGroup : $this->groups[$group];
		$group->addPage($page);
	}

	/**
	 * Return last insertet group.
	 *
	 * @return sly_Layout_Navigation_Group
	 */
	public function getCurrentGroup() {
		return $this->currentGroup;
	}

	/**
	 * Returns the navigation groups.
	 *
	 * @return array
	 */
	public function getGroups() {
		return $this->groups;
	}

	/**
	 *
	 * @param string $name
	 * @return sly_Layout_Navigation_Group
	 */
	public function getGroup($name) {
		return isset($this->groups[$name]) ? $this->groups[$name] : null;
	}

	/**
	 * Gets a Page from the Navigation.
	 *
	 * @param string $name
	 * @param string $group
	 * @return sly_Layout_Navigation_Page
	 */
	public function get($name, $group) {
		$pages = $this->groups[$group]->getPages();
		foreach ($pages as $p) if ($p->getName() === $name || $p->getPageParam() === $name) return $p;
		return null;
	}

	/**
	 * Returns a Page found by its name or null.
	 *
	 * @param string $name
	 * @return sly_Layout_Navigation_Page
	 */
	public function find($name) {
		foreach (array_keys($this->groups) as $group) {
			$p = $this->get($name, $group);
			if ($p) return $p;
		}

		return null;
	}

	/**
	 * Checks if a Page exists
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function hasPage($name) {
		foreach (array_keys($this->groups) as $group) {
			if ($this->get($name, $group)) return true;
		}

		return false;
	}

	/**
	 * Returns the active backend page.
 	 *
	 * @return sly_Layout_Navigation_Page
	 */
	public function getActivePage() {
		foreach ($this->groups as $group) {
			foreach ($group->getPages() as $p) if ($p->isActive()) return $p;
		}

		return null;
	}

	/**
	 * Return the group of the active backend page.
	 *
	 * @return sly_Layout_Navigation_Group
	 */
	public function getActiveGroup() {
		foreach ($this->groups as $group) {
			foreach ($group->getPages() as $p) if ($p->isActive()) return $group;
		}

		return null;
	}

	/**
	 * Removes a Group, found by its name, from the Navigation. Returns true on success, false
	 * on error.
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function removeGroup($name) {
		$hasIt = isset($this->groups[$name]);
		unset($this->groups[$name]);
		return $hasIt;
	}

	/**
	 * Removes a Page from the Navigation. Returns true on success, false
	 * on error.
	 *
	 * @param sly_Layout_Navigation_Page $name
	 * @return boolean
	 */
	public function removePage($name) {
		foreach ($this->groups as $gName => $group) {
			if ($this->get($name, $gName)) {
				return $group->removePage($name);
			}
		}

		return false;
	}

	/**
	 * Removes a Subpage from a Navigation Page. Returns true on success, false
	 * on error.
	 *
	 * @param sly_Layout_Navigation_Page $page
	 * @param sly_Layout_Navigation_Subpage $subpage
	 * @return boolean
	 */
	public function removeSubpage($page, $subpage) {
		foreach (array_keys($this->groups) as $group) {
			$pageObj = $this->get($page, $group);

			if ($pageObj) {
				return $pageObj->removeSubpage($subpage);
			}
		}

		return false;
	}
}
