# Plan 03: Responses - Status

## Status: ✅ COMPLETE

## Summary
Comprehensive Response class with all planned features implemented including streaming.

## Response Class
File: `src/Response.php`

### Core Methods
| Method | Planned | Implemented | Notes |
|--------|---------|-------------|-------|
| `status(): int` | ✅ | ✅ | |
| `headers(): array` | ✅ | ✅ | |
| `header(string): ?string` | - | ✅ | Extra |
| `body(): string` | ✅ | ✅ | |
| `json(): array` | ✅ | ✅ | Supports dot notation key |
| `object(): stdClass` | ✅ | ✅ | |
| `collect(): Collection` | ✅ | ✅ | |
| `dto(class): T` | ✅ | ✅ | |
| `dtoCollection()` | ✅ | ✅ | |
| `ok(): bool` | ✅ | ✅ | |
| `successful(): bool` | - | ✅ | Alias for ok() |
| `failed(): bool` | ✅ | ✅ | |
| `serverError(): bool` | ✅ | ✅ | |
| `clientError(): bool` | ✅ | ✅ | |
| `redirect(): bool` | - | ✅ | Extra |
| `toPsrResponse()` | ✅ | ✅ | |
| `throw()` | ✅ | ✅ | Throws on error status |

### Response Mutation (Immutable)
| Method | Planned | Implemented |
|--------|---------|-------------|
| `withJson(array)` | ✅ | ✅ |
| `withJsonKey(key, value)` | ✅ | ✅ |
| `withBody(string)` | ✅ | ✅ |
| `withHeaders(array)` | ✅ | ✅ |
| `withHeader(name, value)` | ✅ | ✅ |
| `withStatus(int)` | ✅ | ✅ |

### Streaming Support
| Method | Planned | Implemented |
|--------|---------|-------------|
| `streamTo(path, progress)` | ✅ | ✅ |
| `chunks(chunkSize)` | ✅ | ✅ |
| `stream()` | ✅ | ✅ |

### Timing & Context
| Method | Planned | Implemented |
|--------|---------|-------------|
| `duration()` | ✅ | ✅ |
| `transferTime()` | ✅ | ❌ |
| `request()` | ✅ | ✅ |
| `effectiveUri()` | ✅ | ❌ |

### Conditional Request Support
| Method | Planned | Implemented |
|--------|---------|-------------|
| `etag()` | ✅ | ✅ |
| `lastModified()` | ✅ | ✅ |
| `wasNotModified()` | ✅ | ✅ |
| `fromCache()` | ✅ | ✅ |

### Tracing
| Method | Planned | Implemented |
|--------|---------|-------------|
| `traceId()` | ✅ | ✅ |
| `spanId()` | ✅ | ✅ |

### Idempotency
| Method | Planned | Implemented |
|--------|---------|-------------|
| `idempotencyKey()` | ✅ | ✅ |
| `wasIdempotentReplay()` | ✅ | ✅ |

### Serialization
| Method | Planned | Implemented |
|--------|---------|-------------|
| `serialize()` | ✅ | ❌ |
| `unserialize()` | ✅ | ❌ |
| `toJson()` | ✅ | ❌ |
| `fromJson()` | ✅ | ❌ |

### File Downloads (Extra)
| Method | Implemented |
|--------|-------------|
| `saveTo(path)` | ✅ |
| `stream()` | ✅ |
| `filename()` | ✅ |
| `isDownload()` | ✅ |
| `base64()` | ✅ |

### Debugging (Extra)
| Method | Implemented |
|--------|-------------|
| `dump()` | ✅ |
| `dd()` | ✅ |

### Rate Limiting (Extra)
| Method | Implemented |
|--------|-------------|
| `rateLimit(): ?RateLimitInfo` | ✅ |

## DTO Mapping via Attribute
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `#[Dto(class)]` attribute | ✅ | ✅ |
| `dataKey` parameter | ✅ | ✅ |

## Stream Attribute
File: `src/Attributes/Stream.php`
- `#[Stream]` attribute for marking streaming requests

## Missing Features
- [ ] `transferTime()` - Transfer time separate from total duration
- [ ] `effectiveUri()` - Final URL after redirects
- [ ] `serialize()` / `unserialize()` - Array serialization
- [ ] `toJson()` / `fromJson()` - JSON serialization

## Files Created
- `src/Response.php`
- `src/RateLimitInfo.php`
- `src/Attributes/Dto.php`
- `src/Attributes/Stream.php`

## Tests
- `tests/Unit/ResponseTest.php`
