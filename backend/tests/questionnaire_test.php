<?php

declare(strict_types=1);

use BandPilot\Controllers\BandController;
use BandPilot\Controllers\QuestionnaireController;
use BandPilot\Support\Database;

$databasePath = tempnam(sys_get_temp_dir(), 'bandpilot-questionnaire-test-');
if ($databasePath === false) {
    fwrite(STDERR, "Could not create the questionnaire test database.\n");
    exit(1);
}

putenv('DB_PATH=' . $databasePath);
$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';
$database = Database::connection($projectRoot);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');

try {
    if ($schema === false || $seed === false) {
        throw new RuntimeException('Questionnaire test database files could not be read.');
    }
    $database->exec($schema);
    $database->exec($seed);

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $assertInvalid = static function (callable $callback, string $message): void {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            return;
        }
        throw new RuntimeException($message);
    };

    $controller = new QuestionnaireController($projectRoot);
    $bandController = new BandController($projectRoot);

    $empty = $controller->show(1, 1);
    $assert(array_key_exists('answers', $empty), 'A new questionnaire response should include the answers field.');
    $assert($empty['answers'] === null, 'A new questionnaire should not contain answers.');
    $assert(($empty['completion_percent'] ?? null) === 0, 'A new questionnaire should be 0% complete.');
    $assert(($empty['completed'] ?? null) === false, 'A new questionnaire should not be marked complete.');

    $firstInput = [
        'instrument' => 'Guitar',
        'genres' => 'Rock, Indie',
        'experience_level' => 'mixed',
        'main_goal' => 'performance',
        'rehearsal_frequency' => 'weekly',
        'session_minutes' => 120,
        'main_challenge' => 'timing',
        'notes' => 'Prepare a reliable thirty-minute set.',
    ];
    $firstSave = $controller->save(1, 1, $firstInput);
    $firstAnswers = $firstSave['answers'] ?? null;
    $assert(is_array($firstAnswers), 'The first save did not return questionnaire answers.');
    $assert(($firstSave['completion_percent'] ?? null) === 100, 'A saved questionnaire should be 100% complete.');
    $assert(($firstSave['completed'] ?? null) === true, 'A saved questionnaire should be marked complete.');
    $assert(($firstAnswers['genres'] ?? null) === 'Rock, Indie', 'Genres were not saved correctly.');
    $assert(($firstAnswers['instrument'] ?? null) === 'Guitar', 'The owner instrument was not returned.');
    $assert((int) ($firstAnswers['session_minutes'] ?? 0) === 120, 'The rehearsal length was not saved.');

    $savedInstrument = $database->query(
        'SELECT instrument FROM band_members WHERE band_id = 1 AND user_id = 1'
    )->fetchColumn();
    $assert($savedInstrument === 'Guitar', 'The questionnaire did not update the band-scoped instrument.');
    $savedMeta = $database->query(
        'SELECT updated_by, completed_at FROM band_questionnaires WHERE band_id = 1'
    )->fetch();
    $assert((int) ($savedMeta['updated_by'] ?? 0) === 1, 'The questionnaire did not record its editor.');
    $assert(($savedMeta['completed_at'] ?? '') !== '', 'The questionnaire did not record completion time.');

    $editedInput = [
        'instrument' => 'Lead guitar',
        'genres' => 'Alternative; Funk',
        'experience_level' => 'intermediate',
        'main_goal' => 'recording',
        'rehearsal_frequency' => 'twice_month',
        'session_minutes' => 90,
        'main_challenge' => 'teamwork',
        'notes' => 'Leave ten minutes for a final review.',
    ];
    $edited = $controller->save(1, 1, $editedInput);
    $editedAnswers = $edited['answers'] ?? null;
    $assert(($editedAnswers['genres'] ?? null) === 'Alternative, Funk', 'Edited genres were not normalized and saved.');
    $assert(($editedAnswers['main_goal'] ?? null) === 'recording', 'The edited goal was not saved.');
    $assert((int) ($editedAnswers['session_minutes'] ?? 0) === 90, 'The edited rehearsal length was not saved.');
    $assert(($editedAnswers['instrument'] ?? null) === 'Lead guitar', 'The edited instrument was not returned.');
    $rowCount = (int) $database->query('SELECT COUNT(*) FROM band_questionnaires WHERE band_id = 1')->fetchColumn();
    $assert($rowCount === 1, 'Editing created a duplicate questionnaire row.');

    $validInput = $editedInput;
    $missingInstrument = $validInput;
    $missingInstrument['instrument'] = '';
    $assertInvalid(
        static fn () => $controller->save(1, 1, $missingInstrument),
        'A questionnaire without an instrument was accepted.'
    );

    $tooManyGenres = $validInput;
    $tooManyGenres['genres'] = 'Rock, Pop, Jazz, Funk';
    $assertInvalid(
        static fn () => $controller->save(1, 1, $tooManyGenres),
        'A questionnaire with more than three genres was accepted.'
    );

    $invalidChoice = $validInput;
    $invalidChoice['experience_level'] = 'expert';
    $invalidChoice['session_minutes'] = 75;
    $assertInvalid(
        static fn () => $controller->save(1, 1, $invalidChoice),
        'Invalid questionnaire choices were accepted.'
    );

    $afterInvalid = $controller->show(1, 1)['answers'] ?? [];
    $assert(($afterInvalid['main_goal'] ?? null) === 'recording', 'Invalid input changed the saved questionnaire.');
    $assert(($afterInvalid['instrument'] ?? null) === 'Lead guitar', 'Invalid input changed the saved instrument.');

    $userStatement = $database->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
    );
    $userStatement->execute([
        'name' => 'Member Test',
        'email' => 'member@example.com',
        'password_hash' => password_hash('MemberPassword123!', PASSWORD_DEFAULT),
    ]);
    $memberUserId = (int) $database->lastInsertId();
    $membershipStatement = $database->prepare(
        "INSERT INTO band_members (band_id, user_id, display_name, role, instrument)
         VALUES (1, :user_id, 'Member Test', 'member', 'Drums')"
    );
    $membershipStatement->execute(['user_id' => $memberUserId]);

    $memberView = $controller->show(1, $memberUserId);
    $assert(($memberView['completed'] ?? null) === true, 'A band member could not read the questionnaire.');
    $assert(
        (($memberView['answers'] ?? [])['instrument'] ?? null) === 'Drums',
        'The questionnaire did not return the viewing member\'s band-scoped instrument.'
    );
    $assertInvalid(
        static fn () => $controller->save(1, $memberUserId, $validInput),
        'A non-owner was allowed to edit the band questionnaire.'
    );
    $afterMemberAttempt = $controller->show(1, 1)['answers'] ?? [];
    $assert(
        ($afterMemberAttempt['notes'] ?? null) === 'Leave ten minutes for a final review.',
        'A non-owner edit attempt changed the questionnaire.'
    );

    $secondBand = $bandController->create(1, [
        'name' => 'Second Questionnaire Band',
        'description' => 'Used to verify band isolation',
    ])['band'] ?? null;
    $secondBandId = (int) ($secondBand['id'] ?? 0);
    $assert($secondBandId > 1, 'The second test band was not created.');
    $emptySecondBand = $controller->show($secondBandId, 1);
    $assert(array_key_exists('answers', $emptySecondBand), 'The second band response should include the answers field.');
    $assert($emptySecondBand['answers'] === null, 'A new band inherited another band\'s questionnaire.');

    $secondInput = [
        'instrument' => 'Bass',
        'genres' => 'Jazz',
        'experience_level' => 'advanced',
        'main_goal' => 'competition',
        'rehearsal_frequency' => 'monthly',
        'session_minutes' => 180,
        'main_challenge' => 'availability',
        'notes' => 'Second band answers must stay separate.',
    ];
    $secondSaved = $controller->save($secondBandId, 1, $secondInput)['answers'] ?? [];
    $assert(($secondSaved['genres'] ?? null) === 'Jazz', 'The second band questionnaire was not saved.');
    $assert(($secondSaved['instrument'] ?? null) === 'Bass', 'The second band instrument was not saved.');

    $firstBandAgain = $controller->show(1, 1)['answers'] ?? [];
    $assert(($firstBandAgain['genres'] ?? null) === 'Alternative, Funk', 'The second band changed the first band genres.');
    $assert(($firstBandAgain['instrument'] ?? null) === 'Lead guitar', 'The second band changed the first band instrument.');
    $assert(($firstBandAgain['notes'] ?? null) === 'Leave ten minutes for a final review.', 'Band answers leaked across bands.');

    $questionnaireCount = (int) $database->query('SELECT COUNT(*) FROM band_questionnaires')->fetchColumn();
    $assert($questionnaireCount === 2, 'Questionnaires were not stored as one row per band.');

    fwrite(STDOUT, "BandPilot questionnaire test passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    unset($database);
    if (is_file($databasePath)) {
        unlink($databasePath);
    }
}
