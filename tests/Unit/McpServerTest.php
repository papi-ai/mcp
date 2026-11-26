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
use Mcp\Client\Transport\StdioTransport;
use PapiAI\Core\Contracts\ToolInterface;
use PapiAI\Mcp\McpException;
use PapiAI\Mcp\McpServer;
use PapiAI\Mcp\ToolFailedException;

dataset('eras', [
    'modern (2026-07-28)' => [[], '2026-07-28'],
    'handshake (2025-11-25)' => [['handshake-only'], '2025-11-25'],
]);

/**
 * @param list<ToolInterface> $tools
 *
 * @return array<string, ToolInterface>
 */
function byName(array $tools): array
{
    $named = [];

    foreach ($tools as $tool) {
        $named[$tool->getName()] = $tool;
    }

    return $named;
}

describe('McpServer', function () {
    it('hands over every tool the server lists, across pages, in either era', function (array $flags, string $era) {
        $client = Client::builder()->setClientInfo('test', '1.0.0')->build();
        $server = new McpServer(fixtureServer($flags), client: $client);

        $tools = byName($server->tools());

        expect(array_keys($tools))->toBe(['echo', 'add', 'fail', 'admin_users_list', 'ping', 'picture']);
        expect($client->getProtocolVersion()?->value)->toBe($era);
        $server->disconnect();
    })->with('eras');

    it('describes each tool as the server does', function () {
        $tools = byName((new McpServer(fixtureServer()))->tools());

        expect($tools['echo']->getDescription())->toBe('Repeats the text it is given');
        expect($tools['echo']->getParameterSchema())->toBe([
            'type' => 'object',
            'properties' => ['text' => ['type' => 'string']],
            'required' => ['text'],
        ]);
        expect($tools['picture']->getDescription())->toBe('A picture');
    });

    it('runs a tool on the server and gives back its result, in either era', function (array $flags) {
        $tools = byName((new McpServer(fixtureServer($flags)))->tools());

        expect($tools['echo']->execute(['text' => 'hello']))->toBe('hello');
        expect($tools['add']->execute(['a' => 2, 'b' => 3]))->toBe(['sum' => 5]);
        expect($tools['admin_users_list']->execute([]))->toBe('alice, bob');
    })->with('eras');

    it('throws what the tool said when it reports a failure', function () {
        byName((new McpServer(fixtureServer()))->tools())['fail']->execute([]);
    })->throws(ToolFailedException::class, 'The fixture always fails.');

    it('prefixes every tool name', function () {
        expect(array_keys(byName((new McpServer(fixtureServer(), prefix: 'fx'))->tools())))
            ->toBe(['fx_echo', 'fx_add', 'fx_fail', 'fx_admin_users_list', 'fx_ping', 'fx_picture']);
    });

    it('hands over only the tools asked for', function () {
        $tools = (new McpServer(fixtureServer(), only: ['admin.users.list', 'echo']))->tools();

        expect(array_keys(byName($tools)))->toBe(['echo', 'admin_users_list']);
    });

    it('refuses a tool asked for that the server does not list', function () {
        (new McpServer(fixtureServer(), only: ['echo', 'delete_everything']))->tools();
    })->throws(McpException::class, 'The MCP server "papi-fixture" lists no tool "delete_everything". It lists: "echo", "add", "fail", "admin.users.list", "ping", "picture".');

    it('refuses two tools that would reach the model under one name', function () {
        (new McpServer(fixtureServer(['collide'])))->tools();
    })->throws(McpException::class, 'The MCP server "papi-fixture" has tools "admin.users.list" and "admin_users_list", which both reach the model as "admin_users_list". Leave one out with "only".');

    it('says which server it could not reach', function () {
        (new McpServer(new StdioTransport(PHP_BINARY, ['-r', 'exit(3);']), client: Client::builder()->setInitTimeout(2)->build()))->tools();
    })->throws(McpException::class, 'Could not list the tools of the MCP server:');

    it('says which tool it could not run', function () {
        byName((new McpServer(fixtureServer(['crash'])))->tools())['crash']->execute([]);
    })->throws(McpException::class, 'Could not run the tool "crash" on the MCP server "papi-fixture":');

    it('stops the server when it disconnects', function () {
        $client = Client::builder()->setClientInfo('test', '1.0.0')->build();
        $server = new McpServer(fixtureServer(), client: $client);
        $server->tools();

        $server->disconnect();

        expect($client->isConnected())->toBeFalse();
    });

    it('connects again when a tool is run after disconnecting', function () {
        $server = new McpServer(fixtureServer());
        $echo = byName($server->tools())['echo'];
        $server->disconnect();

        expect($echo->execute(['text' => 'again']))->toBe('again');
    });
});
