<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * @package redaxo4
 */

unset ($REX_ACTION);

$category_id = sly_request('category_id', 'rex-category-id');
$article_id  = sly_request('article_id',  'rex-article-id');
$slice_id    = sly_request('slice_id',    'rex-slice-id', '');
$function    = sly_request('function',    'string');
$slot        = sly_request('slot',        'string');
$clang       = sly_Core::getCurrentClang();
$languages   = sly_Util_Language::findAll();

foreach ($languages as $id => $lang) {
	$languages[$id] = $lang->getName();
}

$article_revision = 0;
$slice_revision   = 0;
$warning          = '';
$global_warning   = '';
$info             = '';
$global_info      = '';

require SLY_INCLUDE_PATH.'/functions/function_rex_content.inc.php';

// check article's existence
$articleService = sly_Service_Factory::getArticleService();
$article        = $articleService->findById($article_id, $clang);

if (is_null($article)) {
	sly_Core::getLayout()->pageHeader(t('content'));
	print rex_warning(t('no_article_available'));
	return;
}

// init services
$typeService     = sly_Service_Factory::getArticleTypeService();
$templateService = sly_Service_Factory::getTemplateService();
$moduleService   = sly_Service_Factory::getModuleService();
$user            = sly_Util_User::getCurrentUser();
$dispatcher      = sly_Core::dispatcher();

// Artikel wurde gefunden - Kategorie holen
$category_id = $article->getCategoryId();

// Kategoriepfad und -rechte

require SLY_INCLUDE_PATH.'/views/toolbars/breadcrumb.phtml';
// $KATout kommt aus dem include
// $KATPERM

$KATout .= '<p>';

if ($article->isStartArticle()) {
	$KATout .= t('start_article').': ';
}
else {
	$KATout .= t('article').': ';
}

$catname = str_replace(' ', '&nbsp;', sly_html($article->getName()));

$KATout .= '<a href="index.php?page=content&amp;article_id='.$article_id.'&amp;mode=edit&amp;clang='.$clang.'">'.$catname.'</a>';
$KATout .= '</p>';

// Titel anzeigen
sly_Core::getLayout()->pageHeader(t('content'), $KATout);

// request params
$mode     = sly_request('mode', 'string', 'edit');
$function = sly_request('function', 'string');
$warning  = sly_request('warning', 'string');
$info     = sly_request('info', 'string');

// Sprachenblock

$sprachen_add = '&amp;mode='.$mode.'&amp;category_id='.$category_id.'&amp;article_id='.$article_id;
require SLY_INCLUDE_PATH.'/views/toolbars/languages.phtml';

// extend menu
print $dispatcher->filter('PAGE_CONTENT_HEADER', '', array(
	'article_id'       => $article_id,
	'clang'            => $clang,
	'function'         => $function,
	'mode'             => $mode,
	'slice_id'         => $slice_id,
	'page'             => 'content',
	'slot'             => $slot,
	'category_id'      => $category_id,
	'article_revision' => &$article_revision,
	'slice_revision'   => &$slice_revision
));

// stop if no permissions
if (!($KATPERM || $user->hasPerm('article['.$article_id.']'))) {
	print rex_warning(t('no_rights_to_edit'));
	return;
}

if ($mode == 'edit' && sly_post('save_article', 'string')) {
	$type    = sly_post('article_type', 'string');
	$article = $articleService->findById($article_id, $clang);

	// change type and update database
	$articleService->setType($article, $type);

	$global_info = t('article_updated');
	$article     = $articleService->findById($article_id, $clang);
}

$hasType     = $article->hasType();
$hasTemplate = false;

if ($hasType) {
	$templateName = $typeService->getTemplate($article->getType());
	$hasTemplate = !empty($templateName) && $templateService->exists($templateName);
}

// validate slot

if ($hasTemplate && !$templateService->hasSlot($templateName, $slot)) {
	$slot = $templateService->getFirstSlot($templateName);
}

$curSlots = $hasTemplate ? $templateService->getSlots($templateName) : false;

// Slice add/edit/delete

