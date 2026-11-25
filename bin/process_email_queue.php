<?php
declare(strict_types=1);

/**
 * Scheduled CLI Worker: Processes pending and due retryable email queue jobs.
 * Recommended Cron (every 1-5 minutes):
 *   * * * * * php /path/to/orixo/bin/process_email_queue.php >> /path/to/orixo/storage/logs/cron_mail.log 2>&1
 */

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Services\EmailQueueService;

App::bootCli();

$summary = EmailQueueService::processDueJobs(30);
echo sprintf(
    "[%s] Email Queue Run: processed=%d, sent=%d, failed=%d\n",
    date('Y-m-d H:i:s'),
    $summary['processed'],
    $summary['sent'],
    $summary['failed']
);
