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

namespace PapiAI\Mcp;

use Composer\InstalledVersions;
use Mcp\Client\Client;
use Mcp\Client\Transport\TransportInterface;
use Mcp\Schema\Exception\ExceptionInterface;
use Mcp\Schema\Tool;
use PapiAI\Core\Contracts\ToolInterface;

/**
 * One MCP server, whose tools an Agent can use: `$agent->tools($server->tools())`.
 *
 * The protocol is the official SDK's: its client speaks revision 2026-07-28 and falls back to the
 * initialize handshake for older servers, over stdio or Streamable HTTP. The connection opens on
 * first use and again after a disconnect, so a stdio server that exited is started anew.
 */
final class McpServer
{
    private readonly Client $client;

    /**
     * @param TransportInterface $transport The SDK's StdioTransport or HttpTransport
     * @param string|null        $prefix    Put in front of every tool name, to tell servers apart
     * @param list<string>|null  $only      The tools to hand over, by their names on the server
     * @param Client|null        $client    The SDK client, for timeouts, authorization or elicitation
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly ?string $prefix = null,
        private readonly ?array $only = null,
        ?Client $client = null,
    ) {
        $this->client = $client ?? Client::builder()
            ->setClientInfo('papi-ai/mcp', InstalledVersions::getPrettyVersion('papi-ai/mcp') ?? 'dev')
            ->build();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @return list<ToolInterface>
     *
     * @throws McpException When the server cannot be reached, lacks a tool in "only", or two names collide
     */
    public function tools(): array
    {
        $tools = [];

        foreach ($this->offered() as $tool) {
            $name = (new ToolName($tool->name, $this->prefix))->exposed();

            if (isset($tools[$name])) {
                throw new McpException(sprintf(
                    'The MCP server%s has tools "%s" and "%s", which both reach the model as "%s". Leave one out with "only".',
                    $this->named(),
                    $tools[$name]->remoteName(),
                    $tool->name,
                    $name,
                ));
            }

            $tools[$name] = new McpTool($this, $tool, $name);
        }

        return array_values($tools);
    }

    /**
     * Runs a tool on the server, by its name there, and reads the result for the model.
     *
     * @param array<string, mixed> $arguments
     *
     * @throws ToolFailedException When the tool reports a failure
     * @throws McpException        When the server cannot be reached
     */
    public function call(string $remoteName, array $arguments): mixed
    {
        try {
            $result = $this->connected()->callTool($remoteName, $arguments);
        } catch (ExceptionInterface $failure) {
            throw new McpException(sprintf('Could not run the tool "%s" on the MCP server%s: %s', $remoteName, $this->named(), $failure->getMessage()), 0, $failure);
        }

        return (new ToolResult($result))->value();
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    /**
     * Every tool the server lists, or only those asked for, in the server's order.
     *
     * @return list<Tool>
     */
    private function offered(): array
    {
        $listed = $this->listedTools();
        $only = $this->only;

        if ($only === null) {
            return $listed;
        }

        $names = array_map(static fn (Tool $tool): string => $tool->name, $listed);

        foreach ($only as $wanted) {
            if (!in_array($wanted, $names, true)) {
                throw new McpException(sprintf(
                    'The MCP server%s lists no tool "%s". It lists: %s.',
                    $this->named(),
                    $wanted,
                    implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', $names)),
                ));
            }
        }

        return array_values(array_filter($listed, static fn (Tool $tool): bool => in_array($tool->name, $only, true)));
    }

    /**
     * @return list<Tool>
     */
    private function listedTools(): array
    {
        $tools = [];
        $cursor = null;

        try {
            do {
                $page = $this->connected()->listTools($cursor);
                array_push($tools, ...$page->tools);
                $cursor = $page->nextCursor;
            } while ($cursor !== null);
        } catch (ExceptionInterface $failure) {
            throw new McpException(sprintf('Could not list the tools of the MCP server%s: %s', $this->named(), $failure->getMessage()), 0, $failure);
        }

        return array_values($tools);
    }

    private function connected(): Client
    {
        if (!$this->client->isConnected()) {
            $this->client->connect($this->transport);
        }

        return $this->client;
    }

    /**
     * The server's own name for messages, once it has given one.
     */
    private function named(): string
    {
        $name = $this->client->getServerInfo()?->name;

        return $name === null ? '' : ' "' . $name . '"';
    }
}
