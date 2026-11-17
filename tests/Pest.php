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

use Mcp\Client\Transport\StdioTransport;

/**
 * The fixture MCP server over stdio, started with the given flags ("handshake-only", "collide", "crash").
 *
 * @param list<string> $flags
 */
function fixtureServer(array $flags = []): StdioTransport
{
    return new StdioTransport(PHP_BINARY, [__DIR__ . '/Fixtures/server.php', ...$flags]);
}
