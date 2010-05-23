<?php

define('IS_SALLY', true);
ob_start();
ob_implicit_flush(0);

// Globale Variablen vorbereiten

require_once 'redaxo/include/functions/function_rex_mquotes.inc.php';

// $REX vorbereiten

unset($REX);

$REX['REDAXO']      = false; // Backend = true, Frontend = false
$REX['SALLY']       = false; // Backend = true, Frontend = false
$REX['HTDOCS_PATH'] = './';

// Core laden

require_once 'redaxo/include/master.inc.php';
require_once 'redaxo/include/addons.inc.php';

// Setup?

if ($config->get('SETUP')) {
	header('Location: redaxo/index.php');
	exit();
}

// Aktuellen Artikel finden und ausgeben

$REX['ARTICLE'] = new rex_article();
$REX['ARTICLE']->setCLang(sly_Core::getCurrentClang());

if ($REX['ARTICLE']->setArticleId(sly_Core::getCurrentArticleId())) {
	print $REX['ARTICLE']->getArticleTemplate();
}
else {
	print 'Kein Startartikel selektiert. Bitte setze ihn im <a href="redaxo/index.php">Backend</a>.';
	$REX['STATS'] = 0;
}

$content = ob_get_clean();
rex_send_article($REX['ARTICLE'], $content, 'frontend');