if ($hasTemplate && $slot !== null && sly_request('save', 'boolean') && in_array($function, array('add', 'edit', 'delete'))) {
	// check module

	if ($function == 'edit' || $function == 'delete') {
		$module = rex_slice_module_exists($slice_id, $clang);
	}
	else { // add
		$module = sly_post('module', 'string');
	}

	if (!$moduleService->exists($module)) {
		$global_warning = t('module_not_found');
		$slice_id       = '';
		$function       = '';
	}
	else {
		// Rechte am Modul
		if (!$templateService->hasModule($templateName, $module, $slot)) {
			$global_warning = t('no_rights_to_this_function');
			$slice_id       = '';
			$function       = '';
		}
		elseif (!($user->isAdmin() || $user->hasPerm('module['.$module.']') || $user->hasPerm('module[0]'))) {
			$global_warning = t('no_rights_to_this_function');
			$slice_id       = '';
			$function       = '';
		}
		else {
			// Daten einlesen
			$REX_ACTION = array('SAVE' => true);

			foreach (sly_Core::getVarTypes() as $idx => $obj) {
				$REX_ACTION = $obj->getACRequestValues($REX_ACTION);
			}

			// ----- PRE SAVE ACTION [ADD/EDIT/DELETE]
			list($action_message, $REX_ACTION) = rex_execPreSaveAction($module, $function, $REX_ACTION);

			// Statusspeicherung für die rex_article Klasse
			$REX['ACTION'] = $REX_ACTION;

			// Werte werden aus den REX_ACTIONS übernommen wenn SAVE=true

			if (!$REX_ACTION['SAVE']) {
				// DONT SAVE/UPDATE SLICE
				if (!empty($action_message)) {
					$warning = $action_message;
				}
				elseif ($function == 'delete') {
					$warning = t('slice_deleted_error');
				}
				else {
					$warning = t('slice_saved_error');
				}
			}
			else {
				// SAVE / UPDATE SLICE

				$sql          = sly_DB_Persistence::getInstance();
				$sliceService = sly_Service_Factory::getSliceService();

				if ($function === 'add' || $function === 'edit') {
					$values = array(
						'updatedate' => time(),
						'updateuser' => $user->getLogin()
					);

					if ($function == 'edit') {
						$ooslice   = OOArticleSlice::getArticleSliceById($slice_id);
						$realslice = $sliceService->findById($ooslice->getSliceId());
						$realslice->flushValues();
					}
					else {
						$realslice = $sliceService->create(array('module' => $module));

						$values['prior']      = sly_post('prior', 'int');
						$values['article_id'] = $article_id;
						$values['module']     = $module;
						$values['clang']      = $clang;
						$values['slot']       = $slot;
						$values['revision']   = $slice_revision;
						$values['createdate'] = time();
						$values['createuser'] = $user->getLogin();
					}

					$values['slice_id'] = $realslice->getId();

					// speichern falls nötig
					foreach (sly_Core::getVarTypes() as $obj) {
						$obj->setACValues($realslice->getId(), $REX_ACTION, true, false);
					}

					// fire query
					if ($function === 'edit') {
						$sql->update('article_slice', $values, array('id' => $slice_id));
						rex_deleteCacheSliceContent($realslice->getId());
						$info = $action_message.t('block_updated');
					}
					else {
						$sql->insert('article_slice', $values);

						$id   = $sql->lastId();
						$pre  = sly_Core::config()->get('DATABASE/TABLE_PREFIX');
						$info = $action_message.t('block_added');

						$sql->query('UPDATE '.$pre.'article_slice SET prior = prior + 1 '.
							'WHERE article_id = '.$article_id.' AND clang = '.$clang.' AND slot = "'.$slot.'" '.
							'AND prior >= '.$values['prior'].' AND id <> '.$id
						);
					}

					$function = '';
				}
				else {
					if (rex_deleteArticleSlice($slice_id)) {
						$global_info = t('block_deleted');
					}
					else {
						$global_warning = t('block_not_deleted');
					}
				}
				// ----- / SAVE SLICE

				// update article
				$value = array('updatedate' => time(), 'updateuser' => $user->getLogin());
				$sql->update('article', $value, array('id' => $article_id, 'clang' => $clang));

				// POST SAVE ACTION [ADD/EDIT/DELETE]

				list($msg, $actions) = rex_execPostSaveAction($module, $function, $REX_ACTION);
				$info .= $msg;
				$dispatcher->notify('SLY_CONTENT_UPDATED', '', compact('article_id', 'clang'));

				// Update Button wurde gedrückt?

				if (sly_post('btn_save', 'string')) {
					$function = '';
				}
			}
		}
	}

	// Flush slice cache
	sly_Core::cache()->flush(OOArticleSlice::CACHE_NS);
}

