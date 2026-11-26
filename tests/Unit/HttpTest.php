<?php

/*
 * This file is part of PapiAI,
 * A simple but powerful PHP library for building AI agents.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Mcp\Client\Client;
use Mcp\Client\Transport\HttpTransport;
use PapiAI\Mcp\McpServer;

/**
 * Serves the fixture with PHP's built-in web server on a free local port, until the test ends.
 *
 * @return array{0: resource, 1: string} The server process and its base URL
 */
function servedOverHttp(): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $address = (string) stream_socket_get_name($probe, false);
    fclose($probe);
    $log = (string) tempnam(sys_get_temp_dir(), 'papi-mcp-http');

    $process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/../Fixtures/server.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', $log, 'w']], $pipes);

    for ($attempt = 0; $attempt < 50 && !str_contains((string) file_get_contents($log), 'started'); ++$attempt) {
        usleep(100_000);
    }

    unlink($log);

    return [$process, 'http://' . $address . '/mcp'];
}

describe('McpServer over Streamable HTTP', function () {
    beforeEach(function () {
        [$this->process, $this->url] = servedOverHttp();
    });

    afterEach(function () {
        proc_terminate($this->process);
        proc_close($this->process);
    });

    it('lists and runs the tools of a remote server, in either era', function (string $query, string $era) {
        $client = Client::builder()->setClientInfo('test', '1.0.0')->build();
        $server = new McpServer(new HttpTransport($this->url . $query), client: $client);

        $tools = $server->tools();
        $add = array_values(array_filter($tools, static fn ($tool): bool => $tool->getName() === 'add'))[0];

        expect(count($tools))->toBe(6);
        expect($add->execute(['a' => 20, 'b' => 22]))->toBe(['sum' => 42]);
        expect($client->getProtocolVersion()?->value)->toBe($era);
    })->with([
        'modern (2026-07-28)' => ['', '2026-07-28'],
        'handshake (2025-11-25)' => ['?handshake-only', '2025-11-25'],
    ]);
});
