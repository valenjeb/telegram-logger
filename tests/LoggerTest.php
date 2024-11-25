<?php

declare(strict_types=1);

namespace Devly\TelegramLogger\Tests;

use Devly\TelegramLogger\Logger;
use Exception;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

use function strpos;

class LoggerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new class ('fake-token', '123456') extends Logger {
            /** @var array<string> */
            public array $sentMessages = [];

            protected function sendToTelegram(
                string $message,
                string|null $chatId = null,
                string|null $parseMode = null
            ): bool {
                $this->sentMessages[] = $message;

                return true;
            }
        };
    }

    public function testLoggerInitialization(): void
    {
        $this->assertInstanceOf(Logger::class, $this->logger);
    }

    public function testSetParseMode(): void
    {
        $this->logger->setParseMode(Logger::PARSE_MODE_HTML);
        $this->expectNotToPerformAssertions();
    }

    public function testInvalidParseMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->logger->setParseMode('INVALID_MODE');
    }

    public function testMessageFormatting(): void
    {
        $this->logger->info('Test message');

        $this->assertCount(1, $this->logger->sentMessages);
        $this->assertStringContainsString('Test message', $this->logger->sentMessages[0]);
    }

    public function testAutoDetectFileAndUrl(): void
    {
        $_SERVER['REQUEST_URI'] = '/test-url';

        // Create a properly initialized mock
        $logger = Mockery::mock(Logger::class, ['fake-token', '123456'])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $logger->shouldReceive('sendToTelegram')
            ->once()
            ->withArgs(static function ($message) {
                return strpos($message, 'URL: /test-url') !== false
                    && strpos($message, 'File:') !== false;
            })
            ->andReturn(true);

        $result = $logger->error('Test error');
        $this->assertTrue($result);
    }

    public function testDifferentLogLevels(): void
    {
        // Create a properly initialized mock
        $logger = Mockery::mock(Logger::class, ['fake-token', '123456'])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $logger->shouldReceive('sendToTelegram')
            ->times(3)
            ->andReturn(true);

        $this->assertTrue($logger->info('Info message'));
        $this->assertTrue($logger->warning('Warning message'));
        $this->assertTrue($logger->error('Error message'));
    }

    public function testExceptionLogging(): void
    {
        $exception = new Exception('Test exception', 500);
        $this->logger->exception($exception);

        $this->assertCount(1, $this->logger->sentMessages);
        $message = $this->logger->sentMessages[0];
        
        // Verify all exception details are included
        $this->assertStringContainsString('Exception: Test exception', $message);
        $this->assertStringContainsString('Type: Exception', $message);
        $this->assertStringContainsString('Code: 500', $message);
        $this->assertStringContainsString('File:', $message);
        $this->assertStringContainsString('Stack Trace:', $message);
    }

    public function testExceptionLoggingWithContext(): void
    {
        $exception = new Exception('Test exception');
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
            'data' => ['key' => 'value']
        ];

        $this->logger->exception($exception, null, null, $context);

        $this->assertCount(1, $this->logger->sentMessages);
        $message = $this->logger->sentMessages[0];
        
        // Verify context is included
        $this->assertStringContainsString('Context:', $message);
        $this->assertStringContainsString('user_id: 123', $message);
        $this->assertStringContainsString('action: test_action', $message);
        $this->assertStringContainsString('data: {"key":"value"}', $message);
    }

    public function testExceptionLoggingWithCustomChatId(): void
    {
        $exception = new Exception('Test exception');
        $customChatId = '987654321';
        
        // Create a mock to verify the chat ID
        $logger = Mockery::mock(Logger::class, ['fake-token', '123456'])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $logger->shouldReceive('sendToTelegram')
            ->once()
            ->withArgs(function ($message, $chatId) use ($customChatId) {
                return $chatId === $customChatId;
            })
            ->andReturn(true);

        $result = $logger->exception($exception, $customChatId);
        $this->assertTrue($result, 'Exception logging with custom chat ID should return true');
    }

    public function testExceptionLoggingWithCustomParseMode(): void
    {
        $exception = new Exception('Test exception');
        
        // Create a mock to verify the parse mode
        $logger = Mockery::mock(Logger::class, ['fake-token', '123456'])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $logger->shouldReceive('sendToTelegram')
            ->once()
            ->withArgs(function ($message, $chatId, $parseMode) {
                return $parseMode === Logger::PARSE_MODE_MARKDOWN;
            })
            ->andReturn(true);

        $result = $logger->exception($exception, null, Logger::PARSE_MODE_MARKDOWN);
        $this->assertTrue($result, 'Exception logging with custom parse mode should return true');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