// END: Slice add/edit/delete
if ($mode == 'meta') {
	// START: ARTICLE2STARTARTICLE

	if (sly_post('article2startpage', 'string')) {
		if ($user->isAdmin() || $user->hasPerm('article2startpage[]')) {
			if (rex_article2startpage($article_id)) {
				$info = t('content_tostartarticle_ok');
				while (ob_get_level()) ob_end_clean();
				header('Location: index.php?page=content&mode=meta&clang='.$clang.'&slot='.$slot.'&article_id='.$article_id.'&info='.urlencode($info));
				exit;
			}
			else {
				$warning = t('content_tostartarticle_failed');
			}
		}
	}

	// END: ARTICLE2STARTARTICLE
	// START: COPY LANG CONTENT

	if (sly_post('copycontent', 'string')) {
		if ($user->isAdmin() || $user->hasPerm('copyContent[]')) {
			$clang_a = sly_post('clang_a', 'rex-clang-id');
			$clang_b = sly_post('clang_b', 'rex-clang-id');

			if (rex_copyContent($article_id, $article_id, $clang_a, $clang_b)) {
				$info = t('content_contentcopy');
			}
			else {
				$warning = t('content_errorcopy');
			}
		}
	}

	// END: COPY LANG CONTENT
	// START: MOVE ARTICLE

	if (sly_post('movearticle', 'string') && $category_id != $article_id) {
		$category_id_new = sly_post('category_id_new', 'rex-category-id');

		if ($user->isAdmin() || ($user->hasPerm('moveArticle[]') && ($user->hasPerm('csw[0]') || $user->hasPerm('csw['.$category_id_new.']')))) {
			if (rex_moveArticle($article_id, $category_id_new)) {
				$info = t('content_articlemoved');
				while (ob_get_level()) ob_end_clean();
				header('Location: index.php?page=content&article_id='.$article_id.'&mode=meta&clang='.$clang.'&slot='.$slot.'&info='.urlencode($info));
				exit;
			}
			else {
				$warning = t('content_errormovearticle');
			}
		}
		else {
			$warning = t('no_rights_to_this_function');
		}
	}

	// END: MOVE ARTICLE
	// START: COPY ARTICLE

	if (sly_post('copyarticle', 'string')) {
		$category_copy_id_new = sly_post('category_copy_id_new', 'rex-category-id');

		if ($user->isAdmin() || ($user->hasPerm('copyArticle[]') && ($user->hasPerm('csw[0]') || $user->hasPerm('csw['.$category_copy_id_new.']')))) {
			if (($new_id = rex_copyArticle($article_id, $category_copy_id_new)) !== false) {
				$info = t('content_articlecopied');
				while (ob_get_level()) ob_end_clean();
				header('Location: index.php?page=content&article_id='.$new_id.'&mode=meta&clang='.$clang.'&slot='.$slot.'&info='.urlencode($info));
				exit;
			}
			else {
				$warning = t('content_errorcopyarticle');
			}
		}
		else {
			$warning = t('no_rights_to_this_function');
		}
	}

	// END: COPY ARTICLE
	// START: MOVE CATEGORY

	if (sly_post('movecategory', 'string')) {
		$category_id_new = sly_post('category_id_new', 'rex-category-id');

		if ($user->isAdmin() || ($user->hasPerm('moveCategory[]') && (($user->hasPerm('csw[0]') || $user->hasPerm('csw['.$category_id.']')) && ($user->hasPerm('csw[0]') || $user->hasPerm('csw['.$category_id_new.']'))))) {
			if ($category_id != $category_id_new && rex_moveCategory($category_id, $category_id_new)) {
				$info = t('category_moved');
				while (ob_get_level()) ob_end_clean();
				header('Location: index.php?page=content&article_id='.$category_id.'&mode=meta&clang='.$clang.'&slot='.$slot.'&info='.urlencode($info));
				exit;
			}
			else {
				$warning = t('content_error_movecategory');
			}
		}
		else {
			$warning = t('no_rights_to_this_function');
		}
	}

	// END: MOVE CATEGORY
	// START: SAVE METADATA META PAGE

	if (sly_post('savemeta', 'string')) {
		$name   = sly_post('meta_article_name', 'string');
		$sql    = sly_DB_Persistence::getInstance();
		$values = array('name' => $name, 'updatedate' => time(), 'updateuser' => $user->getLogin());

		$sql->update('article', $values, array('id' => $article_id, 'clang' => $clang));

		// update cache
		sly_Core::cache()->delete('sly.article', $article_id.'_'.$clang);

		// notify system
		$info = t('metadata_updated');
		sly_Core::dispatcher()->notify('ART_META_UPDATED', $info, array(
			'id'    => $article_id,
			'clang' => $clang
		));
	}

	// END: SAVE METADATA
}
// START: CONTENT HEAD MENUE

