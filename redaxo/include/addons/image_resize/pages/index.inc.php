<?php

require $REX['INCLUDE_PATH'].'/layout/top.php';

$subpage = sly_request('subpage', 'string');

if ($subpage == 'clear_cache') {
	$c   = Thumbnail::deleteCache();
	$msg = $I18N->msg('iresize_cache_files_removed', $c);
	if (!empty($msg)) print rex_info($msg);
}

rex_title('Image Resize', $REX['ADDON']['image_resize']['SUBPAGES']);

if ($subpage != 'settings') $subpage = 'overview';
require $REX['INCLUDE_PATH'].'/addons/image_resize/pages/'.$subpage.'.inc.php';
require $REX['INCLUDE_PATH'].'/layout/bottom.php';
