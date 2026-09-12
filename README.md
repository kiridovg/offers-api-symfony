# Supplier Offers API — Symfony

REST API for async supplier offer import, cheapest-offer search and safe booking.

**PHP 8.4 · Symfony 8.1 · PostgreSQL 18 · Doctrine ORM 3 · Messenger · FrankenPHP · Docker**

Legend: ✅ implemented · 🧪 verified by a functional test (35 tests, all green)

---

## Setup

Docker is the only requirement.

```bash
docker compose up --wait
```

The `php` container waits for PostgreSQL and runs migrations on start, so the schema is always
current. API: `https://localhost` (self-signed certificate, accept it once). Imports are consumed by
the `worker` container.

```bash
docker compose exec php bin/console doctrine:migrations:migrate   # schema
docker compose exec php bin/console doctrine:fixtures:load        # supplier-a, supplier-b
docker compose logs -f worker                                     # queue consumer

docker compose exec php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php bin/console --env=test doctrine:migrations:migrate --no-interaction
docker compose exec php bin/phpunit                               # 35 passed
docker compose exec php vendor/bin/phpstan analyse                # level 8
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run     # @Symfony + @Symfony:risky
```

Tests use a separate `app_test` database — `dbname_suffix` in `config/packages/doctrine.yaml`.

The Docker setup is [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker), the
de-facto standard template for Symfony, with a `worker` service added for the queue consumer.

---

## 1. Task

| | Requirement | Implementation | Test |
|---|---|---|---|
| ✅ | Imports offers asynchronously | `POST /api/imports` dispatches `ProcessImport`; parsing happens in the worker | 🧪 `testItAcceptsImportAndQueuesProcessing` |
| ✅ | Returns the cheapest available offer per property | `ROW_NUMBER()` window function, joined back on `rn = 1` | 🧪 `testItReturnsOnlyTheCheapestOfferForAProperty` |
| ✅ | Allows booking safely | transaction + `SELECT ... FOR UPDATE` | 🧪 `testItBooksTheLastUnitOnlyOnce` |

## 2. Tech

| | Requirement | Implementation |
|---|---|---|
| ✅ | PHP 8.2+ | 8.4 (the image ships 8.5) |
| ✅ | Modern framework | Symfony 8.1 |
| ✅ | Relational database | PostgreSQL 18 |
| ✅ | Queue | Messenger over the Doctrine transport |
| ✅ | Docker optional | Compose with `php`, `worker`, `database` |

## 3. Entities

| | Requirement | Implementation | Test |
|---|---|---|---|
| ✅ | Supplier, Property, Import, Offer, Reservation | attribute-mapped entities, migrations with foreign keys | — |
| ✅ | Fixtures for `supplier-a`, `supplier-b` | `SupplierFixtures`, idempotent on `--append` | — |
| ✅ | One property shared by different suppliers | `properties.code` globally unique | 🧪 `testItReusesPropertyAcrossSuppliers` |

`offers.price` is an integer in minor units (72500 = 725.00 EUR). `INT` rather than `BIGINT` on
purpose: Doctrine maps `BIGINT` to a PHP **string**, and 2.1 billion minor units is a ceiling no
nightly rate reaches.

`imports.payload` is `JSONB` and keeps the validated request body, so the message carries only an id
and a retry needs no new HTTP call.

## 4. Import — `POST /api/imports`

| | Requirement | Implementation | Test |
|---|---|---|---|
| ✅ | Validate payload and supplier | `CreateImportInput` + `#[MapRequestPayload]`, custom `SupplierExists` constraint | 🧪 `testItRejectsUnknownSupplier`, `testItRejectsCheckOutBeforeCheckIn` |
| ✅ | Create the import record | `ImportService::register()` | 🧪 `testItAcceptsImportAndQueuesProcessing` |
| ✅ | Queue the processing | `MessageBusInterface::dispatch()` | 🧪 `testItAcceptsImportAndQueuesProcessing` |
| ✅ | Return 202 immediately | `{"id":15,"status":"pending"}` | 🧪 `testItAcceptsImportAndQueuesProcessing` |

**Import rules**