$numSlots = $hasTemplate ? count($curSlots) : 0;
$slotMenu = '';

if ($numSlots > 1) {
	$listElements = array(t($numSlots > 1 ? 'content_types' : 'content_type').' : ');

	foreach ($curSlots as $tmpSlot) {
		$class     = ($tmpSlot == $slot && $mode == 'edit') ? ' class="rex-active"' : '';
		$slotTitle = rex_translate($templateService->getSlotTitle($templateName, $tmpSlot));

		$listElements[] = '<a href="index.php?page=content&amp;article_id='.$article_id.'&amp;clang='.$clang.'&amp;slot='.$tmpSlot.'&amp;mode=edit"'.$class.'>'.$slotTitle.'</a>';
	}

	$listElements = $dispatcher->filter('PAGE_CONTENT_SLOT_MENU', $listElements, array(
		'article_id' => $article_id,
		'clang'      => $clang,
		'function'   => $function,
		'mode'       => $mode,
		'slice_id'   => $slice_id
	));

	$slotMenu  .= '<ul id="rex-navi-slots">';

	foreach ($listElements as $idx => $listElement) {
		$class = '';

		if ($idx == 1) { // das erste Element ist nur Beschriftung -> überspringen
			$class = ' class="rex-navi-first"';
		}

		$slotMenu .= '<li'.$class.'>'.$listElement.'</li>';
	}

	$slotMenu .= '</ul>';
}

$menu         = $slotMenu;
$listElements = array();
$baseURL      = 'index.php?page=content&amp;article_id='.$article_id.'&amp;clang='.$clang.'&amp;slot='.$slot;

if ($mode == 'edit') {
	$listElements[] = '<a href="'.$baseURL.'&amp;mode=edit" class="rex-active">'.t('edit_mode').'</a>';
	$listElements[] = '<a href="'.$baseURL.'&amp;mode=meta">'.t('metadata').'</a>';
}
else {
	$listElements[] = '<a href="'.$baseURL.'&amp;mode=edit">'.t('edit_mode').'</a>';
	$listElements[] = '<a href="'.$baseURL.'&amp;mode=meta" class="rex-active">'.t('metadata').'</a>';
}

$listElements[] = '<a href="../'.$REX['FRONTEND_FILE'].'?article_id='.$article_id.'&amp;clang='.$clang.'" onclick="window.open(this.href); return false;">'.t('show').'</a>';

$listElements = $dispatcher->filter('PAGE_CONTENT_MENU', $listElements, array(
	'article_id' => $article_id,
	'clang'      => $clang,
	'function'   => $function,
	'mode'       => $mode,
	'slice_id'   => $slice_id
));

$menu .= '<ul class="rex-navi-content">';

foreach ($listElements as $idx => $element) {
	$class = $idx == 0 ? ' class="rex-navi-first"' : '';
	$menu .= '<li'.$class.'>'.$element.'</li>';
}

$menu .= '</ul>';

// END: CONTENT HEAD MENUE
// START: AUSGABE

?>
<!-- *** OUTPUT OF ARTICLE-CONTENT - START *** -->
<div class="rex-content-header">
	<div class="rex-content-header-2">
		<?= $menu ?>
	</div>
</div>
<?

// Meldungen

if (!empty($global_warning)) print rex_warning($global_warning);
if (!empty($global_info))    print rex_info($global_info);

$article = $articleService->findById($article_id, $clang);
$dispatcher->notify('SLY_ART_MESSAGES', $article);

if ($mode != 'edit') {
	if (!empty($warning)) print rex_warning($warning);
	if (!empty($info))    print rex_info($info);
}

print '<div class="rex-content-body">';

