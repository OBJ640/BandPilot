<?php

declare(strict_types=1);

$htmlPath = dirname(__DIR__, 2) . '/public/index.html';
$javascriptPath = dirname(__DIR__, 2) . '/public/assets/js/app.js';
$html = file_get_contents($htmlPath);
$javascript = file_get_contents($javascriptPath);
if ($html === false || $javascript === false) {
    fwrite(STDERR, "Could not read the website HTML or JavaScript.\n");
    exit(1);
}

$knownButtonIds = [
    'useDemoButton', 'logoutButton',
    'changeBandButton', 'finishSetupButton', 'profileButton', 'menuButton',
    'notificationsButton', 'addSongButton', 'viewNotesButton',
    'addPerformanceButton', 'editSetlistButton', 'editPlanButton',
    'approvePlanButton', 'closeSongModal', 'cancelSongModal',
    'createBandButton', 'editBandButton',
    'manageMembersButton', 'addMemberButton', 'removeMemberButton',
    'archiveSongButton', 'cancelRehearsalButton', 'reviewHistoryButton',
    'questionnaireBackButton', 'questionnaireNextButton', 'saveQuestionnaireButton',
];

$requiredElementIds = [
    'authScreen', 'appShell', 'loginForm', 'registerForm',
    'logoutButton', 'questionnaireModal', 'questionnaireForm',
    'questionnaireBackButton', 'questionnaireNextButton', 'saveQuestionnaireButton',
    'rehearsalTimeline', 'rehearsalReviewModal', 'rehearsalReviewForm',
    'reviewSongPicker', 'reviewSongForms', 'reviewSongCount',
    'memberManagerModal', 'memberFormModal', 'rehearsalSongPicker',
    'availabilityModal', 'availabilityForm', 'reviewHistoryModal', 'reviewHistoryList',
];

$requiredJavascriptSnippets = [
    'rehearsal review submit handler' => "document.getElementById('rehearsalReviewForm').addEventListener('submit', saveRehearsalReview)",
    'rehearsal review save function' => 'async function saveRehearsalReview(event)',
    'dynamic rehearsal review button' => 'data-rehearsal-review=',
    'dynamic rehearsal review button binding' => "timeline.querySelectorAll('[data-rehearsal-review]')",
    'dynamic rehearsal review opener' => 'openRehearsalReview(Number(button.dataset.rehearsalReview))',
    'five progress levels' => "['starting', 'Just starting']",
    'song progress level payload' => "progress_level: String(input.progress_level || 'starting')",
    'review progress level payload' => "progress_level_after: value('progress_level_after')",
    'member manager' => 'async function openMemberManager()',
    'song edit form' => 'function openSongForm(mode, songId = null)',
    'rehearsal song selection' => 'function renderRehearsalSongPicker(selectedIds = [])',
    'availability form' => 'async function openAvailability(rehearsalId)',
    'review history' => 'async function openReviewHistory()',
];

$document = new DOMDocument();
libxml_use_internal_errors(true);
$document->loadHTML($html);
libxml_clear_errors();

$deadButtons = [];
foreach ($document->getElementsByTagName('button') as $button) {
    $id = $button->getAttribute('id');
    $type = strtolower($button->getAttribute('type'));
    $hasEventPath = $type === 'submit'
        || in_array($id, $knownButtonIds, true)
        || $button->hasAttribute('data-view')
        || $button->hasAttribute('data-view-link')
        || $button->hasAttribute('data-action')
        || $button->hasAttribute('data-close-dialog')
        || $button->hasAttribute('data-song-filter')
        || $button->hasAttribute('data-rehearsal-filter')
        || $button->hasAttribute('data-ai-suggestion')
        || $button->hasAttribute('data-auth-mode');

    if (!$hasEventPath) {
        $deadButtons[] = trim($button->textContent) ?: $id ?: '(unnamed button)';
    }
}

$missingElements = [];
foreach ($requiredElementIds as $id) {
    if ($document->getElementById($id) === null) {
        $missingElements[] = $id;
    }
}

$missingJavascript = [];
foreach ($requiredJavascriptSnippets as $label => $snippet) {
    if (!str_contains($javascript, $snippet)) {
        $missingJavascript[] = $label;
    }
}

$authModes = [];
foreach ($document->getElementsByTagName('button') as $button) {
    if ($button->hasAttribute('data-auth-mode')) {
        $authModes[] = $button->getAttribute('data-auth-mode');
    }
}
sort($authModes);
if ($authModes !== ['login', 'register']) {
    fwrite(STDERR, 'Auth mode buttons must include login and register exactly once.\n');
    exit(1);
}

if ($deadButtons !== []) {
    fwrite(STDERR, 'Buttons without an event path: ' . implode(', ', $deadButtons) . "\n");
    exit(1);
}

if ($missingElements !== []) {
    fwrite(STDERR, 'Missing required frontend elements: ' . implode(', ', $missingElements) . "\n");
    exit(1);
}

if ($missingJavascript !== []) {
    fwrite(STDERR, 'Missing rehearsal review JavaScript contracts: ' . implode(', ', $missingJavascript) . "\n");
    exit(1);
}

$progressSelect = $document->getElementsByTagName('select');
$hasSongProgressSelect = false;
foreach ($progressSelect as $select) {
    if ($select->getAttribute('name') === 'progress_level') {
        $hasSongProgressSelect = true;
        break;
    }
}
if (!$hasSongProgressSelect || str_contains($html, 'name="progress" type="number"')) {
    fwrite(STDERR, "Song progress must use the five-level select instead of a number input.\n");
    exit(1);
}

fwrite(STDOUT, "BandPilot frontend button contract test passed.\n");
