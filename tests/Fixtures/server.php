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

/*
 * An MCP server for the tests, built with the SDK's own server. From the command line it serves
 * stdio; under `php -S` it serves Streamable HTTP. It answers both protocol eras unless started with
 * handshake-only, and lists its tools two to a page so that paging is exercised. The collide and
 * crash flags add a tool whose name collides once exposed, and one that stops the server.
 */

use Http\Discovery\Psr17Factory;
use Mcp\Schema\Content\ImageContent;
use Mcp\Server\Exception\ToolCallException;
use Mcp\Server\Server;
use Mcp\Server\Transport\StdioTransport;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$flags = PHP_SAPI === 'cli' ? array_slice($argv, 1) : array_keys($_GET);

$builder = Server::builder()
    ->setServerInfo('papi-fixture', '1.0.0')
    ->setPaginationLimit(2)
    ->addTool(static fn (string $text): string => $text, 'echo', description: 'Repeats the text it is given')
    ->addTool(
        static fn (int $a, int $b): array => ['sum' => $a + $b],
        'add',
        description: 'Adds two whole numbers',
        outputSchema: ['type' => 'object', 'properties' => ['sum' => ['type' => 'integer']], 'required' => ['sum']],
    )
    ->addTool(static function (): never {
        throw new ToolCallException('The fixture always fails.');
    }, 'fail', description: 'Always reports a failure')
    ->addTool(static fn (): string => 'alice, bob', 'admin.users.list', description: 'Lists the users')
    ->addTool(static fn (): ImageContent => new ImageContent(base64_encode('not really a png'), 'image/png'), 'picture', title: 'A picture');

if (in_array('collide', $flags, true)) {
    $builder->addTool(static fn (): string => 'carol', 'admin_users_list', description: 'Lists the users again');
}

if (in_array('crash', $flags, true)) {
    $builder->addTool(static function (): never {
        exit(1);
    }, 'crash', description: 'Stops the server');
}

if (in_array('handshake-only', $flags, true)) {
    $builder->withoutModernEra();
}

$server = $builder->build();

if (PHP_SAPI === 'cli') {
    exit($server->run(new StdioTransport()));
}

$response = $server->run(new StreamableHttpTransport((new Psr17Factory())->createServerRequestFromGlobals()));

if ($response instanceof ResponseInterface) {
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        header($name . ': ' . implode(', ', $values));
    }

    echo $response->getBody();
}
