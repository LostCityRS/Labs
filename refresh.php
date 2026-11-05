<?php
// Cron, every 5 minutes.
require __DIR__ . '/lib.php';
transaction($db, 'refreshing gains', fn() => refresh_gains($db, $now));
