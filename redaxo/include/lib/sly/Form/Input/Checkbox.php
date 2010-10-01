<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

class sly_Form_Input_Checkbox extends sly_Form_Input_Base
{
	protected $description;

	public function __construct($name, $label, $value, $description = 'ja', $id = null)
	{
		$allowed = array('value', 'name', 'id', 'disabled', 'class', 'style', 'type', 'checked');
		parent::__construct($name, $label, $value, $id, $allowed);
		$this->description = $description;
		$this->setAttribute('type', 'checkbox');
	}

	public function render()
	{
		$this->addStyle('vertical-align:middle');
		
		$lineHeight      = 21;
		$attributeString = $this->getAttributeString();
		
		return 
			'<input '.$attributeString.' /> '.
			'<label class="inline" style="line-height:'.$lineHeight.'px" for="'.$this->attributes['id'].'">'.sly_html($this->description).'</label>';
	}

	public function setChecked($checked)
	{
		if ($checked) $this->setAttribute('checked', 'checked');
		else $this->removeAttribute('checked');
	}
}