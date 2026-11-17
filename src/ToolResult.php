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

use Mcp\Schema\Content\AudioContent;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\Content;
use Mcp\Schema\Content\EmbeddedResource;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Result\CallToolResult;

/**
 * What an MCP tool call gives the model: its structured content when it has some, otherwise its
 * content as text, with a short description of anything the model cannot read as text.
 */
final class ToolResult
{
    public function __construct(
        private readonly CallToolResult $result,
    ) {
    }

    /**
     * @throws ToolFailedException When the tool reported a failure
     */
    public function value(): mixed
    {
        if ($this->result->isError) {
            throw new ToolFailedException($this->text() !== '' ? $this->text() : 'The tool reported a failure without saying why.');
        }

        if ($this->result->hasStructuredContent()) {
            return $this->result->structuredContent;
        }

        return $this->text();
    }

    private function text(): string
    {
        return implode("\n", array_map($this->textOf(...), $this->result->content));
    }

    private function textOf(Content $content): string
    {
        return match (true) {
            $content instanceof TextContent => (string) $content->text,
            $content instanceof ImageContent => sprintf('[%s image]', $content->mimeType),
            $content instanceof AudioContent => sprintf('[%s audio]', $content->mimeType),
            $content instanceof ResourceLink => $content->uri,
            $content instanceof EmbeddedResource && $content->resource instanceof TextResourceContents => $content->resource->text,
            $content instanceof EmbeddedResource && $content->resource instanceof BlobResourceContents => sprintf('[%s resource at %s]', $content->resource->mimeType ?? 'binary', $content->resource->uri),
            default => sprintf('[%s content]', get_debug_type($content)),
        };
    }
}
