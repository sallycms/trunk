<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

/**
 * @author christoph@webvariants.de
 */
class sly_Service_AddOn extends sly_Service_AddOn_Base
{
	public function __construct()
	{
		$this->data       = sly_Core::config()->get('ADDON');
		$this->i18nPrefix = 'addon_';
	}

	public function install($addonName, $installDump = true)
	{
		global $REX;

		$addonDir    = $this->baseFolder($addonName);
		$installFile = $addonDir.'install.inc.php';
		$installSQL  = $addonDir.'install.sql';
		$configFile  = $addonDir.'config.inc.php';
		$filesDir    = $addonDir.'files';

		$state = $this->extend('PRE', 'INSTALL', $addonName, true);

		// Prüfen des Addon Ornders auf Schreibrechte,
		// damit das Addon später wieder gelöscht werden kann

		$state = rex_is_writable($addonDir);

		if ($state) {
			if (is_readable($installFile)) {
				$this->req($installFile, $addonName);

				$hasError = !empty($REX['ADDON']['installmsg'][$addonName]);

				if ($hasError) {
					$state = t('no_install', $addonName).'<br />';

					if ($hasError) {
						$state .= $REX['ADDON']['installmsg'][$addonName];
					}
					else {
						$state .= $this->I18N('no_reason');
					}
				}
				else {
					if (is_readable($configFile)) {
						if (!$this->isActivated($addonName)) {
							$this->req($configFile, $addonName);
						}
					}
					else {
						$state = t('config_not_found');
					}

					if ($installDump && $state === true && is_readable($installSQL)) {
						$state = rex_install_dump($installSQL);

						if ($state !== true) {
							$state = 'Error found in install.sql:<br />'.$state;
						}
					}

					$this->setProperty($addonName, 'install', true);
				}
			}
			else {
				$state = t('install_not_found');
			}
		}

		$state = $this->extend('POST', 'INSTALL', $addonName, $state);

		// Dateien kopieren

		if ($state === true && is_dir($filesDir)) {
			if (!rex_copyDir($filesDir, $this->publicFolder($addonName), $REX['MEDIAFOLDER'])) {
				$state = t('install_cant_copy_files');
			}
		}

		$state = $this->extend('POST', 'ASSET_COPY', $addonName, $state);

		if ($state !== true) {
			$this->setProperty($addonName, 'install', false);
		}

		return $state;
	}

	/**
	 * De-installiert ein Addon
	 *
	 * @param $addonName Name des Addons
	 */
	public function uninstall($addonName)
	{
		$addonDir      = $this->baseFolder($addonName);
		$uninstallFile = $addonDir.'uninstall.inc.php';
		$uninstallSQL  = $addonDir.'uninstall.sql';
		$config        = sly_Core::config();

		$state = $this->extend('PRE', 'UNINSTALL', $addonName, true);

		if (is_readable($uninstallFile)) {
			$this->req($uninstallFile, $addonName);

			$hasError = $config->has('ADDON/installmsg/'.$addonName);

			if ($hasError) {
				$state = $this->I18N('no_uninstall', $addonName).'<br />';

				if ($hasError) {
					$state .= $config->get('ADDON/installmsg/'.$addonName);
				}
				else {
					$state .= $this->I18N('no_reason');
				}
			}
			else {
				$state = $this->deactivate($addonName);

				if ($state === true && is_readable($uninstallSQL)) {
					$state = rex_install_dump($uninstallSQL);

					if ($state !== true) {
						$state = 'Error found in uninstall.sql:<br />'.$state;
					}
				}

				if ($state === true) {
					$this->setProperty($addonName, 'install', false);
				}
			}
		}
		else {
			$state = $this->I18N('uninstall_not_found');
		}

		$state = $this->extend('POST', 'UNINSTALL', $addonName, $state);

		if ($state === true) $state = $this->deletePublicFiles($addonName);
		if ($state === true) $state = $this->deleteInternalFiles($addonName);

		$state = $this->extend('POST', 'ASSET_DELETE', $addonName, $state);

		if ($state !== true) {
			$this->setProperty($addonName, 'install', true);
		}

		return $state;
	}

