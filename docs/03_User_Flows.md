# User Flows

## Purpose

This document explains the main steps a user will follow in BandPilot.

## User Roles

### Band Owner

The band owner can:

- Change band information
- Add or remove members
- Manage songs, rehearsals, and performances
- Complete and edit post-rehearsal surveys
- Use AI features

### Band Member

A band member can:

- View band information
- Update their availability
- View songs and rehearsals
- View saved post-rehearsal surveys

Account permissions stay simple: owner or member. A separate full musical-role list is used for each member's position in the band.

## Main Flow

The main user flow is:

1. Create an account or sign in
2. Create a band
3. Complete the band questionnaire
4. Add band members
5. Add songs
6. Update song progress
7. Create a rehearsal
8. Record member availability
9. Choose rehearsal songs and goals
10. Review every practised song after the rehearsal
11. Ask AI to suggest the next rehearsal plan

## Flow 1 — Create an Account

### Goal

The user wants to start using BandPilot.

### Steps

1. The user opens the sign-up page.
2. The user enters a name, email, and password.
3. The app creates the account.
4. The app signs the user in.
5. If the user has no band yet, the app opens the first-band questionnaire.

### Result

The user can set up a band without signing in again.

## Flow 1B — Complete the Band Questionnaire

### Goal

The band owner wants BandPilot to understand the band's sound and working habits.

### Steps

1. A new user enters the first band name and description.
2. The owner adds their instrument or role and one to three music genres.
3. The owner chooses the band's experience, main goal, rehearsal frequency, and usual rehearsal length.
4. The owner chooses the band's biggest current challenge and can add a note.
5. The app creates the first band when needed and saves the answers.
6. The owner can reopen the questionnaire from the sidebar and edit it later.

### Result

The answers remain connected to the correct band and are available after the user signs out and signs in again.

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
3. The owner enters the member's name and chooses a musical role from the list.
4. The owner saves the member.
5. The owner can edit the member later or remove a member who is no longer in the band.

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
4. The user chooses one of the five progress levels and sets the status.
5. The user records any parts that need more practice.
6. The user saves the song.
7. The owner can edit the song later or archive it without removing old history.

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
7. The owner can edit or cancel a planned rehearsal.

### Result

The rehearsal appears in the band's upcoming rehearsal list.

The owner can record availability for every member. A member with a linked account can update their own answer.

## Flow 6 — Complete the Post-Rehearsal Survey

### Goal

The band owner wants to remember how the rehearsal and each song went.

### Steps

1. The owner opens a saved rehearsal.
2. The owner starts the post-rehearsal survey.
3. The owner rates the whole rehearsal and records whether its goals were met.
4. The owner selects every song that was practised.
5. For each song, the owner adds a rating, chooses a new progress level, and adds a new status, problem type, and short note.
6. The owner saves the survey.
7. The app marks the rehearsal as completed and updates the selected songs.
8. The owner can open the survey again and edit it later.
9. The band can open survey history and filter old song reviews by song or problem type.

### Result

The overall result and every song review are saved with the correct rehearsal. Band members can view the saved survey.

## Flow 7 — Create an AI Rehearsal Plan

### Goal

The band wants help planning the next rehearsal.

### Steps

1. A user selects `Create AI Plan`.
2. The app collects song progress, availability, performance dates, and past survey results.
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
- Rehearsal details and song-by-song survey
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
