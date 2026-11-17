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

use Mcp\Schema\Tool;
use PapiAI\Core\Contracts\ToolInterface;

/**
 * A tool an MCP server offers, as the Agent sees it. Running it asks the server to run the tool.
 */
final class McpTool implements ToolInterface
{
    public function __construct(
        private readonly McpServer $server,
        private readonly Tool $tool,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The tool's name on the server, which may differ from the one the model sees.
     */
    public function remoteName(): string
    {
        return $this->tool->name;
    }

    public function getDescription(): string
    {
        return $this->tool->description ?? $this->tool->title ?? $this->tool->name;
    }

    public function getParameterSchema(): array
    {
        /** @var array{type: string, properties?: array, required?: array<string>} */
        return $this->tool->inputSchema;
    }

    /**
     * @throws ToolFailedException When the tool reports a failure
     * @throws McpException        When the server cannot be reached
     */
    public function execute(array $arguments, mixed $context = null): mixed
    {
        return $this->server->call($this->tool->name, $arguments);
    }
}
