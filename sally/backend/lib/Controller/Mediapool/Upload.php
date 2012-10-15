<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Upload extends sly_Controller_Mediapool {
	public function indexAction() {
		$this->init('index');
		$this->render('mediapool/upload.phtml', array(), false);
	}

	public function uploadAction() {
		$this->init('upload');

		if (!empty($_FILES['file_new']['name']) && $_FILES['file_new']['name'] != 'none') {
			$title = sly_post('ftitle', 'string');
			$cat   = $this->getCurrentCategory();

			if (!$this->canAccessCategory($cat)) {
				$cat = 0;
			}

			// add the actual database record
			$file = $this->saveMedium($_FILES['file_new'], $cat, $title);

			// close the popup, if requested

			$callback = sly_request('callback', 'string');

			if ($callback && sly_post('saveandexit', 'boolean', false) && $file !== null) {
				$this->render('mediapool/upload_js.phtml', compact('file', 'callback'), false);
				exit;
			}
			elseif ($file !== null) {
				sly_Core::getCurrentApp()->redirect('mediapool', array('info' => $this->info));
			}
		}
		else {
			$this->warning = t('file_not_found_maybe_too_big');
		}

		$this->indexAction();
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($action === 'upload') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function saveMedium(array $fileData, $category, $title) {
		$file = null;

		try {
			$file       = sly_Util_Medium::upload($fileData, $category, $title);
			$this->info = t('file_added');
		}
		catch (sly_Exception $e) {
			$this->warning = $e->getMessage();
		}

		return $file;
	}
}
