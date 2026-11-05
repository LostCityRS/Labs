<?php
// Cron, every 5 minutes.
require __DIR__ . '/lib.php';
$db->beginTransaction();
refresh_gains($db, $now);
$db->commit();
