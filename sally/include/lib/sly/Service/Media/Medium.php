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
 * DB Model Klasse für Medien
 *
 * @author  christoph@webvariants.de
 * @ingroup service
 */
class sly_Service_Media_Medium extends sly_Service_Model_Base {
	protected $tablename = 'file';

	protected function makeObject(array $params) {
		return new sly_Model_Media_Medium($params);
	}
}