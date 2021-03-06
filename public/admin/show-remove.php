<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use nntmux\Releases;

$page     = new AdminPage();
$releases = new Releases(['Settings' => $page->settings]);

$success = false;

if (isset($_GET["id"])) {
	$success = $releases->removeVideoIdFromReleases($_GET["id"]);
	$page->smarty->assign('videoid', $_GET["id"]);
}

$page->smarty->assign('success', $success);

$page->title   = "Remove Video and Episode IDs from Releases";
$page->content = $page->smarty->fetch('show-remove.tpl');
$page->render();
