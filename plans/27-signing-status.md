# Plan 27: Request Signing - Status

## Status: ✅ PARTIALLY COMPLETE

## Summary
HMAC request signing is implemented. AWS Signature V4 and advanced features are not.

## Classes
| Class | Planned | Implemented | File |
|-------|---------|-------------|------|
| `RequestSigner` | ✅ | ✅ | `src/Security/RequestSigner.php` |
| `RequestSignerInterface` | ✅ | ✅ | `src/Contracts/RequestSignerInterface.php` |
| `#[HmacSignature]` attribute | ✅ | ❌ | Not implemented |
| `#[AwsSignature]` attribute | ✅ | ❌ | Not implemented |
| `AwsSignatureV4` | ✅ | ❌ | Not implemented |
| `WebhookVerifier` | ✅ | ❌ | Not implemented |

## RequestSigner Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| HMAC signing | ✅ | ✅ |
| Algorithm config | ✅ | ✅ |
| Custom header name | ✅ | ✅ |
| Include timestamp | ✅ | ✅ |
| Timestamp header | ✅ | ✅ |
| Verify signature | ✅ | ✅ |
| Payload building | ✅ | ✅ |

## Missing Features
- [ ] `#[HmacSignature]` attribute
- [ ] `#[AwsSignature]` attribute
- [ ] AWS Signature V4 implementation
- [ ] Session token support
- [ ] Connector-level signing config
- [ ] Webhook verification helper
- [ ] Signing specific body parts

## Files Created
- `src/Security/RequestSigner.php`
- `src/Contracts/RequestSignerInterface.php`

## Tests
- `tests/Unit/Security/RequestSignerTest.php`
