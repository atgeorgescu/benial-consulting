<?php
/* Rulare din cron: php digest.php  (trimite rezumatul zilnic; dublurile sunt evitate prin mail_log) */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '';
require __DIR__ . '/lib.php'; require __DIR__ . '/schema.php'; require __DIR__ . '/entities.php'; require __DIR__ . '/views.php'; require __DIR__ . '/ext.php'; require __DIR__ . '/notify.php';
migrate(); migrate_ext(); migrate_notify();
$s = daily_digest(in_array('--dry', $argv, true));
echo date('Y-m-d H:i') . ' rezumat: ' . count($s) . " destinatari\n";
