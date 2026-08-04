# Development Log

## Purpose

This file records important BandPilot development work, changes, data updates, and test results.

## 2026-08-04 — Working Core Website

### Project Structure

- Built the website with HTML, CSS, and JavaScript.
- Built the backend with PHP and SQLite.
- Added the API router, environment settings, database setup script, and sample data.
- Added responsive layouts for the main dashboard pages and forms.

### Accounts and Bands

- Added account registration, sign in, sign out, protected sessions, and CSRF protection.
- Added band creation, band editing, and band switching.
- Added user profile editing for name, email, and instrument.
- Added permission checks so users cannot access bands they do not belong to.
- Added member creation, editing, and removal for band owners.
- Changed the user and member musical role to a fixed dropdown with 34 complete choices.
- Kept musical roles separate from the owner/member account permission.

### Band Questionnaire

- Added a three-step first-band questionnaire.
- Added answers for genres, experience, goals, rehearsal routine, current challenge, and notes.
- Allowed the band owner to reopen and edit the questionnaire.
- Saved questionnaire answers in SQLite and showed the completion state on the dashboard.

### Songs

- Added the song list and song creation form.
- Added artist, status, problem notes, and progress tracking.
- Changed song progress from a percentage to five named levels:

  1. Just starting
  2. Learning parts
  3. Can rehearse together
  4. Almost finished
  5. Performance ready

- Added a five-part visual progress marker without showing numeric percentages.
- Added automatic conversion of old percentage data into the five new levels.
- Added song editing and safe archiving while keeping old rehearsal history.

### Rehearsals

- Added rehearsal creation and a real saved rehearsal list.
- Added date, time, length, location, goals, and rehearsal status.
- Added separate views for upcoming and completed rehearsals.
- Added song selection when creating or editing a rehearsal.
- Added planned-rehearsal editing and cancellation.
- Added a member availability survey with available, unavailable, and unsure choices.

### Post-Rehearsal Survey

- Added one overall survey for each rehearsal.
- Added the overall rating, goal result, and overall notes.
- Let the owner select the songs that were actually practised.
- Added a separate review for every selected song.
- Each song review records a rating, five-level progress, status, problem type, and note.
- Saving the survey updates the selected songs and marks the rehearsal as completed.
- Saved surveys can be reopened and edited.
- Band members can view a survey, while only the owner can change it.
- Added a song-by-song survey history view with song and problem-type filters.

### Performance and AI Foundations

- Added basic performance creation, setlist, and equipment checklist interfaces.
- Added the PHP service structure for one LLM API.
- Added AI result review, editing, approval, and save interfaces.
- The AI page currently uses sample results in the frontend. Connecting the visible page to a configured live LLM is still unfinished.
- Performance setlists and checklists still need full database storage and editing.

### Documentation

- Updated product requirements, user flows, data model, technical architecture, API design, and roadmap.
- Updated the README with setup steps, demo account details, routes, and test commands.
- Kept the language simple and changed all song-progress documentation to the five-level standard.

## Data Change Record

### Five-Level Song Progress Migration

The database keeps existing songs and automatically maps old progress values to the new levels:

| Old value | New level |
|---|---|
| 0–20 | Just starting |
| 21–40 | Learning parts |
| 41–60 | Can rehearse together |
| 61–80 | Almost finished |
| 81–100 | Performance ready |

The website and API now use only the named level. Users no longer enter or see a song-progress percentage.

## Test Record

Latest full test run: **2026-08-04 — Passed**

| Test | What it checks | Result |
|---|---|---|
| `smoke_test.php` | Backend health and basic startup | Passed |
| `auth_test.php` | Registration, login, sessions, logout, and account safety | Passed |
| `core_management_test.php` | Members, all 34 roles, song editing and archiving, rehearsal song selection, availability, cancellation, and survey history | Passed |
| `interaction_test.php` | Band, profile, song, rehearsal, performance, and save actions | Passed |
| `questionnaire_test.php` | Questionnaire validation, saving, editing, and permissions | Passed |
| `rehearsal_review_test.php` | Overall and per-song survey saving, five-level progress updates, editing, rollback, and permissions | Passed |
| `frontend_contract_test.php` | Required controls, working button paths, and five-level progress fields | Passed |

Browser checks also passed for:

- Registration and login flows
- Band and profile editing
- Questionnaire saving and editing
- Real rehearsal list loading
- Opening, saving, and reopening the post-rehearsal survey
- Saving a different progress level for each song
- Updating song cards after the survey
- Adding a song with the five-level progress selector
- Desktop layout of the song page and survey

Temporary browser-test records were removed after verification. The original demo data was restored.

## Progress Snapshot — 2026-08-04

### Core MVP Stages 0–4

- Completed checklist items: **41 of 41**
- Approximate core MVP progress: **100%**

### Whole Roadmap

- Completed checklist items: **41 of 65**
- Approximate full roadmap progress: **63%**

### Main Work Still Needed

1. Connect the visible AI page to a real configured LLM response.
2. Save performance setlists and equipment checklists in the database.
3. Complete phone, accessibility, and real-band testing.
