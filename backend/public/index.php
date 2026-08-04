<?php

declare(strict_types=1);

use BandPilot\Controllers\AiController;
use BandPilot\Controllers\AiResultController;
use BandPilot\Controllers\AuthController;
use BandPilot\Controllers\BandController;
use BandPilot\Controllers\AvailabilityController;
use BandPilot\Controllers\HealthController;
use BandPilot\Controllers\MemberController;
use BandPilot\Controllers\PerformanceController;
use BandPilot\Controllers\QuestionnaireController;
use BandPilot\Controllers\RehearsalController;
use BandPilot\Controllers\RehearsalReviewController;
use BandPilot\Controllers\SongController;
use BandPilot\Controllers\UserController;
use BandPilot\Support\Auth;
use BandPilot\Support\Database;
use BandPilot\Support\HttpException;
use BandPilot\Support\Response;

$projectRoot = require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
Auth::start();
$currentUserId = Auth::currentUserId();

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if ($method === 'GET' && $path === '/api/v1/health') {
        Response::json((new HealthController($projectRoot))->show());
    }

    $authController = new AuthController($projectRoot);

    if ($method === 'GET' && $path === '/api/v1/auth/session') {
        if ($currentUserId === null) {
            Response::json([
                'authenticated' => false,
                'csrf_token' => Auth::csrfToken(),
            ]);
        }
        $result = $authController->session($currentUserId);
        Response::json([
            'authenticated' => true,
            'user' => $result['user'],
            'csrf_token' => Auth::csrfToken(),
        ]);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/register') {
        $result = $authController->register(Response::input());
        Auth::signIn((int) $result['user']['id']);
        Response::json([
            'authenticated' => true,
            'user' => $result['user'],
            'csrf_token' => Auth::csrfToken(),
        ], 201);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/login') {
        $result = $authController->login(Response::input());
        Auth::signIn((int) $result['user']['id']);
        Response::json([
            'authenticated' => true,
            'user' => $result['user'],
            'csrf_token' => Auth::csrfToken(),
        ]);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/logout') {
        if ($currentUserId !== null) {
            Auth::requireCsrfToken();
        }
        Auth::signOut();
        Response::json(['authenticated' => false]);
    }

    if ($currentUserId === null) {
        Response::json(['error' => 'Please sign in to continue.'], 401);
    }

    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        Auth::requireCsrfToken();
    }

    if ($path === '/api/v1/users/me') {
        $bandId = isset($_GET['band_id']) && (int) $_GET['band_id'] > 0
            ? (int) $_GET['band_id']
            : null;
        if ($bandId !== null) {
            (new BandController($projectRoot))->show($bandId, $currentUserId);
        }
        $controller = new UserController($projectRoot);
        if ($method === 'GET') {
            Response::json($controller->show($currentUserId, $bandId));
        }
        if ($method === 'PATCH') {
            if ($bandId === null) {
                throw new InvalidArgumentException('Choose a band before editing your profile.');
            }
            Response::json($controller->update($currentUserId, $bandId, Response::input()));
        }
    }

    if ($path === '/api/v1/bands') {
        $controller = new BandController($projectRoot);
        if ($method === 'GET') {
            Response::json($controller->index($currentUserId));
        }
        if ($method === 'POST') {
            Response::json($controller->create($currentUserId, Response::input()), 201);
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)$#', $path, $matches)) {
        $controller = new BandController($projectRoot);
        $bandId = (int) $matches[1];
        if ($method === 'GET') {
            Response::json($controller->show($bandId, $currentUserId));
        }
        if ($method === 'PATCH') {
            Response::json($controller->update($bandId, $currentUserId, Response::input()));
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/questionnaire$#', $path, $matches)) {
        $controller = new QuestionnaireController($projectRoot);
        $bandId = (int) $matches[1];
        if ($method === 'GET') {
            Response::json($controller->show($bandId, $currentUserId));
        }
        if ($method === 'PUT') {
            Response::json($controller->save($bandId, $currentUserId, Response::input()));
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/members$#', $path, $matches)) {
        $controller = new MemberController($projectRoot);
        $bandId = (int) $matches[1];
        if ($method === 'GET') {
            Response::json($controller->index($bandId, $currentUserId));
        }
        if ($method === 'POST') {
            Response::json($controller->create($bandId, $currentUserId, Response::input()), 201);
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/members/(\d+)$#', $path, $matches)) {
        $controller = new MemberController($projectRoot);
        $bandId = (int) $matches[1];
        $memberId = (int) $matches[2];
        if ($method === 'PATCH') {
            Response::json($controller->update($bandId, $memberId, $currentUserId, Response::input()));
        }
        if ($method === 'DELETE') {
            Response::json($controller->remove($bandId, $memberId, $currentUserId));
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/songs$#', $path, $matches)) {
        $controller = new SongController($projectRoot);
        $bandId = (int) $matches[1];
        (new BandController($projectRoot))->show($bandId, $currentUserId);

        if ($method === 'GET') {
            Response::json($controller->index($bandId));
        }
        if ($method === 'POST') {
            Response::json($controller->create($bandId, Response::input()), 201);
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/songs/(\d+)$#', $path, $matches)) {
        $controller = new SongController($projectRoot);
        $bandId = (int) $matches[1];
        $songId = (int) $matches[2];
        if ($method === 'PATCH') {
            Response::json($controller->update($bandId, $songId, $currentUserId, Response::input()));
        }
        if ($method === 'DELETE') {
            Response::json($controller->archive($bandId, $songId, $currentUserId));
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/rehearsals$#', $path, $matches)) {
        $bandId = (int) $matches[1];
        (new BandController($projectRoot))->show($bandId, $currentUserId);
        $controller = new RehearsalController($projectRoot);
        if ($method === 'GET') {
            Response::json($controller->index($bandId));
        }
        if ($method === 'POST') {
            Response::json($controller->create($bandId, $currentUserId, Response::input()), 201);
        }
    }

    if (preg_match('#^/api/v1/bands/(\d+)/rehearsals/(\d+)$#', $path, $matches)) {
        $controller = new RehearsalController($projectRoot);
        $bandId = (int) $matches[1];
        $rehearsalId = (int) $matches[2];
        if ($method === 'PATCH') {
            Response::json($controller->update($bandId, $rehearsalId, $currentUserId, Response::input()));
        }
        if ($method === 'DELETE') {
            Response::json($controller->cancel($bandId, $rehearsalId, $currentUserId));
        }
    }

    if (preg_match('#^/api/v1/rehearsals/(\d+)/availability$#', $path, $matches) && $method === 'GET') {
        Response::json((new AvailabilityController($projectRoot))->show((int) $matches[1], $currentUserId));
    }

    if (preg_match('#^/api/v1/rehearsals/(\d+)/availability/(\d+)$#', $path, $matches) && $method === 'PUT') {
        Response::json((new AvailabilityController($projectRoot))->save(
            (int) $matches[1], (int) $matches[2], $currentUserId, Response::input()
        ));
    }

    if (preg_match('#^/api/v1/bands/(\d+)/review-history$#', $path, $matches) && $method === 'GET') {
        Response::json((new RehearsalReviewController($projectRoot))->history((int) $matches[1], $currentUserId));
    }

    if (preg_match('#^/api/v1/rehearsals/(\d+)/review$#', $path, $matches)) {
        $controller = new RehearsalReviewController($projectRoot);
        $rehearsalId = (int) $matches[1];
        if ($method === 'GET') {
            Response::json($controller->show($rehearsalId, $currentUserId));
        }
        if ($method === 'PUT') {
            Response::json($controller->save($rehearsalId, $currentUserId, Response::input()));
        }
    }

    if ($method === 'POST' && preg_match('#^/api/v1/bands/(\d+)/performances$#', $path, $matches)) {
        (new BandController($projectRoot))->show((int) $matches[1], $currentUserId);
        Response::json(
            (new PerformanceController($projectRoot))->create((int) $matches[1], Response::input()),
            201
        );
    }

    if ($method === 'POST' && preg_match('#^/api/v1/bands/(\d+)/ai-results$#', $path, $matches)) {
        (new BandController($projectRoot))->show((int) $matches[1], $currentUserId);
        Response::json(
            (new AiResultController($projectRoot))->approve((int) $matches[1], Response::input()),
            201
        );
    }

    if ($method === 'POST' && preg_match('#^/api/v1/rehearsals/(\d+)/ai-plan$#', $path, $matches)) {
        $rehearsalId = (int) $matches[1];
        $statement = Database::connection($projectRoot)->prepare('SELECT band_id FROM rehearsals WHERE id = :id');
        $statement->execute(['id' => $rehearsalId]);
        $bandId = (int) ($statement->fetchColumn() ?: 0);
        if ($bandId < 1) {
            throw new InvalidArgumentException('Rehearsal not found.');
        }
        (new BandController($projectRoot))->show($bandId, $currentUserId);
        Response::json((new AiController($projectRoot))->rehearsalPlan($rehearsalId));
    }

    Response::json(['error' => 'Route not found.'], 404);
} catch (HttpException $exception) {
    Response::json(['error' => $exception->getMessage()], $exception->status());
} catch (InvalidArgumentException $exception) {
    Response::json(['error' => $exception->getMessage()], 400);
} catch (RuntimeException $exception) {
    Response::json(['error' => $exception->getMessage()], 503);
} catch (Throwable $exception) {
    $debug = BandPilot\Support\Env::get('APP_DEBUG', 'false') === 'true';
    Response::json([
        'error' => $debug ? $exception->getMessage() : 'The server could not complete the request.',
    ], 500);
}
