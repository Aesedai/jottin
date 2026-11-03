<?php

namespace Tests\Feature;

use App\Libraries\Delivery\CurlTransport;
use App\Libraries\Delivery\TransportResult;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Exercises real HTTP against the demo receiver, which fakes can't prove.
 */
final class CurlTransportTest extends CIUnitTestCase
{
    /** @var resource|null */
    private static $server;

    private static int $port;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$port   = random_int(20000, 45000);
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, ROOTPATH . 'docker/receiver/index.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        parent::tearDownAfterClass();
    }

    private function send(string $path): TransportResult
    {
        return (new CurlTransport())->post(
            'http://receiver.test:' . self::$port . $path,
            ['host' => 'receiver.test', 'port' => self::$port, 'address' => '127.0.0.1'],
            '{"hello":"world"}',
            ['Content-Type' => 'application/json', 'X-Jottin-Signature' => 'sha256=test']
        );
    }

    public function testSuccessfulPostReachesThePinnedAddress(): void
    {
        $result = $this->send('/ok');

        $this->assertTrue($result->succeeded());
        $this->assertSame(200, $result->httpStatus);

        $last = json_decode((string) file_get_contents('http://127.0.0.1:' . self::$port . '/last'), true);
        $this->assertSame('{"hello":"world"}', $last['body']);
        $this->assertSame('sha256=test', $last['signature']);
    }

    public function testServerErrorIsAFailureWithItsStatus(): void
    {
        $result = $this->send('/fail');

        $this->assertFalse($result->succeeded());
        $this->assertSame(500, $result->httpStatus);
        $this->assertSame('The endpoint returned HTTP 500.', $result->error);
    }

    public function testRedirectIsNotFollowed(): void
    {
        $result = $this->send('/redirect');
        $last   = json_decode((string) file_get_contents('http://127.0.0.1:' . self::$port . '/last'), true);

        $this->assertFalse($result->succeeded());
        $this->assertSame(302, $result->httpStatus);
        $this->assertSame('/redirect', $last['path'], 'The redirect target must not be requested.');
    }

    public function testSlowEndpointTimesOut(): void
    {
        $started = microtime(true);
        $result  = $this->send('/slow');

        $this->assertFalse($result->succeeded());
        $this->assertSame('The endpoint did not respond in time.', $result->error);
        $this->assertLessThan(8, microtime(true) - $started);
    }

    public function testUnreachableEndpointIsASafeFailure(): void
    {
        $result = (new CurlTransport())->post(
            'http://secret-host.test:1/path?token=do-not-leak',
            ['host' => 'secret-host.test', 'port' => 1, 'address' => '127.0.0.1'],
            '{}',
            []
        );

        $this->assertFalse($result->succeeded());
        $this->assertNull($result->httpStatus);
        $this->assertStringNotContainsString('do-not-leak', (string) $result->error);
        $this->assertStringNotContainsString('secret-host', (string) $result->error);
    }
}
