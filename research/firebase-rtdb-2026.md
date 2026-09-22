# Firebase Realtime Database data-layer coding standards (2026)

Status: Reviewed against primary sources, 2026-09-22.
Scope: this repo's `RtdbService` (Laravel 12 / PHP 8.2+, RTDB REST API as sole datastore, service-account OAuth, kitchen page polling 5–10s, stock decrement-after-insert). Every source link is an official Firebase or Google document.

## 1. Flat over deep

- Data models MUST stay as flat as practical: fetching a location retrieves *all* of its child nodes, and granting read/write at a node grants it to everything under it, so nesting costs bandwidth and widens the rules blast radius ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- Data nodes MUST NOT nest deeper than 4 levels below the root (RTDB physically allows up to 32 levels, but the official guidance is "in practice, it's best to keep your data structure as flat as possible" — [Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- A list/meta path MUST NOT contain bulk payload children (e.g. messages under a chat-list node): the docs' counter-example shows listing conversation titles then requiring "potentially downloading hundreds of megabytes of messages" — [Structure Your Database](https://firebase.google.com/docs/database/web/structure-data).
- List children MUST be keyed by server push key (`POST` generates a unique child name — [Saving Data](https://firebase.google.com/docs/database/rest/save-data)) or by stable entity ID; keys MUST NOT be array indices from JSON arrays.
- Two-way relationships MUST be stored as index maps `{"$id": true}` at both ends; membership checks MUST read one path (`/users/$uid/groups/$group_id` is null or not) instead of scanning — the docs call the resulting duplication "a necessary redundancy for two-way relationships" ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).

Bad (one fetch drags every message):

```json
{ "chats": { "one": { "title": "...", "messages": { "m1": { "message": "..." }, "...": {} } } } }
```

Good (meta, members, messages in separate paths; each fetch is narrow):

```json
{
  "chats":    { "one": { "title": "...", "lastMessage": "...", "timestamp": 1459361875666 } },
  "members":  { "one": { "ghopper": true, "alovelace": true } },
  "messages": { "one": { "m1": { "message": "...", "timestamp": 1459361875337 } } }
}
```

([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data))

## 2. Denormalization and duplicated copies

- Any fact stored at N paths (roadmap item 3: product names in carts *and* line items) MUST be written by exactly ONE multi-path request; writing the copies in N sequential requests is NEVER allowed — simultaneous multi-path updates are documented as atomic: "either all updates succeed or all updates fail" ([Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- The multi-path request MUST be a single REST `PATCH` whose body is a flat map of slash-delimited path keys relative to the request URL, issued at the common ancestor of all targets ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [REST API reference](https://firebase.google.com/docs/reference/rest/database)).

Good (one atomic `PATCH …/users.json`):

```json
{ "alanisawesome/nickname": "Alan The Machine", "gracehopper/nickname": "Amazing Grace" }
```

Bad (nested objects are NOT multi-path keys — this overwrites the entire parent node):

```json
{ "alanisawesome": { "nickname": "Alan The Machine" }, "gracehopper": { "nickname": "Amazing Grace" } }
```

([Saving Data](https://firebase.google.com/docs/database/rest/save-data))

- Fan-out MUST NOT span database instances: "Realtime Database doesn't support queries across database instances" and sharded data should have "no sharing or duplication of data across database instances" ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)).
- A stock delta and the records it belongs to MUST ride in the same request body where they must change together: a `".sv"` server value inside a multi-path `PATCH` is resolved by the database server in that single atomic write ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [REST API reference — Server Values](https://firebase.google.com/docs/reference/rest/database)).
- Note: multi-path updates across top-level trees *within one instance* are supported and atomic (the docs' own example writes `/posts/$id` and `/user-posts/$uid/$id` from the root — [Read and write data](https://firebase.google.com/docs/database/web/read-and-write)); sequential per-copy writes and any fan-out across instance URLs are NEVER allowed.

## 3. `.indexOn` for every query path

- Every query `RtdbService` issues MUST be covered by an `.indexOn` rule at or above the queried node; a REST query whose index is not defined fails with HTTP 400, and the docs are explicit that "indexes are not required for development *unless you are using the REST API*" ([Index your data](https://firebase.google.com/docs/database/security/indexing-data), [REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- The rules file MUST ship an `.indexOn` entry for each query path in the codebase; adding a query without its index entry is a review blocker ("Before launching your app … it is important to specify indexes for any queries you have" — [Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Queries MUST use exactly one `orderBy` per request — "Queries can only filter by one key at a time. Using the `orderBy` parameter multiple times on the same request throws an error" ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Every `orderBy` MUST be combined with at least one of `startAt`, `endAt`, `limitToFirst`, `limitToLast`, `equalTo` ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Ordering by a node's own key needs no index (keys are auto-indexed: "A node's key is indexed automatically" — [Index your data](https://firebase.google.com/docs/database/security/indexing-data)); ordering by *value* MUST declare `".indexOn": ".value"` ([Index your data](https://firebase.google.com/docs/database/security/indexing-data)).
- `.indexOn` MUST live in the deployed security-rules JSON — rules are enforced on the Firebase servers at all times, on every read and write ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).

## 4. Security-rules posture: locked rules, backend-only writes

- The database MUST run in locked mode: `".read": false, ".write": false` at the root. Locked mode is defined as "Denies all reads and writes from mobile and web clients. Your authenticated application servers can still access your database." ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)); by default "your rules do not allow anyone access to your database" ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Shared/production databases MUST NOT use test mode, which "allows anyone to read and overwrite your data" ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)).
- All reads and writes MUST originate from the Laravel backend authenticated with a Google OAuth2 access token from a service account; that credential is the documented way to "grant that server full read and write access" and to "bypass your Realtime Database Rules" ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- An unauthenticated request to the database URL MUST be denied (smoke check: `GET <db>.firebaseio.com/.json` with no token → 401 when locked). Unauthenticated REST calls succeed only if rules allow public access, and rule violations return 401 ([REST API reference — Authenticate requests / Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- The rules file MUST contain only deny-all `.read`/`.write` plus `.indexOn` entries. `.read`/`.write` cascade and shallower rules override deeper ones, so a single `true` branch anywhere re-opens the tree ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)).
- Data validation MUST NOT rely on `.validate` rules: OAuth-authenticated requests bypass the rules layer entirely ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)), and even where rules apply, "validation rules do not cascade — all relevant validation rules must evaluate to true" ([Understand Firebase Realtime Database Security Rules](https://firebase.google.com/docs/database/security)). Integrity checks belong in the PHP domain layer (§8).
- Legacy database secrets MUST NOT be used; they are long-lived credentials and the official guidance is to migrate to OAuth2 access tokens or ID tokens ([Authenticate REST Requests — Legacy tokens](https://firebase.google.com/docs/database/rest/auth)).

## 5. Service-account OAuth tokens

- Token minting MUST request exactly the two required scopes: `https://www.googleapis.com/auth/userinfo.email` and `https://www.googleapis.com/auth/firebase.database` ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- Requests MUST carry the token as `Authorization: Bearer <ACCESS_TOKEN>`; the `access_token=` query-string form MUST NOT be used (it is documented only as an alternative — [Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth), [REST API reference](https://firebase.google.com/docs/reference/rest/database)) because URLs end up in logs.
- Access tokens MUST be cached in the PHP process and reused for their full lifetime: they expire at `expires_in: 3600` seconds, and "access tokens can be reused during the duration window specified by the `expires_in` value" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)). Minting a token per request is NEVER allowed; Google's own example flags direct per-request token use as "(not recommended)" in favor of a library-managed session ([Authenticate REST Requests](https://firebase.google.com/docs/database/rest/auth)).
- A cached token MUST be refreshed when its age reaches 55 minutes (3300s), before the 3600s expiry; when a token expires the application "should generate another JWT, sign it, and request another access token" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)).
- A 401 response ("auth token has expired", "auth token … invalid", "authenticating with an access_token failed" — [REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)) MUST invalidate the cached token and retry the request exactly once after refreshing; a second 401 MUST throw.
- JWT construction MUST go through Google's client libraries, not hand-rolled signing: libraries are "strongly recommended due to the complexity and security risks associated with creating and signing JSON Web Tokens" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)). For PHP this means one of Google's official client libraries (linked from the OAuth guide above) or one of the PHP helper libraries listed in the official REST setup docs ([Installation & Setup for REST API](https://firebase.google.com/docs/database/rest/start)).
- The service-account JSON key file MUST be stored outside the repo and outside the web root; it MUST NOT be committed ("Don't submit service account keys to source code repositories … always store the service account key separate from the source code" — [Best practices for managing service account keys](https://cloud.google.com/iam/docs/best-practices-for-managing-service-account-keys)).
- Keys SHOULD be rotated routinely and SHOULD carry an expiry time ([Best practices for managing service account keys](https://cloud.google.com/iam/docs/best-practices-for-managing-service-account-keys)).
- JWT `exp` MUST be exactly `iat + 3600`; `invalid_grant` responses caused by clock skew MUST be treated as a host-clock fault, not retried blindly — the OAuth error table says to "use a clock with skew to account for clock differences between systems" ([Using OAuth 2.0 for Server to Server Applications](https://developers.google.com/identity/protocols/oauth2/service-account)).

## 6. Writes: atomicity, counters, stock

- All writes MUST go to `<db>.firebaseio.com/<path>.json` over HTTPS — "HTTPS is required. Firebase only responds to encrypted traffic" ([REST API reference](https://firebase.google.com/docs/reference/rest/database)).
- Updates to existing records MUST use `PATCH` (omitted children are preserved); `PUT` MUST only be used where overwriting the whole path is intended, because PUT replaces the path with the payload ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Every timestamp written MUST be the server value `{".sv": "timestamp"}`; PHP clocks (`time()`, `now()`) MUST NOT appear in write payloads — server timestamps are the documented accurate option ([Saving Data](https://firebase.google.com/docs/database/rest/save-data), [Enabling Offline Capabilities](https://firebase.google.com/docs/database/web/offline-capabilities)).
- Stock quantity changes MUST use the atomic server value `{".sv": {"increment": <delta>}}` (negative delta for decrement): it is applied "directly on the database server, [so] there is no chance of a conflict", initializes a missing node to the delta, and follows IEEE 754 semantics on overflow ([REST API reference — Server Values](https://firebase.google.com/docs/reference/rest/database), [Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- Stock MUST NEVER be updated by read-modify-write (GET quantity → subtract in PHP → PUT absolute value); the docs identify incremental counters as data "that could be corrupted by concurrent modifications", which is what transactions/conditional requests exist for ([Read and write data](https://firebase.google.com/docs/database/web/read-and-write)).
- Note: there is no `/.info/serverValue` location — official docs document only `/.info/connected` and `/.info/serverTimeOffset` under `.info/` ([Enabling Offline Capabilities](https://firebase.google.com/docs/database/web/offline-capabilities)). Server values are `".sv"` placeholders inside write payloads; code MUST NOT reference `/.info/serverValue`.

Bad (two racing writers both read 5, both write 4, one sale vanishes):

```php
$stock = $rtdb->get("products/$id/stock");          // GET
$rtdb->put("products/$id/stock", $stock - $qty);    // PUT — lost update
```

Good (no read needed; server applies the delta atomically):

```php
$rtdb->patch("items/$cartId/$lineNo", [
    'product' => $productId, 'qty' => $qty,          // duplicated copy
]);
$rtdb->patch('/', ["products/$productId/stock" => ['.sv' => ['increment' => -$qty]]]);
```

- The decrement-after-insert flow MUST be: (1) `POST` the order/line item, which returns `{"name": "<key>"}` with 200; (2) only on success, apply duplicated copies plus the stock delta in ONE multi-path `PATCH` (atomic — [Read and write data](https://firebase.google.com/docs/database/web/read-and-write), [REST API reference](https://firebase.google.com/docs/reference/rest/database)). If step 1 fails, step 2 MUST NOT run (§8).
- Where a write MUST depend on the current stored value (e.g. refuse to oversell), it MUST use a conditional request, "the REST equivalent to transactions": `GET` with `X-Firebase-ETag: true` → `PUT`/`DELETE` with `if-match: <etag>` → on `412 Precondition Failed`, take the ETag/value returned in the failure response and retry manually; "Realtime Database does not automatically retry conditional requests", so retries MUST be bounded (≤ 3 attempts) and MUST then throw ([Saving Data — Conditional Requests](https://firebase.google.com/docs/database/rest/save-data)).
- Conditional requests MUST NOT be attempted with `PATCH` (returns 400) and MUST supply exactly one ETag value ([REST API reference — Conditional Requests](https://firebase.google.com/docs/reference/rest/database), [Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- `X-Firebase-ETag` MUST be sent only where an ETag is needed (conditional flow): Realtime Database returns ETags only for requests carrying that header, which "reduces billing costs for standard requests" ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Writes MUST fit the instance's default `writeSizeLimit` (`tiny`=1s/`small`=10s/`medium`=30s/`large`=60s target; new databases default to `large`); `writeSizeLimit=unlimited` MUST NOT be used — it allows payloads up to 256MB that can block subsequent requests and "writes cannot be canceled once they reach the server" ([REST API reference — writeSizeLimit](https://firebase.google.com/docs/reference/rest/database)).

## 7. Kitchen polling read hygiene (5–10s refresh)

- Each poll tick MUST request ONE narrow, pre-scoped query path (e.g. open tickets via `orderBy` + `limitToLast`) and MUST NOT poll the root or a parent node — fetching a location retrieves all children ([Structure Your Database](https://firebase.google.com/docs/database/web/structure-data)).
- Every poll GET MUST send `timeout` with a value ≤ 10s (the poll interval): a read that exceeds the timeout terminates with HTTP 400, and the default maximum is 15 minutes — far too long to let a stuck read pile up behind a 5–10s loop ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- Every poll MUST bound its result set with `limitToFirst`/`limitToLast` ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data)).
- `shallow=true` MAY be used for keys-only/existence checks, but MUST NOT be combined with any other query parameter ([REST API reference — shallow](https://firebase.google.com/docs/reference/rest/database)).
- Change detection MUST be done in PHP by comparing successive payloads; conditional GETs MUST NOT be attempted — `if-match` is supported only on `PUT`/`DELETE`, and `GET` with `if-match` returns 400 ([REST API reference — Expected responses](https://firebase.google.com/docs/reference/rest/database)).
- Pollers MUST NOT rely on HTTP caches: the RTDB response headers documented in the official flow carry `Cache-Control: no-cache` ([Saving Data — Conditional Requests](https://firebase.google.com/docs/database/rest/save-data)); every tick performs a real request.
- If polling is ever replaced by SSE streaming, the client MUST handle the documented `cancel` event (rules revoked, or payload over the 512MB stream limit) and the `auth_revoked` event (expired credential) ([REST API reference — Streaming](https://firebase.google.com/docs/reference/rest/database)).
- Capacity: the single-instance ceiling that triggers sharding is 200,000 simultaneous connections and 1,000 write operations/second; poll-plus-write load MUST be monitored against it ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)). If sharding is adopted, each query MUST target exactly one instance and MUST NOT share or duplicate data across instances ([Scale with Multiple Databases](https://firebase.google.com/docs/database/usage/sharding)).

## 8. Error handling: fail loudly

- `RtdbService` MUST throw on every non-2xx response and MUST NOT return `null`, `[]`, `0`, or any placeholder on failure. The documented failure surface is: 400 (unparseable/too-large payload, invalid child name, missing query index, unrecognized server value, unsupported query combination, read/write timeouts), 401 (expired/invalid token or rule violation), 404, 412 (ETag mismatch outside the bounded conditional flow), 500, 503 ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database), [Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- Exceptions MUST carry the HTTP status and response body into Laravel's exception handler; `catch` blocks around `RtdbService` calls MUST NOT swallow the exception (empty catch / `catch {} then continue` is a review blocker).
- A 401 MUST be logged at error level and surfaced: per the error table it means an expired or invalid credential, a failed `access_token` authentication, or a rules violation — all configuration faults, not transient noise ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- Automatic retries MUST be limited to idempotent `GET`s. 503 alone MAY be retried with backoff even for writes, because it means "the request was not attempted"; 500 MUST NOT be retried for writes because the server-side outcome is unknown ([REST API reference — Error Conditions](https://firebase.google.com/docs/reference/rest/database)).
- A `print=silent` write MUST treat `204 No Content` as success and MUST NOT attempt to JSON-decode the empty body ([Retrieving Data](https://firebase.google.com/docs/database/rest/retrieve-data), [REST API reference — print](https://firebase.google.com/docs/reference/rest/database)).
- A `412` inside the conditional flow MUST NOT be treated as a hard failure until the bounded retry budget (≤ 3, §6) is exhausted, after which it MUST throw with the last ETag/value attached ([Saving Data](https://firebase.google.com/docs/database/rest/save-data)).
- A 400 whose body names a missing query index MUST be treated as a deploy bug (rules file out of sync with code, §3), never worked around by client-side filtering ([Index your data](https://firebase.google.com/docs/database/security/indexing-data)).

---

All sources are official Firebase/Google documentation, accessed 2026-09-22.
