<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

require_once __DIR__ . '/../../core/GmailHelpers.php';

/**
 * @covers ::base64url_encode
 * @covers ::getEmailDetails
 */
class GmailHelpersTest extends TestCase
{
    public function testBase64UrlEncodeProducesUrlSafeString(): void
    {
        $original = 'hello?+/=';
        $encoded = base64url_encode($original);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
        $this->assertSame(rtrim(strtr(base64_encode($original), '+/', '-_'), '='), $encoded);
    }

    public function testGetEmailDetailsWithNullMessageReturnsDefaults(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $details = getEmailDetails(null, $logger);
        $this->assertEquals('(No Subject)', $details['subject']);
        $this->assertEquals('(No Sender)', $details['from']);
        $this->assertEquals('(No Body)', $details['body']);
    }
} 