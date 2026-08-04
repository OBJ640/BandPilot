INSERT OR IGNORE INTO users (id, name, email, password_hash)
VALUES (1, 'Ricky', 'demo@bandpilot.local', '$2y$12$/XOcD0.FW9u.6MeW1eWUhOgEZ9Sb8otcLFMJzd1GVUwZgqTWy8W/6');

UPDATE users
SET password_hash = '$2y$12$/XOcD0.FW9u.6MeW1eWUhOgEZ9Sb8otcLFMJzd1GVUwZgqTWy8W/6'
WHERE id = 1 AND password_hash = '$2y$10$demo.password.hash.not.for.login';

INSERT OR IGNORE INTO bands (id, name, description, owner_id)
VALUES (1, 'Neon Birds', 'A student band', 1);

INSERT OR IGNORE INTO band_members (id, band_id, user_id, display_name, role, instrument)
VALUES
    (1, 1, 1, 'Ricky', 'owner', 'Guitar'),
    (2, 1, NULL, 'Amy', 'member', 'Vocals'),
    (3, 1, NULL, 'Jay', 'member', 'Drums'),
    (4, 1, NULL, 'Sam', 'member', 'Bass');

INSERT OR IGNORE INTO songs (id, band_id, title, artist, progress, progress_level, status, problem_notes)
VALUES
    (1, 1, 'Little Wing', 'Jimi Hendrix', 50, 'rehearsing', 'practising', 'Chorus entry and timing'),
    (2, 1, 'Everlong', 'Foo Fighters', 75, 'polishing', 'practising', 'Bridge dynamics'),
    (3, 1, 'Dreams', 'Fleetwood Mac', 75, 'polishing', 'practising', 'Full-band balance'),
    (4, 1, 'Yellow', 'Coldplay', 100, 'ready', 'ready', 'Final run-through'),
    (5, 1, '505', 'Arctic Monkeys', 50, 'rehearsing', 'learning', 'Ending transition'),
    (6, 1, 'Creep', 'Radiohead', 100, 'ready', 'ready', 'Performance ready');

INSERT OR IGNORE INTO rehearsals (id, band_id, title, start_time, duration_minutes, location, goals, status)
VALUES
    (1, 1, 'Saturday full-band practice', '2026-08-08T14:00:00+08:00', 120, 'School music room', 'Improve the chorus and practise the full set', 'planned'),
    (2, 1, 'Friday rhythm section', '2026-08-01T16:30:00+08:00', 90, 'Music classroom B', 'Work on timing and dynamics', 'completed');

INSERT OR IGNORE INTO rehearsal_songs (rehearsal_id, song_id, planned_minutes, order_number)
VALUES
    (1, 1, 30, 1), (1, 2, 25, 2), (1, 3, 20, 3),
    (2, 1, 30, 1), (2, 2, 30, 2), (2, 3, 30, 3);
