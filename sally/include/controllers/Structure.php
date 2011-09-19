<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Structure extends sly_Controller_Sally {
	protected $categoryId;
	protected $clangId;
	protected $info;
	protected $warning;
	protected $renderAddCategory  = false;
	protected $renderEditCategory = false;
	protected $renderAddArticle   = false;
	protected $renderEditArticle  = false;

	protected static $viewPath;

	protected function init() {
		parent::init();
		self::$viewPath = 'views/structure/';

		$this->categoryId = sly_request('category_id', 'rex-category-id');
		$this->clangId    = sly_request('clang', 'rex-clang-id', sly_Core::config()->get('START_CLANG_ID'));

		sly_Core::getLayout()->pageHeader(t('title_structure'), $this->getBreadcrumb());
		$this->render('views/toolbars/languages.phtml', array('clang' => $this->clangId, 'sprachen_add' => '&amp;category_id='.$this->categoryId));

		print sly_Core::dispatcher()->filter('PAGE_STRUCTURE_HEADER', '', array(
			'category_id' => $this->categoryId,
			'clang'       => $this->clangId
		));
	}

	protected function index() {
		$this->view();
	}

	protected function view() {
		$advancedMode = sly_Util_User::getCurrentUser()->hasRight('advancedMode[]');

		$cat_service     = sly_Service_Factory::getCategoryService();
		$currentCategory = $cat_service->findById($this->categoryId, $this->clangId);
		$categories      = $cat_service->find(array('re_id' => $this->categoryId, 'clang' => $this->clangId), null, 'catprior ASC');

		$art_service = sly_Service_Factory::getArticleService();
		$articles    = $art_service->findArticlesByCategory($this->categoryId, false, $this->clangId);

		if (!empty($this->info))    print rex_info($this->info);
		if (!empty($this->warning)) print rex_warning($this->warning);

		$this->render(self::$viewPath.'category_table.phtml', array(
			'categories'      => $categories,
			'currentCategory' => $currentCategory,
			'advancedMode'    => $advancedMode,
			'statusTypes'     => $cat_service->getStati()
		));

		$this->render(self::$viewPath.'article_table.phtml', array(
			'articles'        => $articles,
			'advancedMode'    => $advancedMode,
			'statusTypes'     => $art_service->getStati(),
			'canEdit'         => $this->canEditCategory($this->categoryId)
		));

		if ($this->renderAddArticle || $this->renderAddCategory || $this->renderEditArticle || $this->renderEditCategory) {
			$javascript = 'jQuery(function($){$("#rex-form-field-name").focus();});';
			sly_Core::getLayout()->addJavaScript($javascript);
		}
	}

	protected function editStatusCategory() {
		$editId = sly_get('edit_id', 'rex-category-id');

		if ($editId) {
			try {
				$service = sly_Service_Factory::getCategoryService();
				$service->changeStatus($editId, $this->clangId);
				$this->info = t('category_status_updated');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
			}
		}
		else {
			$this->warning = t('no_such_category');
		}

 		$this->view();
	}

	protected function editStatusArticle() {
		$editId = sly_get('edit_id', 'rex-article-id');

		if ($editId) {
			try {
				$service = sly_Service_Factory::getArticleService();
				$article = $service->findById($editId, $this->clangId);
				$service->changeStatus($article);
				$this->info = t('article_status_updated');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
			}
		}
		else {
			$this->warning = t('no_such_article');
		}

 		$this->view();
	}

	protected function deleteCategory() {
		$editId = sly_get('edit_id', 'rex-category-id');

		if ($editId) {
			try {
				$service = sly_Service_Factory::getCategoryService();
				$service->delete($editId);
				$this->info = t('category_deleted');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
			}
		}
		else {
			$this->warning = t('no_such_category');
		}

 		$this->view();
	}

	protected function deleteArticle() {
		$editId = sly_get('edit_id', 'rex-article-id');

		if ($editId) {
			try {
				$service = sly_Service_Factory::getArticleService();
				$service->delete($editId);
				$this->info = t('article_deleted');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
			}
		}
		else {
			$this->warning = t('no_such_article');
		}

 		$this->view();
	}

	protected function addCategory() {
		if (sly_post('do_add_category', 'boolean')) {
			$name     = sly_post('category_name',     'string');
			$position = sly_post('category_position', 'integer');

			try {
				$service = sly_Service_Factory::getCategoryService();
				$service->add($this->categoryId, $name, 0, $position);
				$this->info = t('category_added_and_startarticle_created');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
				$this->renderAddCategory = true;
			}
		}
		else {
			$this->renderAddCategory = true;
		}

		$this->view();
	}

	protected function addArticle() {
		if (sly_post('do_add_article', 'boolean')) {
			$name     = sly_post('article_name',     'string');
			$position = sly_post('article_position', 'integer');

			try {
				$service = sly_Service_Factory::getArticleService();
				$service->add($this->categoryId, $name, 0, $position);
				$this->info = t('article_added');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
				$this->renderAddArticle = true;
			}
		}
		else {
			$this->renderAddArticle = true;
		}

		$this->view();
	}

	protected function editCategory() {
		$editId = sly_request('edit_id', 'rex-category-id');

		if (sly_post('do_edit_category', 'boolean')) {
			$name     = sly_post('category_name',     'string');
			$position = sly_post('category_position', 'integer');

			try {
				$service = sly_Service_Factory::getCategoryService();
				$service->edit($editId, $this->clangId, $name, $position);
				$this->info = t('category_updated');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
				$this->renderEditCategory = $editId;
			}
		}
		else {
			$this->renderEditCategory = $editId;
		}

		$this->view();
	}

	protected function editArticle() {
		$editId = sly_request('edit_id', 'rex-article-id');

		if (sly_post('do_edit_article', 'boolean')) {
			$name     = sly_post('article_name',     'string');
			$position = sly_post('article_position', 'integer');

			try {
				$service = sly_Service_Factory::getArticleService();
				$service->edit($editId, $this->clangId, $name, $position);
				$this->info = t('article_updated');
			}
			catch (sly_Exception $e) {
				$this->warning = $e->getMessage();
				$this->renderEditArticle = $editId;
			}
		}
		else {
			$this->renderEditArticle = $editId;
		}

		$this->view();
	}

	/**
	 * returns the breadcrumb string
	 *
	 * @return string
	 */
	protected function getBreadcrumb() {
		$result = '';
		$cat    = sly_Util_Category::findById($this->categoryId);

		if ($cat) {
			foreach ($cat->getParentTree() as $parent) {
				if ($this->canEditCategory($parent->getId())) {
					$result .= '<li> : <a href="index.php?page=structure&amp;category_id='.$parent->getId().'&amp;clang='.$this->clangId.'">'.sly_html($parent->getName()).'</a></li>';
				}
			}
		}

		$result = '
			<ul class="sly-navi-path">
				<li>'.t('path').'</li>
				<li> : <a href="index.php?page=structure&amp;category_id=0&amp;clang='.$this->clangId.'">Homepage</a></li>
				'.$result.'
			</ul>
			';

		return $result;
	}

	/**
	 * checks if a user can edit a category
	 *
	 * @param int $categoryId
	 * @return boolean
	 */
	protected function canEditCategory($categoryId) {
		$user = sly_Util_User::getCurrentUser();
		return sly_Util_Category::hasPermissionOnCategory($user, $categoryId);
	}

	protected function canPublishCategory($categoryId) {
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || ($user->hasRight('publishCategory[]') && $this->canEditCategory($categoryId));
	}

	/**
	 * checks action permissions for the current user
	 *
	 * @return boolean
	 */
	protected function checkPermission() {
		$categoryId = sly_request('category_id', 'rex-category-id');
		$user       = sly_Util_User::getCurrentUser();

		if ($this->action == 'index') {
			return!is_null($user);
		}
		elseif (sly_Util_String::startsWith($this->action, 'editStatus')) {
			if($this->action == 'editStatusCategory') {
				$editId = sly_request('edit_id', 'rex-category-id');
				return $this->canPublishCategory($editId);
			}else {
				return $this->canPublishCategory($categoryId);
			}
		}
		elseif (sly_Util_String::startsWith($this->action, 'edit') || sly_Util_String::startsWith($this->action, 'add') || sly_Util_String::startsWith($this->action, 'delete')) {
			return $this->canEditCategory($categoryId);
		}

		return false;
	}
}