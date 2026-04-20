<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CouponController
{
    // For Owners/Contributors to request a coupon
    public function requestCoupon(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $db = Database::getInstance();

        $stmt = $db->prepare("INSERT INTO coupon_requests (requester_id, business_id, purpose) VALUES (?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $data['business_id'] ?? null,
            $data['purpose'] ?? 'featured'
        ]);

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    // Admin view of requests
    public function listRequests(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $requests = $db->query("SELECT cr.*, u.name as user_name, b.name as business_name FROM coupon_requests cr
                                JOIN users u ON cr.requester_id = u.id
                                LEFT JOIN businesses b ON cr.business_id = b.id
                                ORDER BY cr.created_at DESC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/coupon_requests.twig', ['requests' => $requests]);
    }

    // Admin approve request and generate coupon
    public function approveRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = $args['id'];
        $db = Database::getInstance();

        $req = $db->prepare("SELECT * FROM coupon_requests WHERE id = ?");
        $req->execute([$requestId]);
        $req = $req->fetch();

        if (!$req) return $response->withStatus(404);

        $code = strtoupper(bin2hex(random_bytes(4)));

        $db->beginTransaction();
        try {
            // Create Coupon
            $stmt = $db->prepare("INSERT INTO coupons (code, discount_value, discount_type, purpose, assigned_to_user_id, business_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, 100, 'percentage', $req['purpose'], $req['requester_id'], $req['business_id']]);

            // Update Request
            $db->prepare("UPDATE coupon_requests SET status = 'approved' WHERE id = ?")->execute([$requestId]);

            // Notify User
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")
               ->execute([$req['requester_id'], 'Coupon Approved', "Your coupon code is $code", 'coupon']);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $response->withHeader('Location', '/admin/coupons/requests')->withStatus(302);
    }
}