| | Rule | Implementation | Test |
|---|---|---|---|
| ✅ | `supplier + external_import_id` unique | unique index on `imports` | 🧪 `testItDoesNotDuplicateImportOrRequeueProcessing` |
| ✅ | Repeat creates no duplicate, does not restart processing | lookup before insert, dispatch only on a real insert | 🧪 `testItDoesNotDuplicateImportOrRequeueProcessing` |
| ✅ | `supplier + offer.external_id` unique | unique index on `offers`; custom `UniqueOfferIds` constraint rejects duplicates inside one payload | 🧪 `testItRejectsDuplicatedOfferIdsWithinSinglePayload`, `testItUpdatesExistingOfferReceivedInAnotherImport` |
| ✅ | Offer from another import gets updated | `INSERT ... ON CONFLICT (supplier_id, external_id) DO UPDATE` | 🧪 `testItUpdatesExistingOfferReceivedInAnotherImport` |
| ✅ | `sent_at` stored | `imports.sent_at`, exposed by the status endpoint | 🧪 `testItReturnsCurrentStateOfTheImport` |
| ✅ | Property found or created by `property.code` | `INSERT ... ON CONFLICT (code) DO UPDATE ... RETURNING id, code` | 🧪 `testItCreatesPropertiesAndOffers`, `testItTreatsPropertyCodeCaseInsensitively` |
| ✅ | `completed` on success, `failed` on error | `ImportProcessor`; `failed` is set by `ImportFailureListener` once retries are exhausted | 🧪 `testItCreatesPropertiesAndOffers`, `testItMarksImportAsFailedWhenProcessingThrows` |
| ✅ | Processing in a worker, not in the HTTP request | `ProcessImportHandler` | 🧪 `testItAcceptsImportAndQueuesProcessing` — with the test transport the import stays `pending`, so nothing is processed in the request |
| ✅ | Statuses `pending`, `processing`, `completed`, `failed` | `ImportStatus` enum | 🧪 covered by the two rows above |
| ✅ | Timestamps with an offset are stored in UTC | normalised at the DTO boundary; columns are `TIMESTAMPTZ` | 🧪 `testItNormalizesTimezoneOffsetsToUtc` |

The handler claims the import atomically:
`UPDATE imports SET status='processing' WHERE id=? AND status IN ('pending','processing')`.
Zero affected rows means it is already done. `processing` stays in the condition on purpose —
otherwise a retry after a worker crash would never resume the import 🧪 `testItDoesNotProcessTheSameImportTwice`.

Offers are written in chunks of 500, each chunk in its own transaction, `processed_offers` updated as
it goes. Properties are deduplicated inside a chunk before the upsert: PostgreSQL refuses an
`ON CONFLICT DO UPDATE` that would touch the same row twice.

## 5. Import status — `GET /api/imports/{id}`

| | Requirement | Implementation | Test |
|---|---|---|---|
| ✅ | Returns current state of the async import | `ImportStatusView` | 🧪 `testItReturnsCurrentStateOfTheImport`, `testItReportsProgressOfACompletedImport` |
| ✅ | `total_offers`, `processed_offers`, `error`, `completed_at` | stored on `imports` | 🧪 `testItReportsProgressOfACompletedImport`, `testItExposesTheErrorOfAFailedImport` |
| ✅ | 404 for an unknown id | `#[MapEntity]` | 🧪 `testItReturnsNotFoundForUnknownImport` |

## 6. Search — `GET /api/properties`

| | Rule | Implementation | Test |
|---|---|---|---|
| ✅ | Dates match the search | equality on `check_in` / `check_out` | 🧪 `testItExcludesOffersForOtherDates` |
| ✅ | `max_guests >= guests` | in the subquery | 🧪 `testItExcludesOffersWithNotEnoughCapacity` |
| ✅ | `available_units > 0` | in the subquery | 🧪 `testItExcludesSoldOutOffers` |
| ✅ | `expires_at > now()` | in the subquery, compared in UTC | 🧪 `testItExcludesExpiredOffers`, `testItExcludesOffersThatExpiredInAnotherTimezone` |
| ✅ | City matches when provided | `EXISTS`, kept **inside** the subquery so the window is not computed across every city | 🧪 `testItFiltersByCity` |
| ✅ | Cheapest offer, ordering and pagination in the database | `ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price, id)` joined on `rn = 1` — no PHP collections | 🧪 `testItReturnsOnlyTheCheapestOfferForAProperty`, `testItOrdersPropertiesByBestOfferPrice` |
| ✅ | Response contains `next`, `prev`, `per_page` | `LIMIT per_page + 1` plus links built by the router | 🧪 `testItPaginatesAndKeepsFiltersInLinks` |

Indexes: `offers(check_in, check_out, max_guests, price)`, `offers(property_id, price)`,
`properties(city)`.

Ordering is two-level (`offers.id` in the window, `properties.id` outside) — without it pagination
drifts when prices tie. The next page is detected by fetching one row more than requested rather than
by a second `COUNT(*)` query.

## 7. Reservation — `POST /api/offers/{id}/reservations`

