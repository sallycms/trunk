<?php 
/*
 * Copyright (c) 20010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
*/
abstract class sly_Controller_Ajax extends sly_Controller_Base{
	
	protected function init()
	{
		while(ob_get_level()) ob_end_clean();
	}

	protected function teardown()
	{
		exit;
	}
}