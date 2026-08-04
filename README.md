# BandPilot

An AI-powered collaboration platform for bands, helping musicians manage rehearsals, track progress, and organize performances.

## Current Status

BandPilot is under development. The first website structure includes:

- Account registration, sign in, sign out, and protected sessions
- A first-band onboarding questionnaire that can be edited later
- Band creation, editing, and switching
- Band member creation, editing, role selection, and removal
- A responsive dashboard
- Song creation, editing, archiving, and five clear progress levels
- Rehearsal creation, song selection, editing, cancellation, and member availability
- A post-rehearsal survey with a separate review for each song
- Searchable survey history by song and problem type
- Performance preparation pages
- An AI assistant review page
- A PHP API structure
- A SQLite database structure

## Technology

- Frontend: HTML, CSS, and JavaScript
- Backend: PHP
- Database: SQLite during development
- AI: One LLM API, connected through the PHP backend

## Project Structure

```text
BandPilot/
├── public/                 Website files
├── backend/
│   ├── public/            API entry
│   ├── src/               PHP classes
│   ├── database/          Database structure and sample data
│   ├── scripts/           Setup scripts
│   ├── storage/           Local database files
│   └── tests/             Backend tests
├── docs/                   Product and development documents
├── router.php              Local website router
└── .env.example            Settings example
```

## Local Setup

### 1. Requirements

- PHP 8.1 or newer
- PHP PDO SQLite extension
- PHP cURL extension for future AI requests

### 2. Create Local Settings

Copy `.env.example` to `.env`.

The AI values can stay empty until an LLM provider is chosen.

### 3. Create the Database

```bash
php backend/scripts/init_database.php
```

### 4. Start the Website

```bash
php -S 127.0.0.1:8000 router.php
```

Open `http://127.0.0.1:8000` in a browser.

For the included demo data, sign in with:

- Email: `demo@bandpilot.local`
- Password: `BandPilot123!`

### 5. Run the Basic Backend Test

```bash
php backend/tests/smoke_test.php
php backend/tests/auth_test.php
php backend/tests/core_management_test.php
php backend/tests/interaction_test.php
php backend/tests/questionnaire_test.php
php backend/tests/rehearsal_review_test.php
php backend/tests/frontend_contract_test.php
```

## API

The first working routes are:

- `GET /api/v1/health`
- `GET /api/v1/auth/session`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/users/me`
- `PATCH /api/v1/users/me`
- `GET /api/v1/bands`
- `POST /api/v1/bands`
- `GET /api/v1/bands/{band_id}`
- `PATCH /api/v1/bands/{band_id}`
- `GET /api/v1/bands/{band_id}/questionnaire`
- `PUT /api/v1/bands/{band_id}/questionnaire`
- `GET /api/v1/bands/{band_id}/members`
- `POST /api/v1/bands/{band_id}/members`
- `PATCH /api/v1/bands/{band_id}/members/{member_id}`
- `DELETE /api/v1/bands/{band_id}/members/{member_id}`
- `GET /api/v1/bands/{band_id}/songs`
- `POST /api/v1/bands/{band_id}/songs`
- `PATCH /api/v1/bands/{band_id}/songs/{song_id}`
- `DELETE /api/v1/bands/{band_id}/songs/{song_id}`
- `GET /api/v1/bands/{band_id}/rehearsals`
- `POST /api/v1/bands/{band_id}/rehearsals`
- `PATCH /api/v1/bands/{band_id}/rehearsals/{rehearsal_id}`
- `DELETE /api/v1/bands/{band_id}/rehearsals/{rehearsal_id}`
- `GET /api/v1/rehearsals/{rehearsal_id}/availability`
- `PUT /api/v1/rehearsals/{rehearsal_id}/availability/{member_id}`
- `GET /api/v1/rehearsals/{rehearsal_id}/review`
- `PUT /api/v1/rehearsals/{rehearsal_id}/review`
- `GET /api/v1/bands/{band_id}/review-history`
- `POST /api/v1/rehearsals/{rehearsal_id}/ai-plan`

More routes are listed in [API Design](docs/06_API_Design.md).

## Documents

- [Vision](docs/01_Vision.md)
- [Product Requirements](docs/02_Product_Requirements.md)
- [User Flows](docs/03_User_Flows.md)
- [Data Model](docs/04_Data_Model.md)
- [Technical Architecture](docs/05_Technical_Architecture.md)
- [API Design](docs/06_API_Design.md)
- [Development Roadmap](docs/07_Development_Roadmap.md)
- [Development Log](docs/08_Development_Log.md)

## License

MIT
