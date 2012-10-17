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
 * @author zozi@webvariants.de
 */
class sly_Helper_Content {
	public static function printAddSliceForm($module, $position, $articleId, $clang, $slot) {
		$moduleService = sly_Service_Factory::getModuleService();

		if (!$moduleService->exists($module)) {
			$slice_content = sly_Helper_Message::warn(ht('module_not_found', $module));
		}
		else {
			try {
				ob_start();
				$moduleTitle = $moduleService->getTitle($module);
				$form = new sly_Form('index.php', 'post', t('add_slice').': '.sly_translate($moduleTitle, true), '', 'addslice');
				$form->setEncType('multipart/form-data');
				$form->addHiddenValue('page', 'content');
				$form->addHiddenValue('func', 'addArticleSlice');
				$form->addHiddenValue('article_id', $articleId);
				$form->addHiddenValue('clang', $clang);
				$form->addHiddenValue('slot', $slot);
				$form->addHiddenValue('module', sly_html($module));
				$form->addHiddenValue('pos', $position);
				$form->setSubmitButton(new sly_Form_Input_Button('submit', 'btn_save', t('add_slice')));

				$renderer   = new sly_Slice_Renderer($module);
				$sliceinput = new sly_Form_Fragment();
				$sliceinput->setContent('<div class="sly-contentpage-slice-input">'.$renderer->renderInput('slicevalue').'</div>');

				$form->add($sliceinput);
				$form->addClass('sly-slice-form');

				print $form->render();

				self::focusFirstElement();

				sly_Core::dispatcher()->notify('SLY_SLICE_POSTVIEW_ADD', array(), array(
					'module'     => $module,
					'article_id' => $articleId,
					'clang'      => $clang,
					'slot'       => $slot
				));

				$slice_content = ob_get_clean();
			}
			catch (Exception $e) {
				ob_end_clean();
				throw $e;
			}
		}

		print $slice_content;
	}

	public static function printEditSliceForm(sly_Model_ArticleSlice $articleSlice, $values = array()) {
		$moduleService = sly_Service_Factory::getModuleService();
		$module        = $articleSlice->getModule();
		$moduleTitle   = $moduleService->getTitle($module);

		try {
			ob_start();
			$form = new sly_Form('index.php', 'post', t('edit_slice').': '.sly_translate($moduleTitle, true), '', 'editslice');
			$form->setEncType('multipart/form-data');
			$form->addHiddenValue('page', 'content');
			$form->addHiddenValue('func', 'editArticleSlice');
			$form->addHiddenValue('article_id', $articleSlice->getArticleId());
			$form->addHiddenValue('clang', $articleSlice->getClang());
			$form->addHiddenValue('slice_id', $articleSlice->getId());
			$form->addHiddenValue('slot', $articleSlice->getSlot());
			$form->setSubmitButton(new sly_Form_Input_Button('submit', 'btn_save', t('save')));
			$form->setApplyButton(new sly_Form_Input_Button('submit', 'btn_update', t('apply')));
			$form->setResetButton(new sly_Form_Input_Button('reset', 'reset', t('reset')));

			$renderer   = new sly_Slice_Renderer($module, $values);
			$sliceinput = new sly_Form_Fragment();
			$sliceinput->setContent('<div class="sly-contentpage-slice-input">'.$renderer->renderInput('slicevalue').'</div>');

			$form->add($sliceinput);
			$form->addClass('sly-slice-form');

			print $form->render();

			self::focusFirstElement();

			sly_Core::dispatcher()->notify('SLY_SLICE_POSTVIEW_EDIT', $values, array(
				'module'     => $articleSlice->getModule(),
				'article_id' => $articleSlice->getArticleId(),
				'clang'      => $articleSlice->getClang(),
				'slot'       => $articleSlice->getSlot(),
				'slice'      => $articleSlice
			));

			$slice_content = ob_get_clean();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}

		print $slice_content;
	}

	private static function focusFirstElement() {
		$layout = sly_Core::getLayout();
		$layout->addJavaScript('jQuery(function($) { $(".sly-slice-form").find(":input:visible:enabled:not([readonly]):first").focus(); });');
	}

	public static function metaFormAddButtonBar($form, $label, $name) {
		$button = new sly_Form_Input_Button('submit', $name, $label);
		$button->setAttribute('onclick', 'return confirm('.json_encode($label.'?').')');
		$form->add(new sly_Form_ButtonBar(array('submit' => $button)));
	}
}
