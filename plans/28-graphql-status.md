# Plan 28: GraphQL Support - Status

## Status: ✅ BASIC IMPLEMENTATION

## Summary
Basic GraphQL support is implemented with request and response classes.

## Classes
| Class | Planned | Implemented | File |
|-------|---------|-------------|------|
| `GraphQLRequest` | ✅ | ✅ | `src/GraphQL/GraphQLRequest.php` |
| `GraphQLResponse` | ✅ | ✅ | `src/GraphQL/GraphQLResponse.php` |
| `#[GraphQL]` attribute | ✅ | ✅ | `src/Attributes/Protocols/GraphQL.php` |
| `GraphQLSubscription` | ✅ | ❌ | Not implemented |
| Query builder | ✅ | ❌ | Not implemented |
| Fragment classes | ✅ | ❌ | Not implemented |

## GraphQLRequest Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `graphqlQuery()` abstract | ✅ | ✅ |
| `variables()` | ✅ | ✅ |
| `operationName()` | ✅ | ✅ |
| Custom endpoint | ✅ | ✅ |
| Auto POST + JSON | ✅ | ✅ |

## GraphQLResponse Features
| Feature | Planned | Implemented |
|---------|---------|-------------|
| `data()` accessor | ✅ | ✅ |
| `errors()` accessor | ✅ | ✅ |
| `hasErrors()` | ✅ | ✅ |
| `firstError()` | ✅ | ✅ |
| `errorMessages()` | ✅ | ✅ |
| `extensions()` | ✅ | ✅ |
| `successful()` | ✅ | ✅ |
| `failed()` | ✅ | ✅ |

## Missing Features
- [ ] Fragment support with `#[Fragments]` attribute
- [ ] Query builder (`GraphQL::query()`)
- [ ] Batched queries
- [ ] WebSocket subscriptions
- [ ] Persisted queries
- [ ] Automatic Persisted Queries (APQ)
- [ ] `#[ThrowOnGraphQLError]` attribute

## Files Created
- `src/GraphQL/GraphQLRequest.php`
- `src/GraphQL/GraphQLResponse.php`
- `src/Attributes/Protocols/GraphQL.php`

## Tests
- `tests/Unit/GraphQL/GraphQLTest.php`
