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

        $query = "SELECT b.*, c.name as category_name";
        $args = [];

        if ($lat && $lng) {
            // Haversine formula
            $query .= ", (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance";
            $args[] = $lat;
            $args[] = $lng;
            $args[] = $lat;
        }

        $query .= " FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.status = 'live'";

        if ($keyword) {
            $query .= " AND (b.name LIKE ? OR b.description LIKE ?)";
            $args[] = "%$keyword%";
            $args[] = "%$keyword%";
        }

        if ($categoryId) {
            $query .= " AND b.category_id = ?";
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
        $stmt = $db->prepare("SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
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
            'gallery' => $gallery
        ]);
    }
}
