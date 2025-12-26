# Plan 22: SDK Example - Status

## Status: ⚠️ REFERENCE ONLY

## Summary
This plan is a documentation example showing how to build an SDK with Relay.
It's not code to implement - it demonstrates usage patterns.

## Example Features Covered
- Connector with authentication
- Resource classes for grouping requests
- Request classes with DTOs
- Idempotency for payments
- Testing with mocks

## Prerequisites for Full Example
| Feature | Status |
|---------|--------|
| Connectors | ✅ Implemented |
| Requests | ✅ Implemented |
| Authentication | ✅ Basic auth implemented |
| DTOs | ✅ Implemented |
| Idempotency | ✅ Implemented |
| Testing | ✅ Implemented |
| Resource classes | ❌ Not implemented |

## Missing for Full SDK Pattern
- [ ] `Resource` base class for grouping related requests
- [ ] `$connector->customers()->create()` pattern

## Notes
The SDK example pattern works with current implementation, but lacks the `Resource`
base class for clean grouping. Users can still implement this pattern manually.
