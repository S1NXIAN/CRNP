<p align="center">
  <img src="assets/img/logo.png" alt="CRATES N' PLATES logo" width="140">
</p>

<h1 align="center">CRATES N' PLATES — Online Management System</h1>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/Database-Firebase%20RTDB-FFCA28?logo=firebase&logoColor=black" alt="Firebase RTDB">
  <img src="https://img.shields.io/badge/Hosting-Render-46E3B7" alt="Render">
</p>

<p align="center">
  <a href="https://render.com/deploy?repo=https://github.com/S1NXIAN/CRNP">
    <img src="https://render.com/images/deploy-to-render-button.svg" alt="Deploy to Render" height="32">
  </a>
</p>

One web application that runs **Crates N' Plates Diner** online and in-store: customers order food ahead or book rental items from their phones, cashiers run the counter and verify GCash payments, kitchen staff work from a live order display, and the owner manages the menu, inventory, staff accounts, business settings, and sales reports — all from a browser, on any device.

There is nothing to install for end users. Everyone uses the same responsive web app through a link.

---

## Table of Contents

1. [Features](#features)
2. [Technology](#technology)
3. [Deploying to Render](#deploying-to-render)
4. [First-Run Setup](#first-run-setup)
5. [Configuration Reference](#configuration-reference)
6. [Local Development](#local-development)
7. [Maintenance & Security](#maintenance--security)
8. [Troubleshooting](#troubleshooting)
9. [Project Structure](#project-structure)

## Features

**For customers**
- Browse the menu with photos and search; add items to a cart and check out
- Pay via GCash or at the counter; receive email receipts
- Reserve rental equipment with date/time bookings
- Track order status live (pending → preparing → ready → completed) and review history

**For cashiers**
- Point-of-sale console for walk-in orders
- Approve/reject rental bookings, verify GCash proof-of-payment photos, mark items returned
- Printable receipts and an archive of completed/cancelled orders

**For kitchen staff**
- Live kitchen display showing incoming orders
- Forward-only status workflow (accept → preparing → ready) so orders can't skip steps

**For the administrator**
- Dashboard with KPIs, 7-day sales trend, peak-hour chart, top products
- Menu (product) and rental inventory management
- Staff account management for cashier and kitchen roles
- Editable business settings: hours, GCash number & QR code, hero content, About page
- Sales reports with calendar date filtering, plus full order/booking archives

### Permissions Matrix

| Feature | Customer | Cashier | Kitchen | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse menu / order ahead / rent equipment | ✅ | — | — | — |
| Cart + checkout (GCash or pay at counter) | ✅ | — | — | — |
| Book rental items & cancel pending bookings | ✅ | — | — | — |
| Track own orders / view receipts | ✅ | — | — | — |
| Edit own profile + upload avatar | ✅ | — | — | — |
| Order console (accept → preparing → ready) | — | ✅ | ✅ | — |
| Walk-in POS orders | — | ✅ | — | — |
| Approve/reject bookings, verify GCash, mark returned | — | ✅ | — | — |
| Manual walk-in rental bookings | — | ✅ | — | — |
| Print / reprint receipts | — | ✅ | — | — |
| Kitchen display + status workflow | — | — | ✅ | — |
| Product & rental inventory CRUD | — | — | — | ✅ |
| Dashboard analytics + sales reports | — | — | — | ✅ |
| Order & booking history (search / date filter) | — | ✅¹ | ✅¹ | ✅ |
| Staff account management | — | — | — | ✅ |
| Business settings (CMS) | — | — | — | ✅ |

¹ Cashier/Kitchen history pages are read-only archives of their own workflow.

Access is enforced per page by role guards (`includes/auth.php`). Each role uses its own session cookie (`SESS_USER`, `SESS_CASHIER`, `SESS_KITCHEN`, `SESS_ADMIN`), so different roles can be signed in side-by-side in one browser.

## Technology

| Layer | Technology |
|---|---|
| Frontend | Server-rendered PHP pages, vanilla HTML/CSS/JS, mobile-first |
| Backend | PHP 8.2 (procedural pages + lightweight OOP models) |
| Database | Firebase Realtime Database via its REST API (`firebaseRDB.php`) |
| Auth | Email + password with OTP verification, bcrypt hashing |
| Email | Gmail API over HTTPS |
| Hosting | Render (Docker runtime, `php:8.2-apache`) |

No build step, no separate API server: PHP serves both the UI and the data layer, which talks to Firebase over REST. A file-based cache keeps dashboard reads fast.

## Deploying to Render

The repository ships with two files that make deployment nearly automatic:

- **`render.yaml`** — Render Blueprint describing the web service (free plan, health checks, environment variables)
- **`Dockerfile`** — packages the PHP app with Apache

> **Note (free plan).** The free instance sleeps after ~15 minutes idle; a cron keepalive inside the container pings `/health.php` every 14 minutes to hold it warm, costing ~730 of the 750 monthly free hours. Uploaded images (avatars, GCash proofs) are stored on the instance's ephemeral disk and are lost on every redeploy/restart. Upgrade the service plan and attach a disk mounted at `/var/www/html/uploads` when the restaurant goes live for real.

### Step-by-step

1. **Push this repository to GitHub.**

2. **Create the Blueprint.** Render Dashboard → **New → Blueprint** → select the repo → **Apply**. Render reads `render.yaml` and prompts for the secret variables marked `sync: false`.

3. **Fill in the environment variables** when prompted (see table below):
   - `FIREBASE_DATABASE_URL` — copy **verbatim** from Firebase Console → Realtime Database (regional `*.firebasedatabase.app` URL)
   - `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GMAIL_REFRESH_TOKEN` — Gmail API mail over HTTPS (section below; required on free plan, where outbound SMTP is blocked)
   - `GMAIL_ADDRESS` — Gmail account sending the mail
   - `MAIL_FROM` — optional; defaults to `GMAIL_ADDRESS`
   - `FIREBASE_SERVICE_ACCOUNT_JSON` — full service-account key (step 4)

4. **Create the Firebase service-account key** (one time):
   - Firebase Console → ⚙️ **Project settings** → **Service accounts**
   - **Generate new private key** → a JSON file downloads
   - Open it, copy *everything* (including the outer `{ }`), paste into Render's `FIREBASE_SERVICE_ACCOUNT_JSON`

5. **Lock down the database.** Firebase Console → **Realtime Database → Rules** → replace with the contents of `database.rules.json` (auth-locked rules plus the `.indexOn` entries every list page queries with `orderBy`).

   Publish **after** step 4 is complete, otherwise the server loses database access. Keep the file and the Console copy in sync — adding a new `Model::where()` field means adding its `.indexOn` here too.
   If the Console rules still use time-boxed open access (`now < ...`) because step 4 is not done, do **not** paste the file wholesale: keep the existing `.read` / `.write` lines and append only the per-node `.indexOn` blocks. Replacing open rules with `auth != null` before the server authenticates cuts off all database access, and every indexed query (`Model::where`) silently returns empty — list pages render as if there were no rows. Time-boxed rules also stop the app dead on expiry; extend or lock down before the date.
   Pre-lockdown copy-paste (open access plus indexes — keep your own `.read` / `.write` lines if they differ):
   ```json
   {
     "rules": {
       ".read": "now < 1791302400000",
       ".write": "now < 1791302400000",
       "user": {
         ".indexOn": ["email"]
       },
       "admins": {
         ".indexOn": ["email"]
       },
       "cashiers": {
         ".indexOn": ["email"]
       },
       "kitchen": {
         ".indexOn": ["email"]
       },
       "orders": {
         ".indexOn": ["user_email", "status", "created_at", "payment_status"]
       },
       "bookings": {
         ".indexOn": ["user_email", "status", "created_at", "payment_status"]
       },
       "products": {
         ".indexOn": ["name", "status", "category"]
       },
       "rent_items": {
         ".indexOn": ["name", "status"]
       }
     }
   }
   ```

> **Regional URL warning.** Databases created outside US-central live on a `*.firebasedatabase.app` domain. Always copy the URL shown above your data tree in Firebase Console — pointing at a `.firebaseio.com` address makes every request fail with *"Database lives in a different region."*

### Gmail API mail (required on Render free)
Outbound SMTP (ports 25/465/587) is blocked on the free plan, so mail goes through the Gmail API over HTTPS. One-time setup:

1. **Enable the API.** Google Cloud Console → new or existing project → **APIs & Services → Library** → enable **Gmail API**.
2. **OAuth consent screen.** **APIs & Services → OAuth consent screen** → **External** → app name + your Gmail as support/developer contact → add your Gmail as a **test user**. Keep the `gmail.send` scope (narrowest that sends).
3. **OAuth client.** **Credentials → Create Credentials → OAuth client ID** → **Desktop app** → note the client ID and secret → set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` in Render env.
4. **Consent once.** Open (replace `<CLIENT_ID>`), approve as your Gmail, copy the `code=` from the redirect URL:
   `https://accounts.google.com/o/oauth2/v2/auth?client_id=<CLIENT_ID>&redirect_uri=http://localhost&response_type=code&scope=https://www.googleapis.com/auth/gmail.send&access_type=offline&prompt=consent`
5. **Exchange the code** for a refresh token (run locally, replace the three placeholders `<CODE>`, `<CLIENT_ID>`, `<SECRET>`):
   `curl -s -X POST https://oauth2.googleapis.com/token -d code=<CODE> -d client_id=<CLIENT_ID> -d client_secret=<SECRET> -d redirect_uri=http://localhost -d grant_type=authorization_code`
   → set `GMAIL_REFRESH_TOKEN` from the response. The app mints access tokens itself from here on.

> Test-mode refresh tokens expire after 7 days; publish the consent screen to **Production** (unverified-app warning on first consent is normal for personal use) or re-consent weekly.

## First-Run Setup

After the first successful deploy:

1. Open `https://YOUR-SERVICE.onrender.com/admin/signup.php` and create the first administrator.
   The page disables itself once an admin exists — then **delete `admin/signup.php` from the server/repo** as good practice.
2. Sign in as admin → **Staff** → create cashier and kitchen accounts.
3. **Settings** → fill in business info, opening hours, GCash number and QR image.
4. **Products / Rent Items** → populate the menu and rental inventory.
5. Customer self-service: `/user/signup.php` (email + OTP verification).

## Configuration Reference

All configuration is environment-based — nothing sensitive is stored in code.

| Variable | Required | Description |
|---|:---:|---|
| `FIREBASE_DATABASE_URL` | yes | RTDB URL, verbatim from Firebase Console |
| `GMAIL_ADDRESS` | yes | Gmail account sending OTP/receipt mail |
| `GOOGLE_CLIENT_ID` | prod | OAuth client ID; enables Gmail API mail over HTTPS (SMTP is blocked on Render free) |
| `GOOGLE_CLIENT_SECRET` | prod | OAuth client secret |
| `GMAIL_REFRESH_TOKEN` | prod | `gmail.send` consent exchanged once; mints access tokens automatically |
| `MAIL_FROM` | no | From: override; defaults to `GMAIL_ADDRESS` |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | prod | Full service-account JSON; required once rules need `auth != null` |
| `DEV_SHOW_OTP` | no | `1` prints OTPs on screen when mail fails; dev only |

On Render these live in the service's **Environment** tab; locally in `.env` (git-ignored). Keep the JSON on one line in `.env`.

## Local Development

Requirements: PHP 8.2 with `curl` and `fileinfo` extensions, Apache (e.g. XAMPP), a Firebase project, a Gmail account.

1. Point Apache's DocumentRoot **at this folder** — internal links assume the app is served from `/`.
2. Create `.env` from the template (`cp .env.example .env`) and fill in real values:
   ```ini
   FIREBASE_DATABASE_URL="https://your-db-default-rtdb.asia-southeast1.firebasedatabase.app"
   GMAIL_ADDRESS="your@gmail.com"
   MAIL_FROM="your@gmail.com"
   DEV_SHOW_OTP="0"
   ```
3. Start Apache and open the site. No build step, no migrations.

## Maintenance & Security

| Cadence | Task | How |
|---|---|---|
| Immediately if leaked | Rotate any exposed credential | Google Account → Security → Third-party access → revoke, re-consent (§ Gmail API mail), update `GMAIL_REFRESH_TOKEN`; or Firebase Console → Service accounts → Keys → delete old, create new, update Render env var |
| Quarterly | Rotate service-account key | Delete old key in Firebase Console → generate new → update `FIREBASE_SERVICE_ACCOUNT_JSON` on Render |
| Weekly | Back up data | Firebase Console → Realtime Database → ⋮ → **Export JSON**; store off-site |
| After each deploy | Refresh any open tabs | Deployments reset sessions; stale pages show *"Security token expired"* until reloaded |

Security posture already built in: bcrypt password hashing, per-session CSRF tokens on every form, rate-limited logins, hardened session cookies (secure flags auto-enable under HTTPS), security headers on every response, database access locked behind service-account authentication, and uploads directory hardened against script execution.

Known limitation of the free plan: uploaded files are ephemeral (see note under [Deploying to Render](#deploying-to-render)).

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| *"Database lives in a different region"* in logs; logins fail | `FIREBASE_DATABASE_URL` uses `.firebaseio.com` but DB is regional | Copy exact URL from Firebase Console → Realtime Database |
| *"Security token expired"* after submitting a form | Page was open across a redeploy; session reset | Reload the page and retry |
| Site slow on first visit after a quiet period | Keepalive pinger failing, or free hours exhausted | Hit `/health.php` to warm it; check deploy logs for cron errors; upgrade plan to remove spin-down entirely |
| OTP email not arriving | SMTP blocked on Render free, or missing/invalid Gmail API creds | Set the `GOOGLE_*` trio (§ Gmail API mail); confirm via `[mailer]` lines in Render logs; check spam folder |

For anything else, check **Render → Logs** first: database errors are logged with a `[firebaseRDB]` prefix describing the exact cause.

## Project Structure

```
CRNP/
├── admin/                  # Admin console (dashboard, products, bookings,
│                           #   reports, staff, settings, history)
├── cashier/                # POS console (walk-in orders, booking queue,
│                           #   GCash verification, receipts, manual bookings)
├── kitchen/                # Kitchen display + forward-only status workflow
├── user/                   # Customer app (shop, cart, checkout, bookings,
│                           #   orders, profile, auth incl. OTP)
├── app/
│   ├── Core/Model.php      # ActiveRecord-style base model
│   └── Models/             # Order, Booking, Product, RentItem, Staff
├── includes/               # Auth guards, helpers, layout shell
│   ├── auth.php            #   require_user/cashier/kitchen/admin
│   ├── functions.php       #   e/redirect/money/csrf/rate_limit/upload/cache
│   └── header.php          #   role-aware nav + theme toggle
├── assets/                 # CSS (light/dark themes), JS, images, logo
├── uploads/                # User uploads (avatars, GCash proofs)
├── firebaseRDB.php         # Authenticated cURL wrapper over Firebase REST
├── database.rules.json     # RTDB rules + .indexOn for every orderBy field
├── config.php              # .env loader, session hardening, constants
├── init.php                # Bootstrap + PSR-4 autoloader
├── mailer.php              # OTP, order & booking receipt emails
├── render.yaml             # Render Blueprint (service definition)
├── Dockerfile              # php:8.2-apache + cron keepalive, entrypoint, pinger
└── tests/                  # Offline checks (smoke_token, indexed_rules, cashier_poll, otp_resend, mailer_api)
```

---

*Developed as a capstone project for Crates N' Plates Diner.*
