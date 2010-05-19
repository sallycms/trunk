<?php

class sly_Controller_Module extends sly_Controller_Base
{
	protected $func = '';
	
	public function init()
	{
		global $I18N;
		
		rex_title($I18N->msg('modules'), array(
			array('',        $I18N->msg('modules')),
			array('actions', $I18N->msg('actions'))
		));

		print '<div class="sly-content">';
	}
	
	public function teardown()
	{
		print '</div>';
	}

	public function index()
	{
		$this->listModules();
		return true;
	}

	public function add()
	{
		global $I18N;

		$save  = sly_post('save', 'boolean', false);
		$user  = sly_Core::config()->get('USER');
		$login = $user->getValue('login');
		$now   = time();
		
		if ($save) {
			$module = array(
				'name'        => sly_post('name', 'string', ''),
				'eingabe'     => sly_post('input', 'string', ''),
				'ausgabe'     => sly_post('output', 'string', ''),
				'category_id' => sly_post('category_id', 'int', 0),
				'createuser'  => $login,
				'updateuser'  => $login,
				'createdate'  => $now,
				'updatedate'  => $now,
				'attributes'  => '',
				'revision'    => 0
			);

			$service = sly_Service_Factory::getService('Module');
			$service->create($module);
			
			print rex_info($I18N->msg('module_added'));
			$this->listModules();
			return true;
		}

		$this->func = 'add';
		$this->render('views/module/edit.phtml', array('module' => null));
		return true;
	}
	
	public function edit()
	{
		global $I18N;
			
		$module = $this->getModule();
		
		if ($module === null) {
			$this->listModules();
			return false;
		}
		
		$save = sly_post('save', 'boolean', false);
		
		if ($save) {
			$module->setName(sly_post('name', 'string', ''));
			$module->setInput(sly_post('input', 'string', ''));
			$module->setOutput(sly_post('output', 'string', ''));
			
			$service = sly_Service_Factory::getService('Module');
			$module  = $service->save($module);

			print rex_info($I18N->msg('module_updated').' | '.$I18N->msg('articles_updated'));

			$goon = sly_post('goon', 'boolean', false);

			if (!$goon) {
				$this->listModules();
				return true;
			}
		}

		$params     = array('module' => $module);
		$actions    = sly_Service_Factory::getService('Action')->find(null, null, 'name', null, null);
		$this->func = 'edit';
		
		$this->render('views/module/edit.phtml', $params);
		
		if (!empty($actions)) {
			$this->render('views/module/module_action.phtml', $params);
		}
		
		return true;
	}
	
	public function delete()
	{
		global $REX, $I18N;
		
		$module = $this->getModule();
		
		if ($module === null) {
			$this->listModules();
			return false;
		}
		
		$service = sly_Service_Factory::getService('Module');
		$usages  = $service->findUsages($module);
		
		if (!empty($usages)) {
			$errormsg     = array();
			$languages    = sly_Core::config()->get('CLANG');
			$multilingual = count($languages) > 1;
			
			foreach ($usages as $articleID => $usage) {
				$article = $usage['article'];
				$clangID = $usage['clang'];
				$aID     = $article->getId();
				$label   = $article->getName().' ['.$aID.']';
				
				if ($multilingual) {
					$label = '('.rex_translate($languages[$clangID]).') '.$label;
				}

				$errormsg[] = '<li><a href="index.php?page=content&amp;article_id='.$aID.'&amp;clang='.$clangID.'&amp;ctype='.$usage['ctype'].'">'.sly_html($label).'</a></li>';
			}

			$moduleName = sly_html($module->getName());
			$warning    = '<ul>'.implode("\n", $errormsg).'</ul>';
			
			print rex_warning($I18N->msg('module_cannot_be_deleted', $moduleName).$warning);
			return false;
		}
		
		$service->deleteWithActions($module);
		print rex_info($I18N->msg('module_deleted'));
		
		$this->listModules();
		return true;
	}

	public function checkPermission()
	{
		return true;
	}

	protected function listModules()
	{
		$service = sly_Service_Factory::getService('Module');
		$modules = $service->find(null, null, 'name', null, null);
		$this->render('views/module/list.phtml', array('modules' => $modules));
	}
	
	protected function getModule()
	{
		global $I18N;
		
		$moduleID = sly_request('id', 'int', 0);
		$service  = sly_Service_Factory::getService('Module');
		$module   = $service->findById($moduleID);
		
		if (!$moduleID || $module === null) {
			print rex_warning($I18N->msg('module_not_exists'));
			return null;
		}
		
		return $module;
	}
}
