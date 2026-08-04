# Technical Architecture

## Purpose

This document explains how the main parts of BandPilot will work together.

## Planned Technology

### Frontend

- Vue 3
- TypeScript
- A responsive design for computers and phones

The frontend shows pages, forms, lists, and AI results to the user.

### Backend

- Python
- FastAPI

The backend checks user requests, reads and saves data, and calls the AI service.

### Database

- SQLite during early development
- PostgreSQL when the app is ready for online use

SQLite is simple for local development. PostgreSQL is better when many users use the app online.

### AI

- One LLM API
- Prompts stored in the backend
- Clear JSON output when the app needs structured results

The exact AI provider can be chosen later. The rest of the app should not depend strongly on one provider.

## Main Structure

```text
User
  |
  v
Vue Website
  |
  v
FastAPI Backend
  |             |
  v             v
Database      LLM API
```

The frontend must not call the LLM API directly. The backend keeps the API key safe and decides what information is sent.

## Request Flow

### Normal Feature

1. The user completes a form in the website.
2. The frontend sends a request to the backend.
3. The backend checks the user and the data.
4. The backend reads or changes the database.
5. The backend sends a result to the frontend.
6. The frontend updates the page.

### AI Feature

1. The user asks for an AI plan or summary.
2. The backend checks that the user belongs to the band.
3. The backend loads the needed band data.
4. The backend removes information that the AI does not need.
5. The backend sends a prompt and data to the LLM API.
6. The backend checks the AI response.
7. The frontend shows the result for review.
8. The result is saved only after the user approves it.

## Backend Parts

### API Routes

Receive requests from the frontend and return responses.

### Services

Contain the main app rules for bands, songs, rehearsals, performances, and AI features.

### Database Models

Describe how app data is stored.

### AI Service

Builds prompts, calls the LLM API, checks the response, and handles errors.

### Prompts

Store the instructions used for each AI feature.

## Suggested Project Structure

```text
BandPilot/
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── services/
│   │   ├── stores/
│   │   └── types/
│   └── tests/
├── backend/
│   ├── app/
│   │   ├── api/
│   │   ├── models/
│   │   ├── schemas/
│   │   ├── services/
│   │   ├── prompts/
│   │   └── main.py
│   └── tests/
├── docs/
└── README.md
```

The numbered planning documents can move into `docs/` when the code is created.

## AI Input

The backend should send only useful data, such as:

- Rehearsal date and length
- Member availability
- Song names and progress
- Song problem notes
- Recent rehearsal feedback
- Upcoming performance date

Passwords, email addresses, and other private data should not be sent to the AI when they are not needed.

## AI Output

For a rehearsal plan, the backend can ask for data in this shape:

```json
{
  "summary": "Focus on the two songs that need the most work.",
  "activities": [
    {
      "song_id": "song-id",
      "goal": "Practise the chorus entry",
      "minutes": 20
    }
  ],
  "notes": [
    "Start slowly and increase the speed."
  ]
}
```

The backend must check the output before sending it to the frontend.

## Sign-In and Safety

- Passwords must be protected before they are saved.
- API keys must be stored in environment variables, not in code.
- Every private request must check the signed-in user.
- The backend must check that the user belongs to the requested band.
- User input must be checked before it is saved.
- AI errors must not stop basic features from working.

## Testing

The project should include:

- Tests for important backend rules
- Tests for API routes
- Tests for the main frontend forms
- A test for the full main user flow
- Tests for invalid or missing AI output

## First Version Limits

- One responsive website, not a separate iOS app
- One backend service
- One database
- One LLM API
- No audio storage or analysis
- No automatic AI actions without user approval
