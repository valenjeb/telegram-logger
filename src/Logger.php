<?php

declare(strict_types=1);

namespace Devly\TelegramLogger;

use InvalidArgumentException;
use Throwable;

use function array_map;
use function date;
use function debug_backtrace;
use function file_get_contents;
use function http_build_query;
use function implode;
use function in_array;
use function json_encode;
use function sprintf;
use function str_replace;
use function stream_context_create;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

class Logger
{
    private string $apiUrl             = 'https://api.telegram.org/bot';
    public const PARSE_MODE_HTML       = 'HTML';
    public const PARSE_MODE_MARKDOWN   = 'Markdown';
    public const PARSE_MODE_MARKDOWNV2 = 'MarkdownV2';

    public function __construct(
        private string $botToken,
        private string $chatId,
        private string $parseMode = self::PARSE_MODE_HTML
    ) {
    }

    public function setParseMode(string $parseMode): void
    {
        if (! in_array($parseMode, [self::PARSE_MODE_HTML, self::PARSE_MODE_MARKDOWN, self::PARSE_MODE_MARKDOWNV2])) {
            throw new InvalidArgumentException('Invalid parse mode. Use HTML, Markdown, or MarkdownV2');
        }

        $this->parseMode = $parseMode;
    }

    /**
     * Get the caller information.
     *
     * @return array<string, string|null>
     */
    private function getCallerInfo(): array
    {
        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? $trace[1] ?? $trace[0] ?? null;

        return [
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
        ];
    }

    public function log(
        string $message,
        string $level = 'INFO',
        string|null $chatId = null,
        string|null $parseMode = null,
        string|null $url = null,
        string|null $file = null
    ): bool {
        if ($url === null || $file === null) {
            $callerInfo = $this->getCallerInfo();
            $url      ??= $callerInfo['url'];
            $file     ??= ($callerInfo['file'] . ':' . $callerInfo['line']);
        }

        $formattedMessage = $this->formatMessage($message, $level, $parseMode ?? $this->parseMode, $url, $file);

        return $this->sendToTelegram($formattedMessage, $chatId, $parseMode);
    }

    public function error(
        string $message,
        string|null $chatId = null,
        string|null $parseMode = null,
        string|null $url = null,
        string|null $file = null
    ): bool {
        return $this->log($message, 'ERROR', $chatId, $parseMode, $url, $file);
    }

    public function warning(
        string $message,
        string|null $chatId = null,
        string|null $parseMode = null,
        string|null $url = null,
        string|null $file = null
    ): bool {
        return $this->log($message, 'WARNING', $chatId, $parseMode, $url, $file);
    }

    public function info(
        string $message,
        string|null $chatId = null,
        string|null $parseMode = null,
        string|null $url = null,
        string|null $file = null
    ): bool {
        return $this->log($message, 'INFO', $chatId, $parseMode, $url, $file);
    }

    /**
     * Log an exception with full details including message, code, file, line and stack trace.
     *
     * @param Throwable     $exception The exception to log
     * @param string|null   $chatId    Optional custom chat ID
     * @param string|null   $parseMode Optional parse mode
     * @param string[]|null $context   Optional additional context
     */
    public function exception(
        Throwable $exception,
        string|null $chatId = null,
        string|null $parseMode = null,
        array|null $context = null
    ): bool {
        $message = sprintf(
            "Exception: %s\nType: %s\nCode: %d\nFile: %s:%d\n\nStack Trace:\n%s",
            $exception->getMessage(),
            get_class($exception),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        if ($context !== null) {
            $message .= "\n\nContext:\n" . $this->formatContext($context);
        }

        return $this->error(
            $message,
            $chatId,
            $parseMode,
            null,
            sprintf('%s:%d', $exception->getFile(), $exception->getLine())
        );
    }

    /**
     * Format context array for logging.
     *
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        $output = [];
        foreach ($context as $key => $value) {
            $output[] = sprintf(
                '%s: %s',
                $key,
                is_scalar($value) ? (string) $value : json_encode($value)
            );
        }

        return implode("\n", $output);
    }

    private function formatMessage(
        string $message,
        string $level,
        string $parseMode,
        string|null $url = null,
        string|null $file = null
    ): string {
        $timestamp = date('Y-m-d H:i:s');
        $details   = [];

        if ($url) {
            $details[] = sprintf('URL: %s', $url);
        }

        if ($file) {
            $details[] = sprintf('File: %s', $file);
        }

        $detailsText = $details ? "\n" . implode("\n", $details) : '';

        switch ($parseMode) {
            case self::PARSE_MODE_HTML:
                return sprintf(
                    '<b>[%s] %s</b>: %s%s',
                    $timestamp,
                    $level,
                    $message,
                    $detailsText
                );

            case self::PARSE_MODE_MARKDOWNV2:
                return sprintf(
                    '*\[%s\] %s*: %s%s',
                    $timestamp,
                    $level,
                    $this->escapeMarkdownV2($message),
                    $this->escapeMarkdownV2($detailsText)
                );

            case self::PARSE_MODE_MARKDOWN:
                return sprintf(
                    '*[%s] %s*: %s%s',
                    $timestamp,
                    $level,
                    $message,
                    $detailsText
                );

            default:
                return sprintf('[%s] %s: %s%s', $timestamp, $level, $message, $detailsText);
        }
    }

    private function escapeMarkdownV2(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        return str_replace($chars, array_map(static fn ($char) => "\\$char", $chars), $text); // phpcs:ignore
    }

    protected function sendToTelegram(string $message, string|null $chatId = null, string|null $parseMode = null): bool
    {
        $url = $this->apiUrl . $this->botToken . '/sendMessage';

        $data = [
            'chat_id' => $chatId ?? $this->chatId,
            'text' => $message,
            'parse_mode' => $parseMode ?? $this->parseMode,
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $result  = file_get_contents($url, false, $context);

        return $result !== false;
    }
}
