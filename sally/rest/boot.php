<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// add the rest app
sly_Loader::addLoadPath(SLY_SALLYFOLDER.'/rest/lib/', 'sly_Rest_');

// init the app
$app = new sly_Rest_App();
sly_Core::setCurrentApp($app);
$app->initialize();

// ... and run it if not debugging
$app->run();
