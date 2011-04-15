<?php

/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup util
 *
 * @author zozi@webvariants.de
 */
class sly_Util_Category {

	/**
	 * checks wheter a category exists or not
	 *
	 * @param  int $categoryId
	 * @return boolean
	 */
	public static function exists($categoryId) {
		return self::isValid(self::findById($categoryId));
	}

	/**
	 *
	 * @param sly_Model_Category $category
	 * @return boolean 
	 */
	public static function isValid($category) {
		return is_object($category) && ($category instanceof sly_Model_Category);
	}

	/**
	 *
	 * @param  int $categoryId
	 * @param  int $clang
	 * @return sly_Model_Article 
	 */
	public static function findById($categoryId, $clang = null) {
		return sly_Service_Factory::getCategoryService()->findById($categoryId, $clang);
	}
	
	/**
	 *
	 * @param  int     $parentId
	 * @param  boolean $ignore_offlines
	 * @param  int     $clang
	 * @return array 
	 */
	public static function findByParentId($parentId, $ignore_offlines = false, $clang = null) {
		return sly_Service_Factory::getCategoryService()->findByParentId($parentId, $ignore_offlines, $clang);
	}

	/**
	 *
	 * @param  boolean $ignore_offlines
	 * @param  int $clang
	 * @return array 
	 */
	public static function getRootCategories($ignore_offlines = false, $clang = null) {
		return self::findByParentId(0, $ignore_offlines, $clang);
	}

}