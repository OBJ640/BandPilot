# User Flows

## Purpose

This document explains the main steps a user will follow in BandPilot.

## User Roles

### Band Owner

The band owner can:

- Change band information
- Add or remove members
- Manage songs, rehearsals, and performances
- Use AI features

### Band Member

A band member can:

- View band information
- Update their availability
- View songs and rehearsals
- Add rehearsal feedback

Role rules can stay simple in the first version. More roles can be added later.

## Main Flow

The main user flow is:

1. Create an account or sign in
2. Create a band
3. Add band members
4. Add songs
5. Update song progress
6. Create a rehearsal
7. Record member availability
8. Choose rehearsal songs and goals
9. Write feedback after the rehearsal
10. Ask AI to suggest the next rehearsal plan

## Flow 1 — Create an Account

### Goal

The user wants to start using BandPilot.

### Steps

1. The user opens the sign-up page.
2. The user enters a name, email, and password.
3. The app creates the account.
4. The app opens the home page.

### Result

The user can create or join a band.

## Flow 2 — Create a Band

### Goal

The user wants to make a shared space for their band.

### Steps

1. The user selects `Create Band`.
2. The user enters the band name and a short description.
3. The user saves the band.
4. The app adds the user as the band owner.

### Result

The new band appears on the user's home page.

## Flow 3 — Add Members

### Goal

The band owner wants to add the other musicians.

### Steps

1. The owner opens the members page.
2. The owner selects `Add Member`.
3. The owner enters the member's name, role, and instrument.
4. The owner saves the member.

### Result

The member appears in the band member list.

Email invitations can be added later. The first version can also allow the owner to add members without creating accounts for them.

## Flow 4 — Add and Update Songs

### Goal

The band wants to track the songs it is practising.

### Steps

1. A user opens the songs page.
2. The user selects `Add Song`.
3. The user enters the song name, artist, and notes.
4. The user sets the progress and status.
5. The user records any parts that need more practice.
6. The user saves the song.

### Result

The song appears in the song list with its latest progress.

## Flow 5 — Create a Rehearsal

### Goal

The band wants to plan its next rehearsal.

### Steps

1. A user opens the rehearsals page.
2. The user selects `Create Rehearsal`.
3. The user enters the date, time, place, and length.
4. Members record whether they are free.
5. The user chooses songs and goals.
6. The user saves the rehearsal.

### Result

The rehearsal appears in the band's upcoming rehearsal list.

## Flow 6 — Write Rehearsal Feedback

### Goal

The band wants to remember what happened during a rehearsal.

### Steps

1. A user opens a finished rehearsal.
2. The user selects the songs that were practised.
3. The user chooses problem types.
4. The user writes short notes.
5. The user updates each song's progress.
6. The user saves the feedback.

### Result

The feedback is saved in the rehearsal history and linked to the correct songs.

## Flow 7 — Create an AI Rehearsal Plan

### Goal

The band wants help planning the next rehearsal.

### Steps

1. A user selects `Create AI Plan`.
2. The app collects song progress, availability, performance dates, and past feedback.
3. The app sends this information to one AI model.
4. The AI returns a suggested plan.
5. The user checks and edits the plan.
6. The user saves the approved plan.

### Result

The plan is added to the rehearsal. The AI does not save changes before the user approves them.

## Flow 8 — Prepare for a Performance

### Goal

The band wants to prepare for an upcoming performance.

### Steps

1. A user creates a performance.
2. The user enters the date, place, and expected length.
3. The user adds songs to the setlist.
4. The user creates an equipment checklist.
5. The user can ask AI for setlist and preparation suggestions.
6. The user checks and saves the final plan.

### Result

The band has one page for its setlist, equipment, and performance tasks.

## Main Pages

The first version needs these pages:

- Sign up and sign in
- Home
- Band overview
- Members
- Songs
- Rehearsals
- Rehearsal details and feedback
- Performances
- AI plan review
- Settings

## Common Error Cases

The app should show a clear message when:

- Required information is missing
- A date or time is not valid
- A user does not have permission
- Data cannot be saved
- The AI service is not available

The user's existing data should not be lost when an error happens.
