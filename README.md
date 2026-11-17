# PapiAI MCP

[![CI](https://github.com/papi-ai/mcp/workflows/CI/badge.svg)](https://github.com/papi-ai/mcp/actions?query=workflow%3ACI) [![Latest Version](https://img.shields.io/packagist/v/papi-ai/mcp.svg)](https://packagist.org/packages/papi-ai/mcp) [![PHP Version](https://img.shields.io/packagist/php-v/papi-ai/mcp.svg)](https://packagist.org/packages/papi-ai/mcp) [![License](https://img.shields.io/packagist/l/papi-ai/mcp.svg)](https://packagist.org/packages/papi-ai/mcp)

Use the tools of any [Model Context Protocol](https://modelcontextprotocol.io) server from a [PapiAI](https://github.com/papi-ai/papi-core) agent, over stdio or HTTP.

## Installation

```bash
composer require papi-ai/mcp
```

The protocol is the official [MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk)'s. It needs `ext-iconv`, which most PHP builds include. Talking to a remote server also needs a PSR-18 HTTP client and PSR-17 factories, found automatically:

```bash
composer require symfony/http-client nyholm/psr7
```

## Usage

`McpServer` connects to one server and hands its tools to an agent as ordinary tools:

```php
use Mcp\Client\Transport\StdioTransport;
use PapiAI\Core\Agent;
use PapiAI\Mcp\McpServer;

$github = new McpServer(
    new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-github'], env: [...getenv(), 'GITHUB_TOKEN' => $token]),
);

$agent = Agent::build()
    ->provider($provider)
    ->tools($github->tools())
    ->create();

echo $agent->run('Which issues are open on papi-ai/mcp?')->text;
```

The client speaks protocol revision `2026-07-28` and falls back to the `initialize` handshake (up to `2025-11-25`) for servers that predate it, so both kinds work without configuration. The connection opens on first use; `disconnect()` closes it, and stops a stdio server's process. A tool run after that connects again.

### A remote server

```php
use Mcp\Client\Transport\HttpTransport;

$crm = new McpServer(new HttpTransport('https://crm.example.com/mcp', headers: ['Authorization' => 'Bearer ' . $token]));
```

### Several servers

Tools reach the model by name, so give each server a prefix when two might offer the same one:

```php
$crm = new McpServer($transport, prefix: 'crm'); // "search" reaches the model as "crm_search"

$agent = Agent::build()
    ->provider($provider)
    ->tools([...$github->tools(), ...$crm->tools()])
    ->create();
```

A name is also adjusted to what every provider accepts: characters other than letters, digits, `_` and `-` become `_` (`admin.users.list` becomes `admin_users_list`), and a name longer than 64 characters is shortened, ending in a hash that keeps it unique. Two tools that would reach the model under one name are refused.

### Only the tools you need

```php
$github = new McpServer($transport, only: ['search_issues', 'get_issue']);
```

Names in `only` are the server's own. Naming a tool the server does not list throws.

### Timeouts, authorization and elicitation

Pass the SDK client configured as you need, and `McpServer` uses it:

```php
use Mcp\Client\Client;

$client = Client::builder()
    ->setClientInfo('my-app', '1.0.0')
    ->setRequestTimeout(60)
    ->build();

$server = new McpServer($transport, client: $client);
```

See the SDK's [client documentation](https://github.com/modelcontextprotocol/php-sdk/tree/main/docs/client) for OAuth and for answering a server that asks the user for input.

## What the model sees

- A tool's description is the server's, or its title when it has none, and its parameters are the server's input schema.
- A result with structured content gives the model that value. Otherwise the model gets the text blocks, joined; an embedded text resource gives its text, a resource link its URI, and an image or audio clip a short note naming its type.
- A tool that reports a failure throws `ToolFailedException` with what it said, which the agent hands to the model as an error so it can correct itself. A server that cannot be reached throws `McpException`.

## Security

An MCP tool runs code you do not control, and its description is written by the server, so treat both as untrusted. Hand over only the tools the agent needs with `only`, and confirm sensitive calls in the agent's `beforeToolCall` hook.

## Requirements

- PHP 8.2+ with `ext-iconv`
- `papi-ai/papi-core` ^0.15.6
- A PSR-18 client and PSR-17 factories, for remote servers

## License

MIT
