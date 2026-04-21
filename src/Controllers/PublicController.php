<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class PublicController
{
    public function home(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'public/home.twig', ['categories' => $categories]);
    }

    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $keyword = $params['q'] ?? '';
        $categoryId = $params['category'] ?? '';
        $lat = $params['lat'] ?? null;
        $lng = $params['lng'] ?? null;
        $radius = $params['radius'] ?? 50; // km

        $db = Database::getInstance();

        // Select one primary category name for the listing view
        $query = "SELECT b.*, (SELECT name FROM categories c JOIN business_categories bc ON c.id = bc.category_id WHERE bc.business_id = b.id LIMIT 1) as category_name";
        $args = [];

        if ($lat && $lng) {
            // Haversine formula
            $query .= ", (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance";
            $args[] = $lat;
            $args[] = $lng;
            $args[] = $lat;
        }

        $query .= " FROM businesses b WHERE b.status = 'live'";

        if ($keyword) {
            $query .= " AND (b.name LIKE ? OR b.description LIKE ?)";
            $args[] = "%$keyword%";
            $args[] = "%$keyword%";
        }

        if ($categoryId) {
            $query .= " AND b.id IN (SELECT business_id FROM business_categories WHERE category_id = ?)";
            $args[] = $categoryId;
        }

        if ($lat && $lng) {
            $query .= " HAVING distance < ?";
            $args[] = $radius;
            $query .= " ORDER BY distance ASC";
        } else {
            $query .= " ORDER BY b.created_at DESC";
        }

        $stmt = $db->prepare($query);
        $stmt->execute($args);
        $results = $stmt->fetchAll();

        $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'public/search_results.twig', [
            'results' => $results,
            'categories' => $categories,
            'q' => $keyword,
            'category_id' => $categoryId
        ]);
    }

    public function showBusiness(Request $request, Response $response, array $args): Response
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT b.* FROM businesses b WHERE b.id = ?");
        $stmt->execute([$args['id']]);
        $business = $stmt->fetch();

        if (!$business) {
            return $response->withStatus(404);
        }

        // Working hours
        $stmt = $db->prepare("SELECT * FROM working_hours WHERE business_id = ?");
        $stmt->execute([$args['id']]);
        $hours = $stmt->fetchAll();

        // Gallery
        $stmt = $db->prepare("SELECT * FROM business_gallery WHERE business_id = ?");
        $stmt->execute([$args['id']]);
        $gallery = $stmt->fetchAll();

        // Offerings
        $stmt = $db->prepare("SELECT * FROM offerings WHERE business_id = ?");
        $stmt->execute([$args['id']]);
        $offerings = $stmt->fetchAll();

        // Reviews
        $stmt = $db->prepare("SELECT r.*, u.name as user_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$args['id']]);
        $reviews = $stmt->fetchAll();

        // Achievements
        $stmt = $db->prepare("SELECT * FROM achievements WHERE business_id = ? ORDER BY date_awarded DESC");
        $stmt->execute([$args['id']]);
        $achievements = $stmt->fetchAll();

        // Categories
        $stmt = $db->prepare("SELECT c.name FROM categories c JOIN business_categories bc ON c.id = bc.category_id WHERE bc.business_id = ?");
        $stmt->execute([$args['id']]);
        $businessCategories = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Average Rating
        $stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE business_id = ?");
        $stmt->execute([$args['id']]);
        $ratingStats = $stmt->fetch();

        // Reorder hours to start from Monday
        $daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $orderedHours = [];
        foreach ($daysOrder as $day) {
            $found = false;
            foreach ($hours as $h) {
                if ($h['day_of_week'] === $day) {
                    $orderedHours[] = $h;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $orderedHours[] = ['day_of_week' => $day, 'is_closed' => 1];
            }
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'public/business_profile.twig', [
            'business' => $business,
            'hours' => $orderedHours,
            'gallery' => $gallery,
            'offerings' => $offerings,
            'reviews' => $reviews,
            'rating_stats' => $ratingStats,
            'achievements' => $achievements,
            'business_categories' => $businessCategories
        ]);
    }
}
