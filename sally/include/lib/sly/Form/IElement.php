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
 * @ingroup form
 */
interface sly_Form_IElement {
	public function getID();
	public function getName();
	public function getLabel();
	public function getValue();
	public function render();
	public function addClass($className);
	public function getAttribute($name);
	public function setAttribute($name, $value);
	public function removeAttribute($name);
	public function addOuterClass($className);
	public function getOuterClass();
	public function isContainer();
	public function getDisplayValue();
	public function isMultilingual();
}
