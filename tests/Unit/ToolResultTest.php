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

use Mcp\Schema\Content\AudioContent;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Result\CallToolResult;
use PapiAI\Mcp\ToolFailedException;
use PapiAI\Mcp\ToolResult;

describe('ToolResult', function () {
    it('gives the model the text the tool returned', function () {
        $result = new CallToolResult([new TextContent('Sunny'), new TextContent('22 degrees')]);

        expect((new ToolResult($result))->value())->toBe("Sunny\n22 degrees");
    });

    it('gives the model the structured content when there is some', function () {
        $result = new CallToolResult([new TextContent('{"sum":5}')], structuredContent: ['sum' => 5]);

        expect((new ToolResult($result))->value())->toBe(['sum' => 5]);
    });

    it('describes what the model cannot read', function () {
        $result = new CallToolResult([
            new ImageContent('aGk=', 'image/png'),
            new AudioContent('aGk=', 'audio/wav'),
            new ResourceLink('file:///notes.md', 'notes.md'),
            new EmbeddedResource(new TextResourceContents('file:///a.txt', 'text/plain', 'inline text')),
            new EmbeddedResource(new BlobResourceContents('file:///a.bin', 'application/octet-stream', 'aGk=')),
        ]);

        expect((new ToolResult($result))->value())->toBe(implode("\n", [
            '[image/png image]',
            '[audio/wav audio]',
            'file:///notes.md',
            'inline text',
            '[application/octet-stream resource at file:///a.bin]',
        ]));
    });

    it('throws with what the tool said when it reports a failure', function () {
        (new ToolResult(new CallToolResult([new TextContent('Invalid date')], isError: true)))->value();
    })->throws(ToolFailedException::class, 'Invalid date');

    it('throws even when a failing tool says nothing', function () {
        (new ToolResult(new CallToolResult([], isError: true)))->value();
    })->throws(ToolFailedException::class, 'The tool reported a failure without saying why.');
});
