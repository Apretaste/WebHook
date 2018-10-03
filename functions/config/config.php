<?php

date_default_timezone_set('America/New_York');

$_SERVER['breakFile'] = "/tmp/breakProcess.lock";
$_SERVER['instanceLock'] = "/tmp/instanceRunning.lock";

$_SERVER['IMAP_USER'] = "imap_username";
$_SERVER['IMAP_PASSW'] = "imap_password";
$_SERVER['IMAP_SERVER'] = "imap.server.com";
$_SERVER['IMAP_PORT'] = "993";
$_SERVER['IMAP_SSL'] = true;
$_SERVER['MAIL_BOX'] = "INBOX";
$_SERVER['DELETE_AFTER_READ'] = true;

$_SERVER['WEB_HOOK_URL'] = "https://url.webhoo.com/web/action";
$_SERVER['WEB_HOOK_METHOD'] = "POST";
$_SERVER['WEB_HOOK_EXTRA_PARAMS'] = [];


?>
