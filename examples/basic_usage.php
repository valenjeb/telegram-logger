<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Devly\TelegramLogger\Logger;

// Initialize the logger with your bot token and chat ID
$logger = new Logger(
    'YOUR_BOT_TOKEN',  // Get this from BotFather
    'YOUR_CHAT_ID'     // The chat/channel ID where messages will be sent
);

// Basic logging examples
$logger->info('Application started successfully');
$logger->warning('Low disk space warning');
$logger->error('Database connection failed');

// Using different parse modes
$logger->setParseMode(Logger::PARSE_MODE_HTML);
$logger->info('<b>Bold message</b> with <i>HTML formatting</i>');

$logger->setParseMode(Logger::PARSE_MODE_MARKDOWN);
$logger->info('*Bold message* with _Markdown formatting_');

// Sending to a different chat ID
$logger->info(
    'This message goes to a different chat',
    'ALTERNATIVE_CHAT_ID'
);

// Custom message with URL and file information
$logger->error(
    'Custom error message',
    null,           // Use default chat ID
    null,           // Use default parse mode
    '/custom/url',  // Custom URL
    'error.log:42'  // Custom file location
);
