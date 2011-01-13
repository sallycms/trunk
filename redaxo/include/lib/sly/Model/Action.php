<?php/* * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de * * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der * beiliegenden LICENSE Datei und unter: * * http://www.opensource.org/licenses/mit-license.php * http://de.wikipedia.org/wiki/MIT-Lizenz *//** * Business Model Klasse für Actions *  * @author christoph@webvariants.de */class sly_Model_Action extends sly_Model_Base{	protected $name;	protected $preview;	protected $presave;	protected $postsave;	protected $previewmode;	protected $presavemode;	protected $postsavemode;	protected $createuser;	protected $updateuser;	protected $createdate;	protected $updatedate;	protected $revision;	protected $_attributes = array(		'name' => 'string',		'preview' => 'string', 'presave' => 'string', 'postsave' => 'string',		'previewmode' => 'int', 'presavemode' => 'int', 'postsavemode' => 'int',		'createuser' => 'string', 'createdate' => 'int',		'updateuser' => 'string', 'updatedate' => 'int',		'revision' => 'int'	);		public function getName()         { return $this->name;         }	public function getPreview()      { return $this->preview;      }	public function getPresave()      { return $this->presave;      }	public function getPostsave()     { return $this->postsave;     }	public function getPreviewMode()  { return $this->previewmode;  }	public function getPresaveMode()  { return $this->presavemode;  }	public function getPostsaveMode() { return $this->postsavemode; }	public function getRevision()     { return $this->revision;     }		public function setName($name)                 { $this->name         = $name;         }	public function setPreview($preview)           { $this->preview      = $preview;      }	public function setPresave($presave)           { $this->presave      = $presave;      }	public function setPostsave($postsave)         { $this->postsave     = $postsave;     }	public function setPreviewMode($previewMode)   { $this->previewmode  = $previewMode;  }	public function setPresaveMode($presaveMode)   { $this->presavemode  = $presaveMode;  }	public function setPostsaveMode($postsaveMode) { $this->postsavemode = $postsaveMode; }	public function setRevision($revision)         { $this->revision     = $revision;     }		/* Helper */		public function getMode($mode, $bitStrings = null)	{		$mode  = strtolower($mode);		$modes = array('preview', 'presave', 'postsave');				if (!in_array($mode, $modes)) {			throw new Exception('Unbekannter Action-Mode: '.$mode);		}				$mode     = $mode.'mode';		$var      = $this->$mode;		$result   = array();		$fallback = array(1 => 'ADD', 2 => 'EDIT', 4 => 'DELETE');				foreach ($fallback as $mask => $value) {			if ($var & $mask) {				$result[] = is_array($bitStrings) && isset($bitStrings[$mask]) ? $bitStrings[$mask] : $value;			}		}				return $result;	}		public function getInput($mode)	{		$mode  = strtolower($mode);		$modes = array('preview', 'presave', 'postsave');				if (!in_array($mode, $modes)) {			throw new Exception('Unbekannter Action-Mode: '.$mode);		}				return $this->$mode;	}		public function delete()	{		return sly_Service_Factory::getService('Action')->delete(array('id' => $this->id));	}}