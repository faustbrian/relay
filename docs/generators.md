---
title: Generators
description: Artisan commands to scaffold API integrations quickly in Relay
---

Relay provides powerful Artisan commands to scaffold API integrations quickly.

## Quick Start

```bash
# Create a complete integration
php artisan make:integration GitHub --oauth --resources=Users,Repositories

# Or build piece by piece
php artisan make:connector GitHub --bearer
php artisan make:resource Users GitHub --crud --requests
php artisan make:request GetUser GitHub --method=get
```

## Commands Overview

| Command | Description |
|---------|-------------|
| `make:integration` | Scaffold complete API integration |
| `make:connector` | Create connector class |
| `make:request` | Create request class |
| `make:resource` | Create resource class |

## make:integration

Creates a complete API integration with connector, resources, requests, and directory structure.

```bash
php artisan make:integration {name} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--oauth` | OAuth2 authentication |
| `--bearer` | Bearer token authentication |
| `--basic` | Basic authentication |
| `--api-key` | API key authentication |
| `--cache` | Enable caching |
| `--rate-limit` | Enable rate limiting |
| `--resilience` | Circuit breaker & retry |
| `--resources=` | Comma-separated resources |
| `--graphql` | GraphQL integration |
| `--jsonrpc` | JSON-RPC integration |

### Examples

```bash
php artisan make:integration Stripe --oauth --cache
php artisan make:integration Twitter --bearer --rate-limit --resources=Users,Tweets
php artisan make:integration GitHub --graphql --bearer
```

## make:connector

```bash
php artisan make:connector {name} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--oauth` | OAuth2 with AuthorizationCodeGrant |
| `--bearer` | Bearer token authentication |
| `--basic` | Basic (username/password) auth |
| `--api-key` | API key in header |
| `--cache` | Caching with CacheConfig |
| `--rate-limit` | Rate limiting with RateLimitConfig |
| `--resilience` | Circuit breaker & retry |

## make:request

```bash
php artisan make:request {name} {connector} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--method=` | HTTP method (get, post, put, patch, delete) |
| `--json` | JSON content type |
| `--paginate` | Page-based pagination |
| `--cursor` | Cursor-based pagination |
| `--cache` | Add Cache attribute |
| `--retry` | Add Retry attribute |
| `--graphql` | GraphQL request |
| `--jsonrpc` | JSON-RPC request |

### Examples

```bash
php artisan make:request GetUser GitHub
php artisan make:request CreateUser GitHub --method=post --json
php artisan make:request ListUsers GitHub --paginate
php artisan make:request GetUserQuery GitHub --graphql
```

## make:resource

```bash
php artisan make:resource {name} {connector} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--crud` | Generate CRUD methods |
| `--paginate` | Add pagination support |
| `--requests` | Also create request classes |

### Examples

```bash
php artisan make:resource Users GitHub --crud --requests
php artisan make:resource Posts GitHub --paginate
```

## Customizing Stubs

```bash
php artisan vendor:publish --tag=relay-stubs
```

This creates a `stubs/relay/` directory where you can modify the templates.

## Best Practices

1. **Use `make:integration` for new APIs** - Sets up the complete structure
2. **Use `--crud --requests` together** - Creates matching requests for resources
3. **Choose authentication upfront** - Harder to change later
4. **Add rate limiting for external APIs** - Prevents hitting API limits