	/**
	 * Aktiviert ein Addon
	 *
	 * @param $addonName Name des Addons
	 */
	public function activate($addonName)
	{
		if ($this->isActivated($addonName)) {
			return true;
		}

		if ($this->isInstalled($addonName)) {
			$state = $this->extend('PRE', 'ACTIVATE', $addonName, true);

			if ($state === true) {
				$this->setProperty($addonName, 'status', true);
			}
		}
		else {
			$state = t('no_activation', $addonName);
		}

		return $this->extend('POST', 'ACTIVATE', $addonName, $state);
	}

	/**
	 * Deaktiviert ein Addon
	 *
	 * @param $addonName Name des Addons
	 */
	public function deactivate($addonName)
	{
		if (!$this->isActivated($addonName)) {
			return true;
		}

		$state = $this->extend('PRE', 'DEACTIVATE', $addonName, true);

		if ($state === true) {
			$this->setProperty($addonName, 'status', false);
		}

		return $this->extend('POST', 'DEACTIVATE', $addonName, $state);
	}

	public function delete($addonName)
	{
		$state = $this->extend('PRE', 'DELETE', $addonName, true);

		if ($state === true) {
			$systemAddons = sly_Core::config()->get('SYSTEM_ADDONS');

			if (in_array($addonName, $systemAddons)) {
				$state = $this->I18N('addon_systemaddon_delete_not_allowed');
			}
			else {
				$state = $this->deleteHelper($addonName);
			}
		}

		return $this->extend('POST', 'DELETE', $addonName, $state);
	}

	public function baseFolder($addonName)
	{
		$dir = SLY_INCLUDE_PATH.DIRECTORY_SEPARATOR.'addons'.DIRECTORY_SEPARATOR;
		if (!empty($addonName)) $dir .= $addonName.DIRECTORY_SEPARATOR;
		return $dir;
	}

	public function publicFolder($addonName)
	{
		return $this->dynFolder('public', $addonName);
	}

	public function internalFolder($addonName)
	{
		return $this->dynFolder('internal', $addonName);
	}

	protected function dynFolder($type, $addonName)
	{
		$config = sly_Core::config();
		$dir    = SLY_DYNFOLDER.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$addonName;

		sly_Util_Directory::create($dir);
		return $dir;
	}

	protected function dynPath($type, $addonName)
	{
		$config = sly_Core::config();
		$dir    = SLY_BASE.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$addonName;

		sly_Util_Directory::create($dir);
		return $dir;
	}

	protected function extend($time, $type, $addonName, $state)
	{
		return rex_register_extension_point('SLY_ADDON_'.$time.'_'.$type, $state, array('addon' => $addonName));
	}

	public function deletePublicFiles($addonName)
	{
		return $this->deleteFiles('public', $addonName);
	}

	public function deleteInternalFiles($addonName)
	{
		return $this->deleteFiles('internal', $addonName);
	}

	protected function deleteFiles($type, $addonName)
	{
		$dir   = $this->dynFolder($type, $addonName);
		$state = $this->extend('PRE', 'DELETE_'.strtoupper($type), $addonName, true);

		if ($state !== true) {
			return $state;
		}

		if (is_dir($dir) && !rex_deleteDir($dir, true)) {
			return $this->I18N('install_cant_delete_files');
		}

		return $this->extend('POST', 'DELETE_'.strtoupper($type), $addonName, true);
	}

	protected function I18N()
	{
		global $I18N;

		$args    = func_get_args();
		$args[0] = $this->i18nPrefix.$args[0];

		return rex_call_func(array($I18N, 'msg'), $args, false);
	}

	public function isAvailable($addonName)
	{
		return $this->isInstalled($addonName) && $this->isActivated($addonName);
	}

