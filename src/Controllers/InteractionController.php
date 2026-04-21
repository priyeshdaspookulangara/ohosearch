<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class InteractionController
{
    public function submitReview(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['id'];
        $data = $request->getParsedBody();
        $db = Database::getInstance();

        $stmt = $db->prepare("INSERT INTO reviews (business_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $businessId,
            $_SESSION['user_id'] ?? null,
            $data['rating'],
            $data['comment']
        ]);

        return $response->withHeader('Location', "/businesses/$businessId")->withStatus(302);
    }

    public function toggleFavorite(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['id'];
        $offeringId = $request->getParsedBody()['offering_id'] ?? null;
        $userId = $_SESSION['user_id'];
        $db = Database::getInstance();

        $check = $db->prepare("SELECT id FROM favorites WHERE user_id = ? AND business_id = ? AND (offering_id = ? OR (offering_id IS NULL AND ? IS NULL))");
        $check->execute([$userId, $businessId, $offeringId, $offeringId]);
        $favorite = $check->fetch();

        if ($favorite) {
            $db->prepare("DELETE FROM favorites WHERE id = ?")->execute([$favorite['id']]);
        } else {
            $db->prepare("INSERT INTO favorites (user_id, business_id, offering_id) VALUES (?, ?, ?)")
               ->execute([$userId, $businessId, $offeringId]);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function sendEnquiry(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['id'];
        $data = $request->getParsedBody();
        $db = Database::getInstance();

        $stmt = $db->prepare("INSERT INTO enquiries (business_id, user_id, customer_name, customer_email, customer_phone, message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $businessId,
            $_SESSION['user_id'] ?? null,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['message']
        ]);

        // Notify Owner
        $owner = $db->prepare("SELECT user_id FROM businesses WHERE id = ?");
        $owner->execute([$businessId]);
        $ownerId = $owner->fetchColumn();

        $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")
           ->execute([$ownerId, 'New Enquiry', 'You have received a new business enquiry.', 'enquiry']);

        return $response->withHeader('Location', "/businesses/$businessId")->withStatus(302);
    }

    public function raiseTicket(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $db = Database::getInstance();

        $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $data['subject'],
            $data['message'],
            $data['priority'] ?? 'normal'
        ]);

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function listEnquiries(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $query = "SELECT e.*, b.name as business_name FROM enquiries e
                  JOIN businesses b ON e.business_id = b.id ";

        if ($_SESSION['user_role'] !== 'admin') {
            $query .= " WHERE b.user_id = ? ";
            $query .= " ORDER BY e.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$userId]);
        } else {
            $query .= " ORDER BY e.created_at DESC";
            $stmt = $db->query($query);
        }

        $enquiries = $stmt->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/enquiries.twig', ['enquiries' => $enquiries]);
    }

    public function listBusinessReviews(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['id'];
        $db = Database::getInstance();

        // Check if business exists
        $stmt = $db->prepare("SELECT name FROM businesses WHERE id = ?");
        $stmt->execute([$businessId]);
        $businessName = $stmt->fetchColumn();

        if (!$businessName) {
            return $response->withStatus(404);
        }

        $stmt = $db->prepare("SELECT r.*, u.name as user_name FROM reviews r
                              LEFT JOIN users u ON r.user_id = u.id
                              WHERE r.business_id = ?
                              ORDER BY r.created_at DESC");
        $stmt->execute([$businessId]);
        $reviews = $stmt->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/business_reviews.twig', [
            'reviews' => $reviews,
            'business_name' => $businessName,
            'business_id' => $businessId
        ]);
    }
}
