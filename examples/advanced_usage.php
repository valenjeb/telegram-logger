<?php // phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols


declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Devly\TelegramLogger\Logger;

// Initialize with MarkdownV2 parse mode
$logger = new Logger(
    'YOUR_BOT_TOKEN',
    'YOUR_CHAT_ID',
    Logger::PARSE_MODE_MARKDOWNV2
);

// Example: Logging exceptions
try {
    throw new Exception('Something went wrong!');
} catch (Throwable $e) {
    $logger->error(
        sprintf(
            "Exception caught:\nMessage: %s\nStack trace:\n%s",
            $e->getMessage(),
            $e->getTraceAsString()
        )
    );
}

// Example: Logging with different levels and formatting
$logger->info(
    sprintf(
        "Server Status Report:\n" .
        "Memory Usage: %s\n" .
        "CPU Load: %s\n" .
        'Disk Space: %s',
        memory_get_usage(true),
        sys_getloadavg()[0],
        disk_free_space('/')
    )
);

// Example: Custom formatting with MarkdownV2
$logger->info(
    sprintf(
        "🚀 *Deployment Status*\n" .
        "Version: `2\\.0\\.1`\n" .
        "Environment: `production`\n" .
        'Time: `%s`',
        date('Y-m-d H:i:s')
    )
);

// Example: Logging API responses
$apiResponse = [
    'status' => 'error',
    'code' => 404,
    'message' => 'Resource not found',
];

$logger->error(
    sprintf(
        "API Error:\n%s",
        json_encode($apiResponse, JSON_PRETTY_PRINT)
    )
);

/**
 * Example: Monitoring cron jobs
 */
function logCronJob(Logger $logger, string $jobName, callable $job): void // phpcs:ignore
{
    $startTime = microtime(true);
    $logger->info(sprintf('Starting cron job: %s', $jobName));

    try {
        $job();
        $duration = round(microtime(true) - $startTime, 2);
        $logger->info(sprintf('Completed cron job: %s (Duration: %s seconds)', $jobName, $duration));
    } catch (Throwable $e) {
        $logger->error(sprintf('Failed cron job: %s', $jobName) . "\nError: " . $e->getMessage());
    }
}

// Usage example
logCronJob($logger, 'Daily Backup', static function (): void {
    // Simulate backup process
    sleep(2);
});
