# Development Roadmap

## Purpose

This document gives a simple order for building and testing BandPilot.

The goal is to finish one useful part at a time. Each stage should work before the next stage begins.

## Stage 0 — Project Setup

### Work

- [ ] Create `frontend` and `backend` folders
- [ ] Set up Vue 3 and TypeScript
- [ ] Set up FastAPI
- [ ] Set up SQLite
- [ ] Add a `.gitignore` file
- [ ] Add environment variable examples
- [ ] Add basic frontend and backend tests
- [ ] Write setup steps in `README.md`

### Ready When

- The frontend can open in a browser.
- The backend has a working health route.
- The frontend can call the backend.
- Tests can run locally.

## Stage 1 — Accounts and Bands

### Work

- [ ] Create account
- [ ] Sign in and sign out
- [ ] Create a band
- [ ] Edit band information
- [ ] Add and edit members
- [ ] Check band permissions

### Ready When

- A new user can create an account and a band.
- The band owner can add members.
- A user cannot view another band's private data.

## Stage 2 — Songs

### Work

- [ ] Add and edit songs
- [ ] Show the song list
- [ ] Update song progress
- [ ] Add problem notes
- [ ] Mark songs as learning, practising, or ready
- [ ] Archive songs without removing old history

### Ready When

- A band can manage all songs from one page.
- Progress values are always between 0 and 100.
- Song changes are saved correctly.

## Stage 3 — Rehearsals

### Work

- [ ] Create and edit rehearsals
- [ ] Record date, time, place, and length
- [ ] Add songs and goals
- [ ] Record member availability
- [ ] Show upcoming and past rehearsals
- [ ] Mark a rehearsal as completed or cancelled

### Ready When

- A band can plan a rehearsal from start to finish.
- Members can record whether they are free.
- The rehearsal shows its songs, goals, and availability.

## Stage 4 — Feedback

### Work

- [ ] Add feedback to a rehearsal
- [ ] Link feedback to a song
- [ ] Add problem types and notes
- [ ] Update song progress after a rehearsal
- [ ] Show feedback history
- [ ] Filter feedback by song or problem type

### Ready When

- A band can finish a rehearsal and save useful notes.
- Old feedback can be found later.
- Song progress can change after each rehearsal.

## Stage 5 — First AI Feature

### Work

- [ ] Add the LLM API connection in the backend
- [ ] Keep the API key outside the code
- [ ] Create the rehearsal-plan prompt
- [ ] Send only needed band data
- [ ] Check the AI response format
- [ ] Show the result before saving it
- [ ] Let the user edit and approve the plan
- [ ] Show a clear error if the AI service fails

### Ready When

- AI can create a plan from real band data.
- The plan includes goals and practice times.
- Nothing is saved before user approval.
- Basic app features still work when AI is unavailable.

## Stage 6 — Performances

### Work

- [ ] Create and edit performances
- [ ] Build a setlist
- [ ] Create an equipment checklist
- [ ] Assign checklist items to members
- [ ] Add AI performance suggestions

### Ready When

- A band can prepare a basic live performance.
- The setlist order and checklist are saved.
- AI suggestions can be reviewed before saving.

## Stage 7 — Design and Phone Support

### Work

- [ ] Make all main pages work on phones
- [ ] Improve loading, empty, and error states
- [ ] Add clear form messages
- [ ] Check keyboard use and text readability
- [ ] Keep common actions easy to find

### Ready When

- The main user flow works on a computer and phone.
- Users understand what to do on each page.
- Errors do not remove unsaved form data.

## Stage 8 — Testing With a Real Band

### Work

- [ ] Ask one band to use the app
- [ ] Watch how they complete the main flow
- [ ] Record confusing steps and missing features
- [ ] Fix important bugs
- [ ] Improve the AI prompt using real feedback
- [ ] Check that private data is protected

### Questions to Ask

- Was it easy to create the band and add songs?
- Did the app save time when planning a rehearsal?
- Was the AI plan useful and clear?
- Did the band continue to record feedback?
- What part of the app was confusing?

### Ready When

- A real band can use the full main flow.
- There are no known bugs that stop the main flow.
- The band finds at least one AI feature useful.

## Version Plan

| Version | Main Result |
|---|---|
| 0.1 | Accounts, bands, members, and songs |
| 0.2 | Rehearsals, availability, and feedback |
| 0.3 | AI rehearsal plans and summaries |
| 0.4 | Performance planning |
| 1.0 | Tested and polished first complete version |

## Work Rules

- Keep each change small and testable.
- Finish the basic feature before adding AI to it.
- Do not add a new feature unless it supports the main user flow.
- Update the documents when an important product decision changes.
- Do not save secrets or API keys in Git.
- Test each stage before moving to the next one.

## Future Ideas

These ideas are not part of the first version:

- Native iOS app
- Audio recording and analysis
- Calendar connection
- Email invitations
- Notifications
- More member roles and permissions
- Public band pages
- Music file sharing
