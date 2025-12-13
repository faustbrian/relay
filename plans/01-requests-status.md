# Plan 01: Requests - Status

## Status: ✅ COMPLETE

## Summary
Full implementation of the Request system with attributes and base class.

## Attributes Implemented

| Attribute | File | Status |
|-----------|------|--------|
| `#[Get]` | `src/Attributes/Methods/Get.php` | ✅ |
| `#[Post]` | `src/Attributes/Methods/Post.php` | ✅ |
| `#[Put]` | `src/Attributes/Methods/Put.php` | ✅ |
| `#[Patch]` | `src/Attributes/Methods/Patch.php` | ✅ |
| `#[Delete]` | `src/Attributes/Methods/Delete.php` | ✅ |
| `#[Head]` | `src/Attributes/Methods/Head.php` | ✅ |
| `#[Options]` | `src/Attributes/Methods/Options.php` | ✅ |
| `#[Json]` | `src/Attributes/ContentTypes/Json.php` | ✅ |
| `#[Form]` | `src/Attributes/ContentTypes/Form.php` | ✅ |
| `#[Multipart]` | `src/Attributes/ContentTypes/Multipart.php` | ✅ |
| `#[Xml]` | `src/Attributes/ContentTypes/Xml.php` | ✅ |
| `#[JsonRpc]` | `src/Attributes/Protocols/JsonRpc.php` | ✅ |
| `#[XmlRpc]` | `src/Attributes/Protocols/XmlRpc.php` | ✅ |
| `#[Soap]` | `src/Attributes/Protocols/Soap.php` | ✅ |
| `#[GraphQL]` | `src/Attributes/Protocols/GraphQL.php` | ✅ |
| `#[ThrowOnError]` | `src/Attributes/ThrowOnError.php` | ✅ |

## Base Request Class
File: `src/Request.php`

| Feature | Planned | Implemented | Notes |
|---------|---------|-------------|-------|
| `endpoint(): string` | Abstract | ✅ Abstract | |
| `body(): ?array` | Optional | ✅ | Returns null by default |
| `query(): ?array` | Optional | ✅ | Returns null by default |
| `headers(): ?array` | Optional | ✅ | Returns null by default |
| `boot(): void` | Lifecycle | ❌ | Not implemented |
| `transformResponse()` | Lifecycle | ❌ | Not implemented |
| `clone(): static` | Cloning | ❌ | Not implemented |

## Additional Features (Beyond Plan)
- `withHeader()` - Add header fluently
- `withQuery()` - Add query param fluently
- `withIdempotencyKey()` - Set idempotency key
- `allHeaders()` - Get merged headers
- `allQuery()` - Get merged query params
- `method()` - Get HTTP method from attribute
- `contentType()` - Get content type from attribute

## Attribute Validation
- `AttributeConflictException` - ✅ Implemented for mutual exclusivity

## Differences from Plan
1. No `boot()` lifecycle hook
2. No `transformResponse()` lifecycle hook
3. No `clone()` method
4. Added fluent `withHeader()`/`withQuery()` instead

## Missing Features
- [ ] Request cloning (`clone()` method)
- [ ] `boot()` lifecycle hook
- [ ] `transformResponse()` hook

## Files Created
- `src/Request.php`
- `src/Attributes/Methods/*.php` (7 files)
- `src/Attributes/ContentTypes/*.php` (4 files)
- `src/Attributes/Protocols/*.php` (4 files)
- `src/Attributes/ThrowOnError.php`
- `src/Exceptions/AttributeConflictException.php`

## Tests
- `tests/Unit/Attributes/HttpMethodAttributesTest.php`
- `tests/Unit/Attributes/ContentTypeAttributesTest.php`
- `tests/Unit/Attributes/ProtocolAttributesTest.php`
