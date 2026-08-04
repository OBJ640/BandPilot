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
| instrument | Text | Musical role chosen from the fixed 34-option list |
| joined_at | Date and time | Date added to the band |

`user_id` can be empty so an owner can add a band member before that person creates an account.

### Band Questionnaire

A band questionnaire stores one set of setup answers for a band.

| Field | Type | Description |
|---|---|---|
| band_id | ID | Band that owns the answers |
| genres | Text | One to three music genres |
| experience_level | Choice | Beginner, mixed, intermediate, or advanced |
| main_goal | Choice | Casual playing, performances, recording, or competition |
| rehearsal_frequency | Choice | How often the band usually rehearses |
| session_minutes | Number | Usual rehearsal length |
| main_challenge | Choice | The band's biggest current challenge |
| notes | Text | Optional extra information |
| updated_by | ID | Owner who last changed the answers |
| completed_at | Date and time | First completion time |
| updated_at | Date and time | Last update time |

Each band has at most one questionnaire. A band's owner can edit it, while members can read the saved answers.

### Song

A song is a piece of music that the band is practising or performing.

| Field | Type | Description |
|---|---|---|
| id | ID | Song ID |
| band_id | ID | Related band |
| title | Text | Song title |
| artist | Text | Original artist |
| progress_level | Choice | Just starting, learning parts, can rehearse together, almost finished, or performance ready |
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

### Rehearsal Review

A rehearsal review stores the overall result of one rehearsal.

| Field | Type | Description |
|---|---|---|
| rehearsal_id | ID | Related rehearsal |
| overall_rating | Number | Overall rating from 1 to 5 |
| goals_met | Choice | Yes, partly, or no |
| notes | Text | Overall rehearsal note |
| updated_by | ID | Owner who last saved the survey |
| completed_at | Date and time | First completion time |
| updated_at | Date and time | Last update time |

Each rehearsal has at most one overall review.

### Rehearsal Song Review

A rehearsal song review stores the result for one song practised in one rehearsal.

| Field | Type | Description |
|---|---|---|
| id | ID | Review ID |
| rehearsal_id | ID | Related rehearsal |
| song_id | ID | Song being reviewed |
| performance_rating | Number | Song rating from 1 to 5 |
| progress_level_after | Choice | One of the five song progress levels after the rehearsal |
| status_after | Choice | Learning, practising, or ready |
| problem_type | Choice | Main problem found in the song |
| note | Text | Short song note |
| updated_at | Date and time | Last update time |

One rehearsal can have one review for each selected song. Saving the survey also updates the song's current progress and status.

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

This is for extra notes outside the completed song-by-song survey.

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
- One band can have one setup questionnaire.
- One band can have many rehearsals.
- One rehearsal can include many songs.
- One rehearsal can have one overall review.
- One rehearsal review can have many song reviews.
- One song can have reviews from many rehearsals.
- One rehearsal can have many availability records and feedback records.
- One band can have many performances.
- One performance can have many songs and checklist items.
- One rehearsal or performance can have saved AI results.

## Data Rules

- Song progress must use one of the five named levels. Percentages are not used in the product.
- Only band members can view a band's private data.
- Only users with permission can change band data.
- Only the band owner can save or edit a post-rehearsal survey.
- A reviewed song must belong to the same band as the rehearsal.
- A song can appear only once in one rehearsal survey.
- Rehearsal and song ratings must be between 1 and 5.
- Saving a rehearsal survey marks the rehearsal as completed.
- Archiving a song should keep old rehearsal surveys and feedback.
- AI output should only be saved after a user approves it.
- Creation and update times should use one time zone format in the database.