| | Requirement | Implementation | Test |
|---|---|---|---|
| ✅ | 201 with the created reservation | `ReservationView` | 🧪 `testItCreatesReservationAndDecrementsAvailableUnits` |
| ✅ | 409 when sold out or expired | `OfferUnavailableException` | 🧪 `testItRejectsReservationWhenNoUnitsLeft`, `testItRejectsReservationForExpiredOffer` |
| ✅ | Protection against two simultaneous bookings of the last unit | pessimistic lock, explained below | 🧪 `testItBooksTheLastUnitOnlyOnce` |

`ReservationService::reserve()` opens a transaction and calls
`EntityManager::refresh($offer, LockMode::PESSIMISTIC_WRITE)`.

`refresh()` rather than `find()` is the point. `find($id, LockMode::PESSIMISTIC_WRITE)` on an entity
that is already managed — and it is, because `#[MapEntity]` loaded it while resolving the route —
issues the `SELECT ... FOR UPDATE` but leaves the in-memory object untouched. The lock would be real
and the `available_units` behind it stale. `refresh()` locks the row *and* re-reads it, so every
check runs on post-lock data. Without the lock both requests read `available_units = 1` and both
write `0`.

A lock-free atomic `UPDATE ... WHERE available_units > 0` with an affected-rows check was considered:
cheaper under contention, but it cannot tell "sold out" from "expired" and fits worse with creating
the reservation in the same transaction.

Client retries are a separate problem, solved by a unique `(offer_id, client_reference)` and a lookup
performed **inside** the lock: a repeat returns the existing reservation with `200` and the counter is
never touched 🧪 `testItDoesNotBookTwiceForTheSameClientReference`. The index is scoped to the offer
on purpose — a globally unique `client_reference` would let a booking attempt on one offer return a
reservation made for another 🧪 `testItDoesNotReturnReservationMadeForAnotherOffer`.

---

## 8. Design decisions

**Request and response bodies are DTOs.** Incoming payloads are denormalised into `final readonly`
objects by `#[MapRequestPayload]` and `#[MapQueryString]`, which wire up the Serializer and the
Validator for you; outgoing bodies are separate view objects. No Doctrine entity ever crosses the
HTTP boundary in either direction, so the API contract cannot drift with the schema. A global
camelCase-to-snake_case name converter keeps PHP properties idiomatic and the JSON conventional.

**Bulk writes bypass the ORM.** Doctrine has no upsert, and an import of a thousand offers has no use
for an identity map. Properties and offers are written with native
`INSERT ... ON CONFLICT ... DO UPDATE` through the DBAL connection, in chunks of 500, each chunk in
its own transaction. Everything else — reads, single writes, the reservation flow — goes through the
ORM, where the unit of work earns its keep.

**Errors follow RFC 7807.** Responses are `application/problem+json` with `type` / `title` / `status`
/ `detail`, and validation failures add a `violations` array. Field paths are converted back to
snake_case, so a client that sent `check_out` reads `check_out` in the error rather than the internal
`checkOut`. 5xx responses are left to Symfony — the listener never invents a body for them, so
internals cannot leak.

**Validation errors arrive in two rounds.** When a constructor argument of a request DTO is missing
or cannot be coerced to its type, the argument resolver collects denormalization errors and never
reaches the constraints. A search without `guests` reports only `guests`; the `check_out > check_in`
violation surfaces on the next request. That is how the resolver works, not an oversight, and the
test asserts both rounds so it stays visible.

**Case-insensitive property codes are explicit.** PostgreSQL compares text case-sensitively, and a
`UNIQUE (lower(code))` expression index cannot be expressed in ORM metadata — every future
`make:migration` would try to drop it. Codes are therefore normalised to upper case at the DTO
boundary and stored that way, which keeps the schema and the mapping in sync.

**The queue runs on PostgreSQL.** `doctrine://default` is what the Messenger recipe configures by
default, it needs no extra service, and the transport uses `SELECT ... FOR UPDATE SKIP LOCKED`. The
`messenger_messages` table comes from a migration rather than `auto_setup` — DDL at runtime has no
place in production.

**Collections are enveloped, single resources are not.** `GET /api/properties` returns
`{data, links, meta}` because pagination links need somewhere to live; `POST /api/imports` and
`GET /api/imports/{id}` return the object itself.

**No `declare(strict_types=1)`.** Symfony does not use it in its own code, and the `@Symfony:risky`
rule set actively strips it.

---

## Tests

```bash
docker compose exec php bin/phpunit
```

**`ImportSubmissionTest`** — the HTTP layer of the import.

