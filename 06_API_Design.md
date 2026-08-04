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
| GET | `/api/v1/users/me` | Get the current user |

## Band Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/bands` | List the user's bands |
| POST | `/api/v1/bands` | Create a band |
| GET | `/api/v1/bands/{band_id}` | Get one band |
| PATCH | `/api/v1/bands/{band_id}` | Change band information |
| DELETE | `/api/v1/bands/{band_id}` | Archive a band |

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
  "progress": 60,
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
| PUT | `/api/v1/rehearsals/{rehearsal_id}/availability/me` | Update current user's availability |

Example request:

```json
{
  "status": "available",
  "note": "I can arrive after 2 PM."
}
```

## Feedback Routes

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
5. Feedback
6. AI rehearsal plan
7. Performances
8. Other AI features
