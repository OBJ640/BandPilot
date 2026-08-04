# Data Model

## Purpose

This document explains the main data stored by BandPilot and how the data is connected.

## Main Data

### User

A user is a person with a BandPilot account.

| Field | Type | Description |
|---|---|---|
| id | ID | User ID |
| name | Text | User name |
| email | Text | Email address |
| password_hash | Text | Protected password data |
| created_at | Date and time | Account creation time |

The app must never save a password as normal text.

### Band

A band is the main shared space in the app.

| Field | Type | Description |
|---|---|---|
| id | ID | Band ID |
| name | Text | Band name |
| description | Text | Short band description |
| owner_id | ID | User who created the band |
| created_at | Date and time | Band creation time |

### Band Member

A band member connects a person to a band.

| Field | Type | Description |
|---|---|---|
| id | ID | Member ID |
| band_id | ID | Related band |
| user_id | ID or empty | Related user account, if available |
| display_name | Text | Name shown in the band |
| role | Text | Owner or member |
| instrument | Text | Instrument or band role |
| joined_at | Date and time | Date added to the band |

`user_id` can be empty so an owner can add a band member before that person creates an account.

### Song

A song is a piece of music that the band is practising or performing.

| Field | Type | Description |
|---|---|---|
| id | ID | Song ID |
| band_id | ID | Related band |
| title | Text | Song title |
| artist | Text | Original artist |
| progress | Number | Progress from 0 to 100 |
| status | Text | Learning, practising, or ready |
| problem_notes | Text | Parts that need more work |
| created_at | Date and time | Date added |
| updated_at | Date and time | Last update time |

### Rehearsal

A rehearsal stores the plan and result of one band practice.

| Field | Type | Description |
|---|---|---|
| id | ID | Rehearsal ID |
| band_id | ID | Related band |
| title | Text | Rehearsal title |
| start_time | Date and time | Start time |
| duration_minutes | Number | Planned length |
| location | Text | Rehearsal place |
| goals | Text | Main rehearsal goals |
| plan | Text | Saved rehearsal plan |
| status | Text | Planned, completed, or cancelled |
| created_at | Date and time | Creation time |

### Rehearsal Song

A rehearsal song connects a song to a rehearsal.

| Field | Type | Description |
|---|---|---|
| id | ID | Record ID |
| rehearsal_id | ID | Related rehearsal |
| song_id | ID | Related song |
| planned_minutes | Number | Planned practice time |
| order_number | Number | Song order in the plan |

### Availability

Availability records whether a member can join a rehearsal.

| Field | Type | Description |
|---|---|---|
| id | ID | Record ID |
| rehearsal_id | ID | Related rehearsal |
| member_id | ID | Related band member |
| status | Text | Available, unavailable, or unsure |
| note | Text | Optional note |

### Feedback

Feedback records a problem or note from a rehearsal.

| Field | Type | Description |
|---|---|---|
| id | ID | Feedback ID |
| rehearsal_id | ID | Related rehearsal |
| song_id | ID or empty | Related song, if needed |
| author_id | ID | User who wrote the feedback |
| problem_type | Text | Rhythm, teamwork, technique, tone, or other |
| note | Text | Feedback details |
| created_at | Date and time | Creation time |

### Performance

A performance stores information about a live show.

| Field | Type | Description |
|---|---|---|
| id | ID | Performance ID |
| band_id | ID | Related band |
| name | Text | Performance name |
| start_time | Date and time | Performance time |
| location | Text | Performance place |
| length_minutes | Number | Expected length |
| notes | Text | Other information |
| status | Text | Planned, completed, or cancelled |

### Setlist Item

A setlist item connects a song to a performance.

| Field | Type | Description |
|---|---|---|
| id | ID | Record ID |
| performance_id | ID | Related performance |
| song_id | ID | Related song |
| order_number | Number | Song order |
| expected_minutes | Number | Expected song length |

### Checklist Item

A checklist item stores one performance task or piece of equipment.

| Field | Type | Description |
|---|---|---|
| id | ID | Item ID |
| performance_id | ID | Related performance |
| text | Text | Task or equipment name |
| assigned_member_id | ID or empty | Member responsible for it |
| is_done | Yes or no | Completion state |

### AI Result

An AI result stores AI output that the user has checked and saved.

| Field | Type | Description |
|---|---|---|
| id | ID | Result ID |
| band_id | ID | Related band |
| rehearsal_id | ID or empty | Related rehearsal |
| performance_id | ID or empty | Related performance |
| result_type | Text | Rehearsal plan, summary, or performance plan |
| content | Text or JSON | Saved AI output |
| created_by | ID | User who requested it |
| created_at | Date and time | Creation time |

## Data Connections

- One user can belong to many bands.
- One band can have many members.
- One band can have many songs.
- One band can have many rehearsals.
- One rehearsal can include many songs.
- One rehearsal can have many availability records and feedback records.
- One band can have many performances.
- One performance can have many songs and checklist items.
- One rehearsal or performance can have saved AI results.

## Data Rules

- Song progress must be between 0 and 100.
- Only band members can view a band's private data.
- Only users with permission can change band data.
- Removing a song should not remove old rehearsal feedback.
- AI output should only be saved after a user approves it.
- Creation and update times should use one time zone format in the database.
