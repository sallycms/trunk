<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup service
 */
abstract class sly_Service_Factory {

	private static $services = array();

	/**
	 * Return a instance on a Service
	 *
	 * @param string $modelName
	 *
	 * @return sly_Service_Base an implementation of sly_Service_Base
	 */
	public static function getService($modelName) {
		if (!isset(self::$services[$modelName])){
			$serviceName = 'sly_Service_'.$modelName;

			if (!class_exists($serviceName)) {
				throw new sly_Exception('sly_Service_Factory: Service für '.$modelName.' wurde nicht gefunden.');
			}

			$service = new $serviceName();

			self::$services[$modelName] = $service;
		}

		return self::$services[$modelName];
	}

	/**
	 * @return sly_Service_Slice  The slice service instance
	 */
	public static function getSliceService() {
		return self::getService('Slice');
	}

	/**
	 * @return sly_Service_Template  The template service instance
	 */
	public static function getTemplateService() {
		return self::getService('Template');
	}

	/**
	 * @return sly_Service_Module  The module service instance
	 */
	public static function getModuleService() {
		return self::getService('Module');
	}

	/**
	 * @return sly_Service_AddOn  The module service instance
	 */
	public static function getAddOnService() {
		return self::getService('AddOn');
	}

	/**
	 * @return sly_Service_Plugin  The module service instance
	 */
	public static function getPluginService() {
		return self::getService('Plugin');
	}
}
