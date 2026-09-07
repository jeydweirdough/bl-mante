# Bel Mante Hotel — reservation and management system

A single-property hotel system where rooms are sold as **time intervals**, not calendar
nights. A guest picks a start hour and a package of 3, 6, 12 or 22 hours; a specific
physical room is assigned at booking; every stay is followed by a turnover buffer before
the room is offered again.

One monolithic Laravel application serving public visitors, registered customers, front
desk staff and administrators. Server-rendered Blade with Alpine for the interactive
pieces. No separate API, no separate frontend.

---

## Running it locally

Requires PHP 8.2+ with `pdo_sqlite`, `zip`, `mbstring`, `openssl` and `intl`, plus
Composer and Node.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed     # creates database/database.sqlite and fills it
npm run build                  # or: npm run dev

php artisan serve
```

Then sign in at `/login` with any of:

| Role       | Email                     | Password   |
|------------|---------------------------|------------|
| Admin      | `admin@belmante.test`     | `password` |
| Front desk | `frontdesk@belmante.test` | `password` |
| Customer   | `customer@belmante.test`  | `password` |

The seeder builds a property that can be used immediately: 4 room types, 18 physical
rooms, the four packages priced per type, 7 extras (one deliberately disabled), 6 staff
accounts (one deactivated), 25 customers, and roughly 900 reservations spread across the
three weeks either side of today — past, in-house and future — plus a few unpaid holds
(two already lapsed) and one extension waiting on the desk.

To see the scheduled work happen, run `php artisan schedule:work`, or invoke the commands
directly:

```bash
php artisan reservations:expire-holds --dry-run
php artisan reservations:mark-no-shows --dry-run
php artisan rooms:sync-states
```

### Tests

```bash
php artisan test        # 130 tests
vendor/bin/pint --test  # code style
```

---

## The parts worth reading first

### The overlap guarantee

Two reservations may never overlap on the same physical room. Three layers enforce it,
and the reasoning is written out in full in
[`ReservationService::book()`](app/Services/ReservationService.php).

1. **A row lock on the room** — the actual mutex. Locking conflicting *reservations*
   would not work: when a room is free there are no rows to lock, so two transactions
   could both find it empty and both insert. The room row always exists, so the second
   transaction blocks until the first commits.
2. **The availability re-check**, run inside the same transaction as the insert, after
   the lock is held. The check is never run outside that transaction — search results
   are a hint, re-verified at confirmation.
3. **The database's own rule**, in
   [the overlap migration](database/migrations/2026_01_01_001200_add_reservation_overlap_guarantee.php):
   a PostgreSQL `EXCLUDE USING gist` constraint over `(room_id, tsrange(starts_at,
   blocked_until))` with `btree_gist`, and equivalent `BEFORE INSERT/UPDATE` triggers on
   SQLite so local development and the test suite exercise the same failure path.

A violation at any layer surfaces as the same friendly *"that room was taken while you
were booking"* message, never an error page.

Three details must stay in lockstep across all three layers, and are commented as such:
the range is over `[starts_at, blocked_until)` (stay **plus** buffer); it is half-open, so
a stay may begin the instant a buffer expires; and only `pending`, `confirmed` and
`checked_in` participate — `ReservationStatus::occupying()` is the single definition, and
the migration builds its SQL from it.

### The turnover buffer

Each reservation stores `blocked_until` as a real column (`ends_at + buffer_minutes`) and
its own `buffer_minutes` snapshot. Two consequences:

- The exclusion constraint can range over actual columns rather than an expression the
  application would have to keep in sync separately.
- Changing the buffer today cannot retroactively move an existing booking's block.

### The customer duplicate rule

A customer may hold several bookings on one day, but not two whose intervals overlap. The
check compares **stay** intervals, not blocked intervals — the buffer belongs to the room,
not the guest, so booking 09:00–12:00 in one room and 13:00–16:00 in another is legal even
though the first room stays blocked until 13:00. The refusal names the conflicting
booking, as required.

Walk-in guests have no account, so there is no identity to compare; the desk sees an
advisory name/phone match instead of a hard block.

### Policy versioning

Every figure a guest agrees to — turnover buffer, unpaid hold, downpayment percentage,
cancellation tiers, no-show grace, reschedule window, tax, fees — lives in
`policy_versions`. Publishing a change **inserts a new version** and moves the current
flag; it never updates a row. Each reservation stores the version it was made under, which
is how *"the policy applied is the one in force when the booking was made"* is enforced
structurally rather than by convention.

Prices, extras and extension rates are snapshotted onto the reservation for the same
reason. An admin can re-price a room type or disable an extra without touching a single
existing booking.

### The payment boundary

Online payment is mocked but sits behind [`App\Payments\PaymentGateway`](app/Payments/PaymentGateway.php)
— four methods, resolved from `config/hotel.php` by
[`PaymentServiceProvider`](app/Providers/PaymentServiceProvider.php). No controller, model
or service names a provider.

Adding a real one means writing a class that implements that interface and changing
`HOTEL_PAYMENT_DRIVER`. Nothing else moves.

The flow assumes redirect-style checkout, so card data never reaches this system. The
stand-in hosted page at `/checkout-simulator/{ref}` is registered *only* while the mock is
the configured driver. Settlement is always confirmed by asking the provider — never by
the URL the guest returns on.

Face-to-face payments never touch the gateway. They are recorded directly and attributed
to the staff member who took them; `PaymentService` requires the staff member, not just
the form.

### The public site

Four pages, all reachable without an account:

| Page | What it does |
|---|---|
| **Home** (`/`) | The property's website — ~1,800 words across an intro, differentiators, how the hourly model works, rooms, amenities, neighbourhood and a 12-question FAQ. |
| **About** (`/about`) | Why the property sells hours rather than nights, how turnover works, and what happens when plans change. |
| **Rooms** (`/rooms`) | The inventory *and* live availability — see below. |
| **Contact** (`/contact`) | Phone, email, address, map link, and a working message form. |

#### Rooms: availability, and when you *can* book

The rooms page answers two questions at once — what the rooms are, and whether you can
have one. Every room type shows one of four states for the requested window:

- **Free** — how many rooms, with a booking link.
- **Nearly gone** — two or fewer left, said plainly, because it is true.
- **Full now, free later** — *"Fully booked at 12:00 · Next free today at 17:00 · that is 5
  hours later"*, with buttons to jump to that window or book it directly.
- **Full for the whole horizon** — a phone number and the contact form, rather than a shrug.

The third state is the point. "Fully booked" on its own is a dead end that loses the
visitor; the next free hour gives them something to click, and following the suggestion
lands on a window that genuinely has a room.

[`AvailabilityService::overview()`](app/Services/AvailabilityService.php) does this in a
**fixed number of queries** — the whole lookahead of reservations is pulled once and the
hour-by-hour scan happens in memory. Probing the database for each hour across a fortnight
would be thousands of round trips to render one page; there is a test asserting the query
count stays flat.

Horizon is `HOTEL_ROOMS_LOOKAHEAD_DAYS` (default 14). A start time in the past is pulled
forward to the next bookable hour rather than rejected, so a stale link still shows a
useful page.

#### Contact

Messages are **stored, not emailed**. This system has no mail transport configured, and a
form whose only copy goes to an SMTP server loses the message when that server is
unreachable — a guest believing they have been in touch when they have not is worse than
no form. The front desk reads them at `/staff/enquiries`, with an unread badge in the
navigation, and a quoted booking reference is resolved to the actual booking.

Two invisible anti-spam measures, neither of which asks a guest to prove anything: a
honeypot field and a minimum time-on-page, plus rate limiting on the route. Deliberately
not a CAPTCHA — it would tax every legitimate enquiry to stop spam these already handle.

### SEO

The homepage is the property's website, not just a booking widget: around 1,800 words of
real copy across an intro, differentiators, how the hourly model works, rooms, amenities,
neighbourhood and a twelve-question FAQ. A page that is only a search form gives a visitor
no reason to trust the place and gives a search engine nothing to rank.

**Copy lives in [`config/content.php`](config/content.php)**, not in Blade, so it can be
rewritten without touching markup — and because the FAQ feeds both the visible accordion
and the `FAQPage` structured data from one array.

**Policy figures in the copy are interpolated, not typed.** Any `:token` in that file is
replaced from the *live* policy version by [`SiteContent`](app/Support/SiteContent.php). An
admin who changes the refund window changes the homepage with it, so the marketing copy can
never promise terms the booking engine will refuse. There is a test for exactly this.

What is in place:

| | |
|---|---|
| **Meta** | One `<x-seo>` component: unique title, 150–160 char description, self-referencing canonical, robots directives. No page can ship without them. |
| **Social** | Open Graph and Twitter cards, with a real PNG share card generated by `php artisan hotel:social-card`. |
| **Structured data** | `Hotel` (address, geo, amenities, offers, 24-hour reception), `WebSite`, `FAQPage`, `HotelRoom` and `BreadcrumbList` per room page. |
| **Crawl control** | Generated `/robots.txt` and `/sitemap.xml` — a new room type publishes itself, and private areas are excluded from both. |
| **Index hygiene** | Availability *results* are `noindex` and canonicalise back to the bare search page; anything behind a login is `noindex` by default. |
| **Semantics** | Exactly one `<h1>` per page, ordered headings, native `<details>` FAQ so answers are in the HTML without JavaScript, lazy-loaded images with real `alt` text. |

**Deliberately absent: `aggregateRating` and `review` markup.** Publishing ratings for
reviews the site does not collect and display is a Google policy violation that risks a
manual penalty, and it would be a fabricated claim about real guests. `starRating` is
emitted only if one is actually configured. `SeoTest::test_no_review_or_rating_markup_is_published`
fails if anyone adds them.

> **The content is placeholder.** It is structurally correct and plausible, not factual.
> Before this goes near a search engine, replace the address, phone and coordinates in
> `config/hotel.php` and the neighbourhood distances, amenity claims and photographs in
> `config/content.php` with verified facts. Invented specifics mislead guests, and a name,
> address or phone that disagrees with the Google Business Profile is the most common reason
> a hotel fails to rank locally.

### Choice architecture (Hick's Law)

Hick's Law says decision time rises with the number of options, so the interfaces are
built to keep the number of *simultaneous* choices small — without removing capability.
Four techniques, applied where the count was actually high:

**1. Defaults that need no decision.** Every search arrives pre-filled with the next
bookable hour and a sensible package, so a visitor who just wants to look can press one
button. A start time in the past is pulled forward rather than rejected.

**2. Presets for the common intents.** The time picker
([`x-when-picker`](resources/views/components/when-picker.blade.php)) leads with *Next
hour · Tonight · Tomorrow AM · Tomorrow PM*. Most people want one of those four, and for
them a 24-option decision collapses to a 4-option one.

**3. Categorisation when the list stays long.** The full hour dropdown is grouped into
Overnight / Morning / Afternoon / Evening. The count is unchanged; the scan is not,
because three quarters can be discarded at a glance. The admin menu's seven items are
grouped the same way, under *What we sell* and *Running the place*.

**4. Progressive disclosure for the genuinely optional.**
[`x-disclosure`](resources/views/components/disclosure.blade.php) is a native `<details>`,
so it works without JavaScript and is keyboard- and screen-reader-correct for free.

What changed, concretely:

| Screen | Before | After |
|---|---|---|
| Booking search | 6 questions at once (date, 1-of-24 hours, package, adults, children, room type) | 2 visible — when, how long. Guests and room type collapsed, with their values shown in the summary line. |
| Booking extras | 7 dropdowns × 6 options = 42 | 7 yes/no checkboxes; quantity appears only once one is ticked. |
| Contact form | 6 fields, 4 of them optional | 3 visible. The rest collapse, and auto-open if a validation error lands in them. |
| Staff booking screen | 8 actions of equal weight | 1 promoted by state (check in / check out / take payment), the rest behind *Other actions*. |
| Navigation | 8+ links for staff | Role-split: guests see the 4 public pages, staff see their 4 work screens. |

The promoted staff action comes from
[`Reservation::primaryDeskAction()`](app/Models/Reservation.php) — derived from the state
machine rather than guessed, so it cannot disagree with what the booking will actually
allow.

**Where it was deliberately not applied:** the homepage prose and FAQ. Hick's Law governs
*choices*, not reading — cutting content there would cost SEO and answer nothing a visitor
is deciding between.

### Room state vs. availability

`rooms.status` is the **present-tense housekeeping state** of a physical room:
`available → reserved → occupied → cleaning → available`. It is *not* the source of truth
for whether a future window is free — that is derived entirely from the reservations
table. A room occupied right now is still bookable for next Tuesday.

Conflating these is the usual way systems like this grow overbooking bugs, so the two are
kept apart deliberately, and `rooms:sync-states` recomputes the present state from facts
rather than relying on every code path to tidy up.

---

## Layout

```
app/
  Enums/            ReservationStatus, RoomStatus, PricingBasis, RefundTier, ...
                    ReservationStatus and RoomStatus carry their own state machines.
  Exceptions/       DomainRuleException and its subclasses — refusals a person can act
                    on, rendered as a message rather than an error page.
  Payments/         The gateway interface, its DTOs, and Gateways/MockPaymentGateway.
  Policies/         All authorisation. Controllers call authorize(); none inspect roles.
  Services/         AvailabilityService   the overlap rule, in one place
                    ReservationService    booking, cancel, reschedule, extend, check-in
                    ReservationStateMachine / RoomStateService   the two state machines
                    PricingService        quotes and re-pricing
                    RefundCalculator      the cancellation tiers
                    PaymentService        both payment paths and refunds
                    PolicyService         reads and publishes policy versions
                    AuditLogger           the catch-all trail
  Support/          BookingWindow, Quote, BookingRequest, Money — value objects.