	public function isInstalled($addonName)
	{
		return $this->getProperty($addonName, 'install', false) == true;
	}

	public function isActivated($addonName)
	{
		return $this->getProperty($addonName, 'status', false) == true;
	}

	public function getVersion($addonName, $default = null)
	{
		$version     = $this->getProperty($addonName, 'version', null);
		$versionFile = $this->baseFolder($addonName).'/version';

		if ($version === null && file_exists($versionFile)) {
			$version = file_get_contents($versionFile);
		}

		return $version === null ? $default : $version;
	}

	public function getAuthor($addonName, $default = null)
	{
		return $this->getProperty($addonName, 'author', $default);
	}

	public function getSupportPage($addonName, $default = null)
	{
		return $this->getProperty($addonName, 'supportpage', $default);
	}

	public function getIcon($addonName)
	{
		$directory = $this->publicFolder($addonName);
		$base      = $this->baseFolder($addonName);
		$icon      = $this->getProperty($addonName, 'icon', null);

		if ($icon === null) {
			if (file_exists($directory.'/images/icon.png')) {
				$icon = 'images/'.$addonName.'/icon.png';
			}
			elseif (file_exists($directory.'/images/icon.gif')) {
				$icon = 'images/'.$addonName.'/icon.gif';
			}
			elseif (file_exists($base.'/images/icon.png')) {
				$icon = $base.'/images/icon.png';
			}
			elseif (file_exists($base.'/images/icon.gif')) {
				$icon = $base.'/images/icon.gif';
			}
			else {
				$icon = false;
			}
		}

		return $icon;
	}

	/**
	 * Setzt eine Eigenschaft des Addons.
	 *
	 * @param  string $addon     Name des Addons
	 * @param  string $property  Name der Eigenschaft
	 * @param  mixed  $property  Wert der Eigenschaft
	 * @return mixed             der gesetzte Wert
	 */
	public function setProperty($addonName, $property, $value)
	{
		return sly_Core::config()->set('ADDON/'.$addonName.'/'.$property, $value);
	}

	/**
	 * Gibt eine Eigenschaft des AddOns zurück.
	 *
	 * @param  string $addonName  Name des Addons
	 * @param  string $property   Name der Eigenschaft
	 * @param  mixed  $default    Rückgabewert, falls die Eigenschaft nicht gefunden wurde
	 * @return string             Wert der Eigenschaft des Addons
	 */
	public function getProperty($addonName, $property, $default = null)
	{
		return sly_Core::config()->has('ADDON/'.$addonName.'/'.$property) ? sly_Core::config()->get('ADDON/'.$addonName.'/'.$property) : $default;
	}

	/**
	 * Gibt ein Array aller registrierten Addons zurück.
	 *
	 * Ein Addon ist registriert, wenn es dem System bekannt ist (addons.yaml).
	 *
	 * @return array  Array aller registrierten Addons
	 */
	public function getRegisteredAddons()
	{
		$data = sly_Core::config()->get('ADDON');
		$data = !empty($data) ? array_keys($data) : array();
		natsort($data);
		return $data;
	}

	/**
	 * Gibt ein Array von verfügbaren Addons zurück.
	 *
	 * Ein Addon ist verfügbar, wenn es installiert und aktiviert ist.
	 *
	 * @return array  Array der verfügbaren Addons
	 */
	public function getAvailableAddons()
	{
		$avail = array();

		foreach ($this->getRegisteredAddons() as $addonName) {
			if ($this->isAvailable($addonName)) $avail[] = $addonName;
		}

		return $avail;
	}

	/**
	 * Prüft, ob ein System-Addon vorliegt
	 *
	 * @param  string $addonName  Name des Addons
	 * @return boolean            true, wenn es sich um ein System-Addon handelt, sonst false
	 */
	public function isSystemAddon($addonName)
	{
		$systemAddOns = sly_Core::config()->get('SYSTEM_ADDONS');
		return in_array($addonName, $systemAddOns);
	}

}