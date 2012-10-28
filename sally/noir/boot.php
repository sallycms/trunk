<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

// add the noir and legacy app
sly_Loader::addLoadPath(SLY_SALLYFOLDER.'/noir/lib/', 'sly_');
sly_Loader::addLoadPath(SLY_SALLYFOLDER.'/backend/lib/', 'sly_');

// init the app
$app = new sly_App_Noir();
sly_Core::setCurrentApp($app);
$app->initialize();

// ... and run it if not debugging
$app->run();