| Test | Guarantees |
|---|---|
| `testItAcceptsImportAndQueuesProcessing` | 202 with `{id, status}`, record persisted as `pending`, exactly one message queued |
| `testItDoesNotDuplicateImportOrRequeueProcessing` | second identical request returns the same id, no second row, no second message |
| `testItRejectsUnknownSupplier` | 422 on a supplier that does not exist |
| `testItRejectsDuplicatedOfferIdsWithinSinglePayload` | 422 instead of a non-deterministic upsert |
| `testItRejectsCheckOutBeforeCheckIn` | 422 per offer, dates validated within their own offer |

**`ImportProcessingTest`** — the message handler.

| Test | Guarantees |
|---|---|
| `testItCreatesPropertiesAndOffers` | property and offer written, import `completed`, `processed_offers` matches `total_offers` |
| `testItDoesNotProcessTheSameImportTwice` | the claim makes a second run a no-op: `completed_at` unchanged, no duplicate rows |
| `testItUpdatesExistingOfferReceivedInAnotherImport` | one offer with the new price, no duplicate property |
| `testItReusesPropertyAcrossSuppliers` | one property, two offers from different suppliers |
| `testItTreatsPropertyCodeCaseInsensitively` | `BCN-0001` and `bcn-0001` resolve to one property |
| `testItNormalizesTimezoneOffsetsToUtc` | `+03:00` in the payload is stored as UTC in both `imports.sent_at` and `offers.expires_at` |
| `testItMarksImportAsFailedWhenProcessingThrows` | status `failed` with the error stored once retries are exhausted |

**`ImportStatusTest`** — the status endpoint.

| Test | Guarantees |
|---|---|
| `testItReturnsCurrentStateOfTheImport` | full payload with counters and dates in the format from the task |
| `testItReportsProgressOfACompletedImport` | counters and `completed_at` reflect a finished run |
| `testItExposesTheErrorOfAFailedImport` | a failed import carries its error message |
| `testItReturnsNotFoundForUnknownImport` | 404 as `problem+json`, without leaking the entity class |

**`PropertySearchTest`** — every selection rule.

| Test | Guarantees |
|---|---|
| `testItReturnsOnlyTheCheapestOfferForAProperty` | one row per property, `best_offer` is the cheapest one, supplier code included |
| `testItExcludesSoldOutOffers` | `available_units = 0` drops out of the selection entirely |
| `testItExcludesExpiredOffers` | `expires_at` in the past drops out |
| `testItExcludesOffersThatExpiredInAnotherTimezone` | an offer whose wall-clock time looks future but whose instant is past stays out |
| `testItExcludesOffersWithNotEnoughCapacity` | `max_guests < guests` drops out |
| `testItExcludesOffersForOtherDates` | dates are matched exactly |
| `testItFiltersByCity` | only the requested city is returned |
| `testItOrdersPropertiesByBestOfferPrice` | results sorted by best offer price ascending |
| `testItPaginatesAndKeepsFiltersInLinks` | `per_page` respected, `prev` empty on the first page and present on the second, both links carry the filters |
| `testItValidatesSearchParameters` | 422 on missing and on contradictory parameters |

**`ReservationTest`** — booking and concurrency.

| Test | Guarantees |
|---|---|
| `testItCreatesReservationAndDecrementsAvailableUnits` | 201, reservation stored, counter decremented by one |
| `testItRejectsReservationWhenNoUnitsLeft` | 409, no reservation created |
| `testItRejectsReservationForExpiredOffer` | 409 with a distinct message |
| `testItDoesNotBookTwiceForTheSameClientReference` | repeat returns 200 with the same reservation, counter untouched |
| `testItDoesNotReturnReservationMadeForAnotherOffer` | the same `client_reference` on a different offer creates its own reservation |
| `testItBooksTheLastUnitOnlyOnce` | the last unit produces exactly one reservation, the next attempt gets 409, counter never goes negative |
| `testItReturnsNotFoundForUnknownOffer` | 404 via `#[MapEntity]` |
| `testItValidatesReservationPayload` | 422 listing every missing field |

---

## Assumptions

- Dates are matched exactly, as the task states.
- Prices are compared without currency conversion.
- `available_units` is overwritten by supplier data on update — the supplier is the source of truth.
- Repeating an `external_import_id` with a changed body ignores the new body: the import is
  idempotent by its identifier, not by its content.
- A failed import keeps the chunks it already wrote: reprocessing is idempotent, so a retry converges
  instead of rolling everything back.
- Between retries the import stays in `processing` — it is still in flight. `failed` is set only after
  all attempts are exhausted.
- Replaying a `client_reference` returns the existing reservation even if the offer has since expired.
  A repeat is a replay of a completed booking, not a new attempt to buy.
