<?php
declare(strict_types=1);

/**
 * Scheduled CLI Worker: Safely cleans up unreferenced private files older than 24 hours.
 */

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Services\FileService;

App::bootCli();

$removed = FileService::cleanupOrphans(24);
echo sprintf("[%s] Orphan File Cleanup: removed=%d\n", date('Y-m-d H:i:s'), $removed);
