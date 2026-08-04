PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bands (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    owner_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at TEXT,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS band_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    band_id INTEGER NOT NULL,
    user_id INTEGER,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('owner', 'member')),
    instrument TEXT NOT NULL DEFAULT '',
    joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS band_questionnaires (
    band_id INTEGER PRIMARY KEY,
    genres TEXT NOT NULL,
    experience_level TEXT NOT NULL CHECK (experience_level IN ('beginner', 'mixed', 'intermediate', 'advanced')),
    main_goal TEXT NOT NULL CHECK (main_goal IN ('casual', 'performance', 'recording', 'competition')),
    rehearsal_frequency TEXT NOT NULL CHECK (rehearsal_frequency IN ('weekly', 'twice_month', 'monthly', 'as_needed')),
    session_minutes INTEGER NOT NULL CHECK (session_minutes IN (60, 90, 120, 180)),
    main_challenge TEXT NOT NULL CHECK (main_challenge IN ('availability', 'song_learning', 'timing', 'teamwork', 'performance_prep')),
    notes TEXT NOT NULL DEFAULT '',
    version INTEGER NOT NULL DEFAULT 1,
    updated_by INTEGER,
    completed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS songs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    band_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    artist TEXT NOT NULL DEFAULT '',
    progress INTEGER NOT NULL DEFAULT 0 CHECK (progress BETWEEN 0 AND 100),
    progress_level TEXT NOT NULL DEFAULT 'starting' CHECK (progress_level IN ('starting', 'learning', 'rehearsing', 'polishing', 'ready')),
    status TEXT NOT NULL DEFAULT 'learning' CHECK (status IN ('learning', 'practising', 'ready')),
    problem_notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at TEXT,
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rehearsals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    band_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    start_time TEXT NOT NULL,
    duration_minutes INTEGER NOT NULL CHECK (duration_minutes > 0),
    location TEXT NOT NULL DEFAULT '',
    goals TEXT NOT NULL DEFAULT '',
    plan TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'planned' CHECK (status IN ('planned', 'completed', 'cancelled')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rehearsal_songs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rehearsal_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    planned_minutes INTEGER NOT NULL DEFAULT 0,
    order_number INTEGER NOT NULL DEFAULT 0,
    UNIQUE (rehearsal_id, song_id),
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS rehearsal_reviews (
    rehearsal_id INTEGER PRIMARY KEY,
    overall_rating INTEGER NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    goals_met TEXT NOT NULL CHECK (goals_met IN ('yes', 'partly', 'no')),
    notes TEXT NOT NULL DEFAULT '',
    updated_by INTEGER,
    completed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rehearsal_song_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rehearsal_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    performance_rating INTEGER NOT NULL CHECK (performance_rating BETWEEN 1 AND 5),
    progress_after INTEGER NOT NULL CHECK (progress_after BETWEEN 0 AND 100),
    progress_level_after TEXT NOT NULL DEFAULT 'starting' CHECK (progress_level_after IN ('starting', 'learning', 'rehearsing', 'polishing', 'ready')),
    status_after TEXT NOT NULL CHECK (status_after IN ('learning', 'practising', 'ready')),
    problem_type TEXT NOT NULL CHECK (problem_type IN ('none', 'rhythm', 'coordination', 'technique', 'tone', 'memory', 'other')),
    note TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (rehearsal_id, song_id),
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rehearsal_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('available', 'unavailable', 'unsure')),
    note TEXT NOT NULL DEFAULT '',
    UNIQUE (rehearsal_id, member_id),
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES band_members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rehearsal_id INTEGER NOT NULL,
    song_id INTEGER,
    author_id INTEGER,
    problem_type TEXT NOT NULL DEFAULT 'other',
    note TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS performances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    band_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    start_time TEXT NOT NULL,
    location TEXT NOT NULL DEFAULT '',
    length_minutes INTEGER NOT NULL DEFAULT 0,
    notes TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'planned' CHECK (status IN ('planned', 'completed', 'cancelled')),
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS setlist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    performance_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    order_number INTEGER NOT NULL,
    expected_minutes INTEGER NOT NULL DEFAULT 0,
    UNIQUE (performance_id, song_id),
    FOREIGN KEY (performance_id) REFERENCES performances(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS checklist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    performance_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    assigned_member_id INTEGER,
    is_done INTEGER NOT NULL DEFAULT 0 CHECK (is_done IN (0, 1)),
    FOREIGN KEY (performance_id) REFERENCES performances(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_member_id) REFERENCES band_members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ai_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    band_id INTEGER NOT NULL,
    rehearsal_id INTEGER,
    performance_id INTEGER,
    result_type TEXT NOT NULL,
    content TEXT NOT NULL,
    created_by INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TEXT,
    FOREIGN KEY (band_id) REFERENCES bands(id) ON DELETE CASCADE,
    FOREIGN KEY (rehearsal_id) REFERENCES rehearsals(id) ON DELETE SET NULL,
    FOREIGN KEY (performance_id) REFERENCES performances(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_songs_band ON songs(band_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_lower ON users(lower(email));
CREATE UNIQUE INDEX IF NOT EXISTS idx_band_members_unique_user
ON band_members(band_id, user_id)
WHERE user_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_rehearsals_band_time ON rehearsals(band_id, start_time);
CREATE INDEX IF NOT EXISTS idx_rehearsal_song_reviews_rehearsal ON rehearsal_song_reviews(rehearsal_id);
CREATE INDEX IF NOT EXISTS idx_feedback_rehearsal ON feedback(rehearsal_id);
CREATE INDEX IF NOT EXISTS idx_performances_band_time ON performances(band_id, start_time);

CREATE TRIGGER IF NOT EXISTS songs_updated_at
AFTER UPDATE ON songs
FOR EACH ROW
BEGIN
    UPDATE songs SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
END;
