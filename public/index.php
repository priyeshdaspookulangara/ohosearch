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
use App\Controllers\OfferingController;
use App\Controllers\CouponController;
use App\Controllers\InteractionController;
use App\Controllers\PaymentController;
use App\Controllers\MediaController;
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

// Twig
$twig = Twig::create(__DIR__ . '/../views', ['cache' => false]);

// Middleware to inject CSRF and Session into Twig
$app->add(function ($request, $handler) use ($twig, $csrf) {
    // Generate new tokens if they don't exist
    $nameKey = $csrf->getTokenNameKey();
    $valueKey = $csrf->getTokenValueKey();
    $name = $csrf->getTokenName();
    $value = $csrf->getTokenValue();

    $twig->getEnvironment()->addGlobal('session', $_SESSION);
    $twig->getEnvironment()->addGlobal('csrf', [
        'nameKey'  => $nameKey,
        'valueKey' => $valueKey,
        'name'     => $name,
        'value'    => $value
    ]);
    return $handler->handle($request);
});

$app->add(TwigMiddleware::create($app, $twig));
$app->add($csrf);

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

        $query = "SELECT b.*, (SELECT name FROM categories c JOIN business_categories bc ON c.id = bc.category_id WHERE bc.business_id = b.id LIMIT 1) as category_name FROM businesses b WHERE 1=1";
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
    $group->post('/businesses/{id:[0-9]+}/claim', [BusinessController::class, 'submitClaim']);

    // Achievements
    $group->get('/businesses/{id:[0-9]+}/achievements', [BusinessController::class, 'achievements']);
    $group->post('/businesses/{id:[0-9]+}/achievements', [BusinessController::class, 'storeAchievement']);

    // Offerings
    $group->get('/businesses/{business_id:[0-9]+}/offerings', [OfferingController::class, 'index']);
    $group->post('/businesses/{business_id:[0-9]+}/offerings', [OfferingController::class, 'store']);
    $group->post('/businesses/{business_id:[0-9]+}/offerings/{id:[0-9]+}/delete', [OfferingController::class, 'delete']);
    $group->post('/businesses/{business_id:[0-9]+}/offerings/{id:[0-9]+}/flagship', [OfferingController::class, 'toggleFlagship']);

    // Coupons (Requester)
    $group->post('/coupons/request', [CouponController::class, 'requestCoupon']);

    // Interactions
    $group->get('/enquiries', [InteractionController::class, 'listEnquiries']);
    $group->post('/businesses/{id:[0-9]+}/review', [InteractionController::class, 'submitReview']);
    $group->post('/businesses/{id:[0-9]+}/favorite', [InteractionController::class, 'toggleFavorite']);
    $group->post('/businesses/{id:[0-9]+}/enquiry', [InteractionController::class, 'sendEnquiry']);
    $group->post('/reviews/{id:[0-9]+}/reply', [InteractionController::class, 'replyToReview']);
    $group->post('/support/tickets', [InteractionController::class, 'raiseTicket']);

    // Payments
    $group->post('/payments/initiate', [PaymentController::class, 'initiatePayment']);

    // Admin only
    $group->group('', function ($adminGroup) {
        $adminGroup->get('/categories', [CategoryController::class, 'index']);
        $adminGroup->post('/categories', [CategoryController::class, 'store']);
        $adminGroup->post('/categories/{id:[0-9]+}/delete', [CategoryController::class, 'delete']);

        $adminGroup->get('/users', [UserController::class, 'index']);
        $adminGroup->post('/users', [UserController::class, 'store']);
        $adminGroup->post('/users/{id:[0-9]+}/delete', [UserController::class, 'delete']);

        // Admin Coupons
        $adminGroup->get('/admin/coupons/requests', [CouponController::class, 'listRequests']);
        $adminGroup->post('/admin/coupons/requests/{id:[0-9]+}/approve', [CouponController::class, 'approveRequest']);

        // Claims
        $adminGroup->get('/admin/claims', [BusinessController::class, 'listClaimRequests']);
        $adminGroup->post('/admin/claims/{id:[0-9]+}/approve', [BusinessController::class, 'approveClaim']);

        // Review Management
        $adminGroup->get('/admin/businesses/{id:[0-9]+}/reviews', [InteractionController::class, 'listBusinessReviews']);

        // Media Management
        $adminGroup->get('/admin/media', [MediaController::class, 'index']);
        $adminGroup->post('/admin/media/{type}/{id}/delete', [MediaController::class, 'delete']);

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


// Error handling
$app->addErrorMiddleware(true, true, true);

$app->run();
