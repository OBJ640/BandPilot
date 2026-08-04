# API Design

## Purpose

This document lists the main backend routes needed by the BandPilot website.

## Basic Rules

- The API path starts with `/api/v1`.
- Data is sent and returned as JSON.
- Private routes require a signed-in user.
- A user can only access bands they belong to.
- Dates and times use ISO 8601 format.
- Errors return a clear message.

Example error:

```json
{
  "error": "Song not found"
}
```

## Account Routes

| Method | Route | Purpose |
|---|---|---|
| POST | `/api/v1/auth/register` | Create an account |
| POST | `/api/v1/auth/login` | Sign in |
| POST | `/api/v1/auth/logout` | Sign out |
| GET | `/api/v1/auth/session` | Check the current login session |
| GET | `/api/v1/users/me` | Get the current user |
| PATCH | `/api/v1/users/me` | Change the current user |

## Band Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands` | List the user's bands |
| POST | `/api/v1/bands` | Create a band |
| GET | `/api/v1/bands/{band_id}` | Get one band |
| PATCH | `/api/v1/bands/{band_id}` | Change band information |
| DELETE | `/api/v1/bands/{band_id}` | Archive a band |
| GET | `/api/v1/bands/{band_id}/questionnaire` | Get the band questionnaire |
| PUT | `/api/v1/bands/{band_id}/questionnaire` | Save or edit the band questionnaire |

Example request:

```json
{
  "name": "The School Band",
  "description": "A student rock band"
}
```

## Member Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands/{band_id}/members` | List members |
| POST | `/api/v1/bands/{band_id}/members` | Add a member |
| PATCH | `/api/v1/bands/{band_id}/members/{member_id}` | Change a member |
| DELETE | `/api/v1/bands/{band_id}/members/{member_id}` | Remove a member |

Member roles use a fixed list of 34 choices. The list includes singing, common instruments, production, writing, music direction, sound, and band management roles, plus `Other`. The account permission remains either `owner` or `member` and is not the same as the member's musical role.

## Song Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands/{band_id}/songs` | List songs |
| POST | `/api/v1/bands/{band_id}/songs` | Add a song |
| GET | `/api/v1/bands/{band_id}/songs/{song_id}` | Get one song |
| PATCH | `/api/v1/bands/{band_id}/songs/{song_id}` | Change a song |
| DELETE | `/api/v1/bands/{band_id}/songs/{song_id}` | Archive a song |

Example request:

```json
{
  "title": "Little Wing",
  "artist": "Jimi Hendrix",
  "progress_level": "rehearsing",
  "status": "practising",
  "problem_notes": "The chorus entry is not together."
}
```

## Rehearsal Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands/{band_id}/rehearsals` | List rehearsals |
| POST | `/api/v1/bands/{band_id}/rehearsals` | Create a rehearsal |
| GET | `/api/v1/bands/{band_id}/rehearsals/{rehearsal_id}` | Get one rehearsal |
| PATCH | `/api/v1/bands/{band_id}/rehearsals/{rehearsal_id}` | Change a rehearsal |
| DELETE | `/api/v1/bands/{band_id}/rehearsals/{rehearsal_id}` | Cancel a rehearsal |

Example request:

```json
{
  "title": "Saturday Rehearsal",
  "start_time": "2026-08-08T14:00:00+08:00",
  "duration_minutes": 120,
  "location": "School Music Room",
  "goals": "Improve the chorus and practise the full setlist",
  "song_ids": ["song-1", "song-2"]
}
```

## Availability Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/rehearsals/{rehearsal_id}/availability` | View member availability |
| PUT | `/api/v1/rehearsals/{rehearsal_id}/availability/{member_id}` | Update one member's availability |

Example request:

```json
{
  "status": "available",
  "note": "I can arrive after 2 PM."
}
```

## Post-Rehearsal Survey Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/rehearsals/{rehearsal_id}/review` | Get the rehearsal survey and its song reviews |
| PUT | `/api/v1/rehearsals/{rehearsal_id}/review` | Save or edit the full rehearsal survey |
| GET | `/api/v1/bands/{band_id}/review-history` | List per-song survey history |

Example request:

```json
{
  "overall_rating": 4,
  "goals_met": "partly",
  "notes": "The full set was more stable today.",
  "songs": [
    {
      "song_id": 1,
      "performance_rating": 3,
      "progress_level_after": "polishing",
      "status_after": "practising",
      "problem_type": "rhythm",
      "note": "The chorus entry still needs work."
    }
  ]
}
```

The band owner can save or edit this survey. Other band members can view it. Every selected song must belong to the rehearsal's band. Saving the survey updates the selected songs and marks the rehearsal as completed.

Allowed progress values are `starting`, `learning`, `rehearsing`, `polishing`, and `ready`. The website displays these as Just starting, Learning parts, Can rehearse together, Almost finished, and Performance ready.

## Other Feedback Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/rehearsals/{rehearsal_id}/feedback` | List rehearsal feedback |
| POST | `/api/v1/rehearsals/{rehearsal_id}/feedback` | Add feedback |
| PATCH | `/api/v1/feedback/{feedback_id}` | Change feedback |
| DELETE | `/api/v1/feedback/{feedback_id}` | Remove feedback |

Example request:

```json
{
  "song_id": "song-1",
  "problem_type": "rhythm",
  "note": "The chorus starts at different times."
}
```

## Performance Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands/{band_id}/performances` | List performances |
| POST | `/api/v1/bands/{band_id}/performances` | Create a performance |
| GET | `/api/v1/bands/{band_id}/performances/{performance_id}` | Get one performance |
| PATCH | `/api/v1/bands/{band_id}/performances/{performance_id}` | Change a performance |
| DELETE | `/api/v1/bands/{band_id}/performances/{performance_id}` | Cancel a performance |
| PUT | `/api/v1/performances/{performance_id}/setlist` | Save the setlist |
| PUT | `/api/v1/performances/{performance_id}/checklist` | Save the checklist |

## AI Routes

| Method | Route | Purpose |
|---|---|---|
| POST | `/api/v1/rehearsals/{rehearsal_id}/ai-plan` | Create a rehearsal plan |
| POST | `/api/v1/rehearsals/{rehearsal_id}/ai-summary` | Summarize feedback |
| POST | `/api/v1/performances/{performance_id}/ai-plan` | Create performance suggestions |
| POST | `/api/v1/bands/{band_id}/ai-results` | Save an approved AI result |
| POST | `/api/v1/ai-results/{result_id}/approve` | Approve and save an AI result |

Example AI plan response:

```json
{
  "result_id": "result-1",
  "status": "waiting_for_approval",
  "plan": {
    "summary": "Focus on the chorus before playing the full set.",
    "activities": [
      {
        "song_id": "song-1",
        "goal": "Practise the chorus entry",
        "minutes": 25
      }
    ],
    "notes": [
      "Begin at a slower speed."
    ]
  }
}
```

## Common Response Codes

| Code | Meaning |
|---|---|
| 200 | Request completed |
| 201 | New data created |
| 400 | Request data is not valid |
| 401 | User is not signed in |
| 403 | User does not have permission |
| 404 | Data was not found |
| 409 | Data conflicts with existing data |
| 500 | Server error |
| 503 | AI service is not available |

## API Work Order

Build the API in this order:

1. Accounts
2. Bands and members
3. Songs
4. Rehearsals and availability
5. Post-rehearsal survey and feedback
6. AI rehearsal plan
7. Performances
8. Other AI features
