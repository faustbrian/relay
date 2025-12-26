# Plan 29: Serialization - Status

## Status: ⚠️ DTO Only

## Summary
DTO mapping via ResponseSerializer. Request/Response serialization was removed due to `toJson`/`fromJson` naming being inappropriate for a multi-content-type library (json, xml, soap, yaml).

## Classes
| Class | Planned | Implemented | File |
|-------|---------|-------------|------|
| `ResponseSerializer` | ✅ | ✅ | `src/Serialization/ResponseSerializer.php` |
| `DataTransferObject` interface | ✅ | ✅ | `src/Contracts/DataTransferObject.php` |
| Request serialization | ✅ | ❌ Removed | - |
| Response serialization | ✅ | ❌ Removed | - |

## ResponseSerializer Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `toDto()` | ✅ | ✅ |
| `toDtoFrom()` (from key) | ✅ | ✅ |
| `toDtoCollection()` | ✅ | ✅ |
| `toCollection()` | ✅ | ✅ |

## Request/Response Serialization - REMOVED
Request and Response serialization was implemented but removed because:
- `toJson`/`fromJson` naming is misleading for a library supporting multiple content types
- Library supports: JSON, XML, SOAP, and needs YAML support
- Content-type agnostic naming would be required (e.g., `serialize`/`deserialize`)
- Saloon only serializes `AccessTokenAuthenticator`, not Request/Response

## Files Created
- `src/Serialization/ResponseSerializer.php`
- `src/Contracts/DataTransferObject.php`

## Tests
- `tests/Unit/Serialization/SerializationTest.php`
