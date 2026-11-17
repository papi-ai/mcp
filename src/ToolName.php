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

/**
 * The name a tool reaches the model under. Providers accept letters, digits, "_" and "-", up to 64
 * characters; MCP also allows dots and up to 128. Anything else becomes "_", and a name that is
 * still too long keeps its start and ends in a hash of the whole, so it stays unique and stable.
 */
final class ToolName
{
    private const MAX_LENGTH = 64;

    private const HASH_LENGTH = 8;

    public function __construct(
        private readonly string $remote,
        private readonly ?string $prefix = null,
    ) {
    }

    public function exposed(): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $this->prefix === null ? $this->remote : $this->prefix . '_' . $this->remote);

        if (strlen($name) <= self::MAX_LENGTH) {
            return $name;
        }

        return substr($name, 0, self::MAX_LENGTH - self::HASH_LENGTH - 1) . '_' . substr(hash('sha256', $name), 0, self::HASH_LENGTH);
    }
}
