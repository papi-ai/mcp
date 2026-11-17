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

use PapiAI\Core\Agent;
use PapiAI\Core\Contracts\ProviderInterface;
use PapiAI\Core\Message;
use PapiAI\Core\Response;
use PapiAI\Core\Role;
use PapiAI\Core\ToolCall;
use PapiAI\Mcp\McpServer;

/**
 * Asks for one tool call, then answers with the tool result it was sent.
 */
function scriptedProvider(string $tool, array $arguments): ProviderInterface
{
    return new class ($tool, $arguments) implements ProviderInterface {
        public function __construct(
            private readonly string $tool,
            private readonly array $arguments,
        ) {
        }

        public function chat(array $messages, array $options = []): Response
        {
            $last = end($messages);

            if ($last instanceof Message && $last->role === Role::Tool) {
                return new Response(text: is_string($last->content) ? $last->content : (string) json_encode($last->content));
            }

            return new Response(text: '', toolCalls: [new ToolCall('call_1', $this->tool, $this->arguments)]);
        }

        public function stream(array $messages, array $options = []): iterable
        {
            return [];
        }

        public function supportsTool(): bool
        {
            return true;
        }

        public function supportsVision(): bool
        {
            return false;
        }

        public function supportsStructuredOutput(): bool
        {
            return false;
        }

        public function getName(): string
        {
            return 'scripted';
        }
    };
}

describe('An agent with MCP tools', function () {
    it('runs the tool the model asks for on the MCP server', function () {
        $agent = Agent::build()
            ->provider(scriptedProvider('fx_echo', ['text' => 'from the server']))
            ->model('scripted')
            ->tools((new McpServer(fixtureServer(), prefix: 'fx'))->tools())
            ->create();

        expect($agent->run('Echo something')->text)->toBe('from the server');
    });

    it('tells the model when the tool fails', function () {
        $agent = Agent::build()
            ->provider(scriptedProvider('fail', []))
            ->model('scripted')
            ->tools((new McpServer(fixtureServer()))->tools())
            ->create();

        expect($agent->run('Try the failing tool')->text)->toBe('{"error":"The fixture always fails."}');
    });
});
