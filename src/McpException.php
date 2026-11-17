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

use PapiAI\Core\Exception\PapiException;

/**
 * An MCP server could not be reached, or did not offer what was asked of it.
 */
class McpException extends PapiException
{
}