database/
  migrations/       Full schema, portable across SQLite and PostgreSQL.
  seeders/          CatalogueSeeder, AccountSeeder, ReservationSeeder.
docs/               01-entity-relationship-model.md
tests/Feature/      Availability, concurrency, duplicates, refunds, extensions,
                    scheduled sweeps, payments, authorisation, end-to-end journeys.
```

---

## Database

SQLite locally, PostgreSQL in production. Every migration runs on both; where the two
engines genuinely differ, both forms are provided side by side in the same file rather
than one being assumed.

SQLite is configured in `config/database.php` with:

- **WAL journalling**, so browsing the availability grid does not block a writer.
- **`IMMEDIATE` transactions**, not Laravel's default `DEFERRED`. The booking transaction
  reads then writes; under `DEFERRED`, SQLite takes the write lock only at the first
  write, so two such transactions can both pass the read and then fail to upgrade.
  `IMMEDIATE` takes the lock at `BEGIN`, serialising the whole check-and-insert.

The database file is not committed.

### Switching to PostgreSQL

Set the `DB_*` block in `.env` (the commented template is already there) and run
`php artisan migrate`. The overlap migration will create the `btree_gist` extension and
add the real exclusion constraint plus the `CHECK` constraints, which SQLite receives as
triggers.

> **Not yet exercised:** the PostgreSQL path is written but has only been run against
> SQLite here, as no Postgres instance was available. The `btree_gist` extension also
> needs privileges the application role may not have on a managed instance.

### Timezone

The application clock is the property's clock (`APP_TIMEZONE`), so a stored datetime is a
wall-clock time at the hotel. This keeps the "starts on the hour" constraint true and
removes a conversion from every screen and query. The trade-off, noted in `config/app.php`:
a property in a DST zone would see one ambiguous and one non-existent hour per year.
Asia/Manila has none.

---

## Decisions worth challenging

1. **Walk-ins get no user account.** `reservations.user_id` is nullable with guest contact
   details inline. Auto-provisioning a shell account for every phone booking would pollute
   the customer list and give the duplicate check an identity that means nothing. The
   consequence is that the duplicate rule covers account holders only.

2. **The unpaid hold applies only to the online path.** An at-property booking has no
   payment by definition, so the 30-minute hold taken literally would expire every one of
   them. Those are confirmed immediately and protected by the no-show grace period instead.

3. **No scheduled maintenance blocks.** A room can only be taken out of service
   immediately and open-endedly. "Room 304 is out next Tuesday" needs a new entity that
   participates in the same overlap logic and the same exclusion constraint.

4. **Money roll-ups are denormalised onto `reservations`.** `payments` and `refunds` remain
   the ledger of record; the roll-ups exist so the daily board and reports do not aggregate
   on every render, and are recomputed inside the same transaction as the ledger row.

5. **Tax and fees are two scalars on the policy version**, not a `tax_rules` table.
   Adequate for one property in one jurisdiction; multiple named tax lines would need a
   table plus `reservation_tax_lines`.

6. **Nothing is ever deleted.** Catalogue entries deactivate, users soft-delete,
   reservations and payments are permanent. Attribution on historical records has to keep
   resolving.