if ($mode == 'edit') {
	// START: Slice move up/down

	if ($hasTemplate && ($function == 'moveup' || $function == 'movedown')) {
		if ($user->isAdmin() || $user->hasPerm('moveSlice[]')) {
			// Modul und Rechte vorhanden?

			$module = rex_slice_module_exists($slice_id, $clang);

			if (!$module) {
				// MODUL IST NICHT VORHANDEN
				$warning  = t('module_not_found');
				$slice_id = '';
				$function = '';
			}
			else {
				// RECHTE AM MODUL ?
				if ($user->isAdmin() || $user->hasPerm('module['.$module.']') || $user->hasPerm('module[0]')) {
					list($success, $message) = rex_moveSlice($slice_id, $clang, $function);

					if ($success) {
						$info = $message;
					}
					else {
						$warning = $message;
					}
				}
				else {
					$warning = t('no_rights_to_this_function');
				}
			}

			// Flush slice cache
			sly_Core::cache()->flush(OOArticleSlice::CACHE_NS);
		}
		else {
			$warning = t('no_rights_to_this_function');
		}
	}
	// END: Slice move up/down

	$params = array('id' => $article_id, 'clang' => $clang, 'article' => $article);

	$form   = new sly_Form('index.php', 'POST', t('general'), '', 'content_article_form');

	/////////////////////////////////////////////////////////////////
	// init form

	$form->setEncType('multipart/form-data');
	$form->addHiddenValue('page',       'content');
	$form->addHiddenValue('article_id', $article_id);
	$form->addHiddenValue('mode',       'edit');
	$form->addHiddenValue('save',       1);
	$form->addHiddenValue('clang',      $clang);
	$form->addHiddenValue('slot',       $slot);

	/////////////////////////////////////////////////////////////////
	// articletype
	$type = new sly_Form_Select_DropDown('article_type', t('content_arttype'), $article->getType(), $typeService->getArticleTypes(), 'article_type');
	$form->add($type);

	//additional form elements
	$form = sly_Core::dispatcher()->filter('SLY_ART_META_FORM', $form, $params);

	//buttons
	$button = new sly_Form_Input_Button('submit', 'save_article', t('article_save'));
	$form->setSubmitButton($button );

	$form->render();

	if (!$hasType || !$hasTemplate || $slot === null) {
		if (!$hasType) print rex_warning(t('content_select_type'));
		elseif (!$hasTemplate) print rex_warning(t('content_configure_article_type'));
		else print rex_info(t('content_no_slots'));
	}
	else {
		print '<div class="rex-content-editmode">';
		$prior = sly_request('prior', 'int', 0);

		$articleSlices = OOArticleSlice::getSliceIdsForSlot($article_id, $clang, $slot);

		foreach ($articleSlices as $articleSlice) {
			$ooslice = OOArticleSlice::getArticleSliceById($articleSlice);

			if ($function == 'add' && $prior == $ooslice->getPrior()) {
				$module = sly_request('module', 'string');
				sly_Helper_Content::printAddSliceForm($prior, $module, $article_id, $clang, $slot);
			}
			else {
				sly_Helper_Content::printAddModuleForm($article_id, $clang, $ooslice->getPrior(), $templateName, $slot);
			}

			if (empty($function) && $prior == $ooslice->getPrior()) {
				if (!empty($info))    print rex_info($info);
				if (!empty($warning)) print rex_warning ($warning);
			}

			if (($function == 'edit' || $function == 'moveup' || $function == 'movedown') && $slice_id == $ooslice->getId()) {
				if (!empty($info))    print rex_info($info);
				if (!empty($warning)) print rex_warning ($warning);
			}

			if ($function == 'edit' && $slice_id == $ooslice->getId()) {
				sly_Helper_Content::printSliceToolbar($ooslice);
				sly_Helper_Content::printEditSliceForm($ooslice);
			}
			else {
				sly_Helper_Content::printSliceToolbar($ooslice);
				sly_Helper_Content::printSliceContent($ooslice);
			}
		}

		if ($function == 'add' && $prior == count($articleSlices)) {
			$module = sly_request('module', 'string');
			if (!empty($info)) print rex_info($info);
			if (!empty($warning)) print rex_warning ($warning);
			sly_Helper_Content::printAddSliceForm($prior, $module, $article_id, $clang, $slot);
		}
		else {
			sly_Helper_Content::printAddModuleForm($article_id, $clang, count($articleSlices), $templateName, $slot);
		}

		print '</div>';

		// END: MODULE EDITIEREN/ADDEN ETC.
	}
}
elseif ($mode == 'meta') {
	// START: META VIEW

	$params = array('id' => $article_id, 'clang' => $clang, 'article' => $article);
	$form   = new sly_Form('index.php', 'POST', t('general'), '', 'REX_FORM');

	/////////////////////////////////////////////////////////////////
	// init form

	$form->setEncType('multipart/form-data');
	$form->addHiddenValue('page',       'content');
	$form->addHiddenValue('article_id', $article_id);
	$form->addHiddenValue('mode',       'meta');
	$form->addHiddenValue('save',       1);
	$form->addHiddenValue('clang',      $clang);
	$form->addHiddenValue('slot',       $slot);
	$form->setSubmitButton(null);
	$form->setResetButton(null);

	/////////////////////////////////////////////////////////////////
	// article name / metadata

	$name = new sly_Form_Input_Text('meta_article_name', t('name_description'), $article->getName(), 'rex-form-meta-article-name');
	$form->add($name);

	$form = sly_Core::dispatcher()->filter('SLY_ART_META_FORM', $form, $params);

	$button = new sly_Form_Input_Button('submit', 'savemeta', t('update_metadata'));
	$form->add(new sly_Form_ButtonBar(array('submit' => $button)));

	$form = sly_Core::dispatcher()->filter('SLY_ART_META_FORM_FIELDSET', $form, $params);

	/////////////////////////////////////////////////////////////////
	// misc

	function addButtonBar($form, $label, $name) {
		$button = new sly_Form_Input_Button('submit', $name, $label);
		$button->setAttribute('onclick', 'return confirm(\''.$label.'?\')');
		$form->add(new sly_Form_ButtonBar(array('submit' => $button)));
	}

	if ($user->isAdmin() || $user->hasPerm('article2startpage[]') || $user->hasPerm('moveArticle[]') || $user->hasPerm('copyArticle[]') || ($user->hasPerm('copyContent[]') && sly_Util_Language::isMultilingual())) {
		// ZUM STARTARTIKEL MACHEN

		if ($user->isAdmin() || $user->hasPerm('article2startpage[]')) {
			$form->beginFieldset(t('content_startarticle'));

			if ($article->getStartpage() == 0 && $article->getParentId() == 0) {
				$form->add(new sly_Form_Text('', t('content_nottostartarticle')));
			}
			else if ($article->getStartpage() == 1) {
				$form->add(new sly_Form_Text('', t('content_isstartarticle')));
			}
			else {
				addButtonBar($form, t('content_tostartarticle'), 'article2startpage');
			}
		}

		// INHALTE KOPIEREN

		if (($user->isAdmin() || $user->hasPerm('copyContent[]')) && sly_Util_Language::isMultilingual()) {
			$lang_a = new sly_Form_Select_DropDown('clang_a', t('content_contentoflang'), sly_request('clang_a', 'rex-clang-id', null), $languages, 'clang_a');
			$lang_a->setSize(1);

			$lang_b = new sly_Form_Select_DropDown('clang_b', t('content_to'), sly_request('clang_b', 'rex-clang-id', null), $languages, 'clang_b');
			$lang_b->setSize(1);

			$form->beginFieldset(t('content_submitcopycontent'), null, 2);
			$form->addRow(array($lang_a, $lang_b));

			addButtonBar($form, t('content_submitcopycontent'), 'copycontent');
		}

		// ARTIKEL VERSCHIEBEN

		if ($article->getStartpage() == 0 && ($user->isAdmin() || $user->hasPerm('moveArticle[]'))) {
			$select = sly_Form_Helper::getCategorySelect('category_id_new', false, null, null, $user);
			$select->setAttribute('value', $category_id);
			$select->setLabel(t('move_article'));

			$form->beginFieldset(t('content_submitmovearticle'));
			$form->add($select);

			addButtonBar($form, t('content_submitmovearticle'), 'movearticle');
		}

		// ARTIKEL KOPIEREN

		if ($user->isAdmin() || $user->hasPerm('copyArticle[]')) {
			$select = sly_Form_Helper::getCategorySelect('category_copy_id_new', false, null, null, $user);
			$select->setAttribute('value', $category_id);
			$select->setLabel(t('copy_article'));

			$form->beginFieldset(t('content_submitcopyarticle'));
			$form->add($select);

			addButtonBar($form, t('content_submitcopyarticle'), 'copyarticle');
		}

		// KATEGORIE/STARTARTIKEL VERSCHIEBEN

		if ($article->getStartpage() == 1 && ($user->isAdmin() || $user->hasPerm('moveCategory[]'))) {
			$select = sly_Form_Helper::getCategorySelect('category_id_new', false, false, null, $user);
			$select->setAttribute('value', $category_id);
			$select->setLabel(t('move_category'));

			$form->beginFieldset(t('content_submitmovecategory'));
			$form->add($select);

			addButtonBar($form, t('content_submitmovecategory'), 'movecategory');
		}
	}
	// SONSTIGES ENDE

	$form->render();

	// END: META VIEW
}

?>
	</div>
	<!-- *** OUTPUT OF ARTICLE-CONTENT - END *** -->
