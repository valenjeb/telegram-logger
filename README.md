# Telegram Logger for PHP

A PHP library for sending log messages to Telegram channels/chats.

## Installation

Install via Composer: 

```bash
composer require devly/telegram-logger
```

## Requirements

- PHP 7.4 or higher
- A Telegram Bot Token (get it from [@BotFather](https://t.me/botfather))
- A Chat ID where messages will be sent

## Quick Start

```php
use Devly\TelegramLogger\Logger;

$logger = new Logger('YOUR_BOT_TOKEN', 'YOUR_CHAT_ID');

// Send a simple log message
$logger->info('Application started successfully');
$logger->warning('Low disk space warning');
$logger->error('Database connection failed');
$logger->exception(new Exception('Something went wrong!', 500));
```

## Features

- Multiple log levels (info, warning, error)
- Support for different parse modes (HTML, Markdown, MarkdownV2)
- Automatic file and URL detection
- Custom chat ID support for each message
- Exception logging support

## Usage Examples

### Different Parse Modes

```php
// HTML formatting
$logger->setParseMode(Logger::PARSE_MODE_HTML);
$logger->info('<b>Bold message</b> with <i>HTML formatting</i>');
// Markdown formatting
$logger->setParseMode(Logger::PARSE_MODE_MARKDOWN);
$logger->info('**Bold message** with *Markdown formatting*');
// MarkdownV2 formatting
$logger->setParseMode(Logger::PARSE_MODE_MARKDOWNV2);
$logger->info('*Bold message* with *MarkdownV2 formatting*');
```

### Exception Logging

```php
try {
    // Some code that might throw an exception
    throw new Exception('Something went wrong!', 500);
} catch (Throwable $e) {
    // Log the exception with additional context
    $logger->exception($e, null, null, [
        'user_id' => 123,
        'action' => 'user_registration',
        'data' => ['email' => 'user@example.com']
    ]);
}
```

The exception logger will automatically include:
- Exception message
- Exception type/class
- Error code
- File and line where the error occurred
- Full stack trace
- Any additional context provided

### Custom Chat ID

```php
$logger->info('This message will be sent to the custom chat ID', 'YOUR_CHAT_ID');
```

### System Monitoring Example

```php
$logger->info(sprintf(
    "Server Status Report:\n" .
    "Memory Usage: %s\n" .
    "CPU Load: %s\n" .
    "Disk Space: %s",
    memory_get_usage(true),
    sys_getloadavg()[0],
    disk_free_space('/')
));
```

### Cron Job Monitoring

```php
function logCronJob(Logger $logger, string $jobName, callable $job): void
{
    $startTime = microtime(true);
    $logger->info(sprintf('Starting cron job: %s', $jobName));
    try {
        $job();
        $duration = round(microtime(true) - $startTime, 2);
        $logger->info(
            sprintf('Completed cron job: %s (Duration: %s seconds)', $jobName, $duration)
        );
    } catch (Throwable $e) {
        $logger->error(
            sprintf('Failed cron job: %s', $jobName) .
            "\nError: " . $e->getMessage()
        );
    }
}
```

## API Reference

### Constructor

```php
public function construct(
    string $botToken,
    string $chatId,
    string $parseMode = self::PARSE_MODE_HTML
)
```

### Methods

#### Log Levels

```php
public function info(string $message, ?string $chatId = null, ?string $parseMode = null): bool
public function warning(string $message, ?string $chatId = null, ?string $parseMode = null): bool
public function error(string $message, ?string $chatId = null, ?string $parseMode = null): bool
public function exception(Throwable $exception, ?string $chatId = null, ?string $parseMode = null, ?array $context = null): bool
```

#### Parse Mode

```php
public function setParseMode(string $parseMode): void
```

Available parse modes:
- `Logger::PARSE_MODE_HTML`
- `Logger::PARSE_MODE_MARKDOWN`
- `Logger::PARSE_MODE_MARKDOWNV2`

