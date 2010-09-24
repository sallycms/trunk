<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool extends sly_Controller_Sally {
	protected $warning;
	protected $info;
	protected $i18n;
	protected $category;
	protected $selectBox;

	public function init() {
		global $REX;

		// load our i18n stuff
		sly_Core::getI18N()->appendFile(SLY_INCLUDE_PATH.'/lang/pages/mediapool/');

		$this->i18n    = sly_I18N_Subset::create('pool_');
		$this->info    = sly_request('info', 'string');
		$this->warning = sly_request('warning', 'string');
		$this->args    = sly_requestArray('args', 'string');

		$this->getCurrentCategory();
		$this->initOpener();

		// -------------- Header

		$subline = array(
			array('',       $this->i18n->msg('file_list')),
			array('upload', $this->i18n->msg('file_insert'))
		);

		if ($this->isMediaAdmin()) {
			$subline[] = array('structure', $this->i18n->msg('cat_list'));
			$subline[] = array('sync',      $this->i18n->msg('sync_files'));
		}

		// ArgUrl an Menü anhängen

		$args = '&amp;'.$this->getArgumentString();

		foreach ($subline as &$item) {
			$item[2] = '';
			$item[3] = $args;
		}

		$subline = rex_register_extension_point('PAGE_MEDIAPOOL_MENU', $subline);
		$layout  = sly_Core::getLayout();

		$layout->pageHeader($this->i18n->msg('media'), $subline);
	}

	protected function getArgumentString($separator = '&amp;') {
		$args = array();

		foreach ($this->args as $name => $value) {
			$args['args['.$name.']'] = $value;
		}

		return http_build_query($args, '', $separator);
	}

	protected function getCurrentCategory() {
		global $REX;

		if ($this->category === null) {
			$category = sly_request('rex_file_category', 'int', -1);
			$service  = sly_Service_Factory::getService('Media_Category');

			if ($category == -1) {
				$category = rex_session('media[rex_file_category]', 'int');
			}

			$category = $service->findById($category);
			$category = $category ? $category->getId() : 0;

			rex_set_session('media[rex_file_category]', $category);
			$this->category = $category;
		}

		return $this->category;
	}

	protected function initOpener() {
		$this->opener = sly_request('opener_input_field', 'string', rex_session('media[opener_input_field]', 'string'));
		rex_set_session('media[opener_input_field]', $this->opener);
	}

	protected function getOpenerLink(/* OOMedia | sly_Model_Media_Medium */ $file) {
		global $I18N;

		$field    = $this->opener;
		$link     = '';
		$title    = sly_html($file->getTitle());
		$filename = $file->getFilename();
		$uname    = urlencode($filename);

		if ($field == 'TINYIMG') {
			if (OOMedia::_isImage($filename)) {
				$link = '<a href="javascript:insertImage(\''.$uname.'\',\''.$title.'\')">'.$I18N->msg('pool_image_get').'</a> | ';
			}
		}
		elseif ($field == 'TINY') {
			$link = '<a href="javascript:insertLink(\''.$uname.'\')">'.$I18N->msg('pool_link_get').'</a>';
		}
		elseif ($field != '') {
			$link = '<a href="javascript:selectMedia(\''.$uname.'\')">'.$I18N->msg('pool_file_get').'</a>';

			if (substr($field, 0, 14) == 'REX_MEDIALIST_') {
				$link = '<a href="javascript:selectMedialist(\''.$uname.'\')">'.$I18N->msg('pool_file_get').'</a>';
			}
		}

		return $link;
	}

	protected function getFiles() {
		$cat   = $this->getCurrentCategory();
		$where = 'f.category_id = '.$cat;

		if (isset($this->args['types'])) {
			$types = explode(',', $this->args['types']);

			foreach ($types as $i => $type) {
				$types[$i] = 'f.filename LIKE "%.'.preg_replace('#[^a-z0-9]#i', '', $type).'"';
			}

			$where .= ' AND ('.implode(' OR ', $types).')';
		}

		$db     = sly_DB_Persistence::getInstance();
		$prefix = sly_Core::config()->get('DATABASE/TABLE_PREFIX');
		$query  = 'SELECT id FROM '.$prefix.'file f WHERE '.$where.' ORDER BY f.updatedate DESC';
		$query  = rex_register_extension_point('MEDIA_LIST_QUERY', $query, array('category_id' => $cat));
		$files  = array();

		$db->query($query);

		foreach ($db as $row) {
			$files[$row['id']] = OOMedia::getMediaById($row['id']);
		}

		return $files;
	}

	public function index() {
		$this->render('views/mediapool/toolbar.phtml');
		$this->render('views/mediapool/index.phtml');
	}

	public function batch() {
		if (!empty($_POST['delete'])) {
			return $this->delete();
		}

		return $this->move();
	}

	public function move() {
		global $I18N, $REX;

		if (!$this->isMediaAdmin()) {
			return $this->index();
		}

		$files = sly_postArray('selectedmedia', 'int', array());

		if (empty($files)) {
			$this->warning = $I18N->msg('pool_selectedmedia_error');
			return $this->index();
		}

		$db   = sly_DB_Persistence::getInstance();
		$what = array('category_id' => $this->category, 'updateuser' => $REX['USER']->getValue('login'), 'updatedate' => time());
		$db->update('file', $what, array('id' => $files));

		$this->info = $I18N->msg('pool_selectedmedia_moved');
		$this->index();
	}

	public function delete() {
		global $I18N, $REX;

		if (!$this->isMediaAdmin()) {
			return $this->index();
		}

		$files = sly_postArray('selectedmedia', 'int', array());

		if (empty($files)) {
			$this->warning = $I18N->msg('pool_selectedmedia_error');
			return $this->index();
		}

		foreach ($files as $fileID) {
			$media = OOMedia::getMediaById($fileID);

			if ($media) {
				$retval = $this->deleteMedia($media);
			}
			else {
				$this->warning[] = $I18N->msg('pool_file_not_found');
			}
		}

		$this->index();
	}

	protected function deleteMedia(OOMedia $media) {
		global $I18N, $REX;

		$filename = $media->getFileName();

		// TODO: Is $this->isMediaAdmin() redundant? The user rights are already checked in delete()...

		if ($this->isMediaAdmin() || $REX['USER']->hasPerm('media['.$media->getCategoryId().']')) {
			$usages = $media->isInUse();

			if ($usages === false) {
				if ($media->delete() !== false) {
					sly_Core::dispatcher()->notify('SLY_MEDIA_DELETED', $media);
					$this->info[] = $I18N->msg('pool_file_deleted');
				}
				else {
					$this->warning[] = $I18N->msg('pool_file_delete_error_1', $filename);
				}
			}
			else {
				$tmp   = array();
				$tmp[] = $I18N->msg('pool_file_delete_error_1', $filename).'. '.$I18N->msg('pool_file_delete_error_2').':<br />';
				$tmp[] = '<ul>';

				foreach ($usages as $usage) {
					if (!empty($usage['link'])) {
						$tmp[] = '<li><a href="javascript:openPage(\''.sly_html($usage['link']).'\')">'.sly_html($usage['title']).'</a></li>';
					}
					else {
						$tmp[] = '<li>'.sly_html($usage['title']).'</li>';
					}
				}

				$tmp[] = '</ul>';
				$this->warning[] = implode("\n", $tmp);
			}
		}
		else {
			$this->warning[] = $I18N->msg('no_permission');
		}
	}

	public function checkPermission() {
		global $REX;
		return !empty($REX['USER']);
	}

	protected function isMediaAdmin() {
		global $REX;
		return $REX['USER']->hasPerm('admin[]') || $REX['USER']->hasPerm('media[0]');
	}

	protected function canAccessFile(OOMedia $file) {
		return $this->canAccessCategory($file->getCategoryId());
	}

	protected function canAccessCategory($cat) {
		global $REX;
		return $this->isMediaAdmin() || $REX['USER']->hasPerm('media['.intval($cat).']');
	}

	protected function getCategorySelect() {
		global $REX, $I18N;

		if ($this->selectBox === null) {
			$this->selectBox = sly_Form_Helper::getMediaCategorySelect('rex_file_category', null, $REX['USER']);
			$this->selectBox->setLabel($I18N->msg('pool_kats'));
			$this->selectBox->setMultiple(false);
			$this->selectBox->setAttribute('value', $this->getCurrentCategory());
		}

		return $this->selectBox;
	}

	protected function createFileObject($filename, $type, $title, $category, $origFilename = null) {
		$size = getimagesize($filename);

		// finfo:             PHP >= 5.3, PECL fileinfo
		// mime_content_type: PHP >= 4.3 (deprecated)

		if (empty($type)) {
			// if it's an image, we know the type
			if (isset($size['mime'])) {
				$type = $size['mime'];
			}

			// or else try the new, recommended way
			elseif (function_exists('finfo_file')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$type  = finfo_file($finfo, $filename);
			}

			// argh, let's see if this old one exists
			elseif (function_exists('mime_content_type')) {
				$type = mime_content_type($filename);
			}

			// fallback to a generic type
			else {
				$type = 'application/octet-stream';
			}
		}

		$file = new sly_Model_Media_Medium();
		$file->setFiletype($type);
		$file->setTitle($title);
		$file->setOriginalName(basename($origFilename === null ? $filename : $origFilename));
		$file->setFilename(basename($filename));
		$file->setFilesize(filesize($filename));
		$file->setCategoryId((int) $category);
		$file->setRevision(0); // totally useless...
		$file->setReFileId(0); // even more useless
		$file->setCreateColumns();

		if ($size) {
			$file->setWidth($size[0]);
			$file->setHeight($size[1]);
		}

		return $file;
	}

	protected function createFilename($filename, $doSubindexing = true) {
		global $REX;

		$newFilename = strtolower($filename);
		$newFilename = str_replace(array('ä','ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $newFilename);
		$newFilename = preg_replace('#[^a-z0-9.+-]#i', '_', $newFilename);
		$lastDotPos  = strrpos($newFilename, '.');
		$fileLength  = strlen($newFilename);

		// split up extension

		if ($lastDotPos !== false) {
			$newName = substr($newFilename, 0, $lastDotPos);
			$newExt  = substr($newFilename, $lastDotPos);
		}
		else {
			$newName = $newFilename;
			$newExt  = '';
		}

		// check for disallowed extensions (broken by design...)

		if (in_array($newExt, $REX['MEDIAPOOL']['BLOCKED_EXTENSIONS'])) {
			$newName .= $newExt;
			$newExt   = '.txt';
		}

		$newFilename = $newName.$newExt;

		if ($doSubindexing) {
			// increment filename suffix until an unique one was found

			if (file_exists($REX['MEDIAFOLDER'].'/'.$newFilename)) {
				for ($cnt = 1; file_exists($REX['MEDIAFOLDER'].'/'.$newName.'_'.$cnt.$newExt); ++$cnt);
				$newFilename = $newName.'_'.$cnt.$newExt;
			}
		}

		return $newFilename;
	}

	protected function getDimensions($width, $height, $maxWidth, $maxHeight) {
		if ($width > $maxWidth) {
			$factor  = (float) $maxWidth / $width;
			$width   = $maxWidth;
			$height *= $factor;
		}

		if ($height > $maxHeight) {
			$factor  = (float) $maxHeight / $height;
			$height  = $maxHeight;
			$width  *= $factor;
		}

		return array(ceil($width), ceil($height));
	}
}