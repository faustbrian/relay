# Plan 26: Idempotency - Status

## Status: ✅ COMPLETE

## Summary
Full idempotency support with `#[Idempotent]` attribute and `IdempotencyManager` class.

## Classes
| Class | Planned | Implemented | File |
|-------|---------|-------------|------|
| `IdempotencyManager` | ✅ | ✅ | `src/Security/IdempotencyManager.php` |
| `#[Idempotent]` attribute | ✅ | ✅ | `src/Attributes/Idempotent.php` |

## #[Idempotent] Attribute
File: `src/Attributes/Idempotent.php`

```php
#[Idempotent(
    header: 'Idempotency-Key',  // Custom header name
    keyMethod: 'generateKey',   // Method to call for custom key
    enabled: true               // Enable/disable
)]
```

### Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| Custom header name | ✅ | ✅ |
| Custom key method | ✅ | ✅ |
| Enable/disable toggle | ✅ | ✅ |
| Auto key generation | ✅ | ✅ |

## IdempotencyManager Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| Generate key | ✅ | ✅ |
| Add to request | ✅ | ✅ |
| Custom header name | ✅ | ✅ |
| Cache response | ✅ | ✅ |
| Get cached response | ✅ | ✅ |
| TTL support | ✅ | ✅ |
| Mark as replay | ✅ | ✅ |
| Invalidate | ✅ | ✅ |

## Request Integration
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `$request->idempotencyKey()` | ✅ | ✅ |
| `$request->withIdempotencyKey()` | ✅ | ✅ |
| `$request->isIdempotent()` | ✅ | ✅ |
| `$request->idempotencyHeader()` | ✅ | ✅ |
| Auto apply on `initialize()` | ✅ | ✅ |
| `$response->wasIdempotentReplay()` | ✅ | ✅ |

## Request.php Integration
The `#[Idempotent]` attribute is automatically processed in `Request::initialize()`:
- Checks for attribute via `isIdempotent()`
- Generates random key or calls custom method
- Adds key to request headers

### Not Implemented
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `throwOnDuplicate` option | ✅ | ❌ |
| Connector-level default | ✅ | ❌ |

## Files Created
- `src/Security/IdempotencyManager.php`
- `src/Attributes/Idempotent.php`

## Tests
- `tests/Unit/Security/IdempotencyTest.php`
- `tests/Unit/Security/IdempotentAttributeTest.php`
