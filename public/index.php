<?php

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Database;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\BusinessController;
use App\Controllers\UserController;
use App\Controllers\PublicController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use Slim\Csrf\Guard;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$responseFactory = $app->getResponseFactory();

// CSRF Protection
$csrf = new Guard($responseFactory);
$csrf->setPersistentTokenMode(true);
$csrf->setFailureHandler(function ($request, $handler) {
    $response = $handler->handle($request);
    $response->getBody()->write('CSRF failure');
    return $response->withStatus(400);
});
$app->add($csrf);

// Twig
$twig = Twig::create(__DIR__ . '/../views', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// Middleware to inject CSRF and Session into Twig
$app->add(function ($request, $handler) use ($twig, $csrf) {
    $twig->getEnvironment()->addGlobal('csrf', [
        'nameKey' => $csrf->getTokenNameKey(),
        'valueKey' => $csrf->getTokenValueKey(),
        'name' => $csrf->getTokenName(),
        'value' => $csrf->getTokenValue(),
    ]);
    $twig->getEnvironment()->addGlobal('session', $_SESSION);
    return $handler->handle($request);
});

// Public Routes
$app->get('/', [PublicController::class, 'home']);
$app->get('/search', [PublicController::class, 'search']);

// Auth
$app->get('/login', [AuthController::class, 'showLogin']);
$app->post('/login', [AuthController::class, 'login']);
$app->get('/logout', [AuthController::class, 'logout']);

// Dashboard & Protected Routes
$app->group('', function ($group) {
    $group->get('/dashboard', function ($request, $response, $args) {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['user_role'];

        $params = $request->getQueryParams();
        $statusFilter = $params['status'] ?? '';
        $q = $params['q'] ?? '';

        $query = "SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE 1=1";
        $whereArgs = [];

        if ($role !== 'admin') {
            $query .= " AND b.user_id = ?";
            $whereArgs[] = $userId;
        }

        if ($statusFilter) {
            $query .= " AND b.status = ?";
            $whereArgs[] = $statusFilter;
        }

        if ($q) {
            $query .= " AND (b.name LIKE ? OR b.address LIKE ?)";
            $whereArgs[] = "%$q%";
            $whereArgs[] = "%$q%";
        }

        $query .= " ORDER BY b.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($whereArgs);
        $listings = $stmt->fetchAll();

        // Stats
        $stats = [
            'total' => $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn(),
            'pending' => $db->query("SELECT COUNT(*) FROM businesses WHERE status = 'pending_approval'")->fetchColumn(),
            'categories' => $db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        ];

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/dashboard.twig', [
            'listings' => $listings,
            'stats' => $stats,
            'filters' => ['status' => $statusFilter, 'q' => $q]
        ]);
    });

    $group->get('/businesses/create', [BusinessController::class, 'create']);
    $group->post('/businesses/create', [BusinessController::class, 'store']);
    $group->get('/businesses/{id:[0-9]+}/edit', [BusinessController::class, 'edit']);
    $group->post('/businesses/{id:[0-9]+}/edit', [BusinessController::class, 'update']);
    $group->post('/businesses/{id:[0-9]+}/delete', [BusinessController::class, 'delete']);

    // Admin only
    $group->group('', function ($adminGroup) {
        $adminGroup->get('/categories', [CategoryController::class, 'index']);
        $adminGroup->post('/categories', [CategoryController::class, 'store']);
        $adminGroup->post('/categories/{id:[0-9]+}/delete', [CategoryController::class, 'delete']);

        $adminGroup->get('/users', [UserController::class, 'index']);
        $adminGroup->post('/users', [UserController::class, 'store']);
        $adminGroup->post('/users/{id:[0-9]+}/delete', [UserController::class, 'delete']);

        $adminGroup->post('/businesses/{id:[0-9]+}/approve', function ($request, $response, $args) {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE businesses SET status = 'live' WHERE id = ?");
            $stmt->execute([$args['id']]);
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        });
    })->add(new AdminMiddleware());

})->add(new AuthMiddleware());

// This must come after specific /businesses/... routes
$app->get('/businesses/{id:[0-9]+}', [PublicController::class, 'showBusiness']);

// Static uploads with basic path sanitization
$app->get('/uploads/{type}/{file}', function ($request, $response, $args) {
    $type = preg_replace('/[^a-z0-9]/', '', $args['type']);
    $file = preg_replace('/[^a-z0-9\._-]/i', '', $args['file']);

    $path = __DIR__ . '/../uploads/' . $type . '/' . $file;
    if (file_exists($path)) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream'
        };
        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', $mimeType);
    }
    return $response->withStatus(404);
});

// Error handling
$app->addErrorMiddleware(true, true, true);

$app->run();
