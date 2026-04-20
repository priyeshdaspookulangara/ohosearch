<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentController
{
    public function initiatePayment(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $db = Database::getInstance();
        $businessId = $data['business_id'];
        $amount = $data['amount'];
        $couponCode = $data['coupon_code'] ?? null;

        $couponId = null;
        if ($couponCode) {
            $stmt = $db->prepare("SELECT id, discount_value, discount_type FROM coupons WHERE code = ? AND is_used = 0 AND (assigned_to_user_id = ? OR assigned_to_user_id IS NULL)");
            $stmt->execute([$couponCode, $_SESSION['user_id']]);
            $coupon = $stmt->fetch();
            if ($coupon) {
                $couponId = $coupon['id'];
                if ($coupon['discount_type'] === 'percentage') {
                    $amount = $amount * (1 - $coupon['discount_value'] / 100);
                } else {
                    $amount = max(0, $amount - $coupon['discount_value']);
                }
            }
        }

        // Mock payment record
        $stmt = $db->prepare("INSERT INTO payments (user_id, business_id, amount, status, coupon_id) VALUES (?, ?, ?, 'pending', ?)");
        $stmt->execute([$_SESSION['user_id'], $businessId, $amount, $couponId]);
        $paymentId = $db->lastInsertId();

        // In a real app, redirect to gateway here
        return $this->handleSuccessfulPayment($paymentId, $response);
    }

    private function handleSuccessfulPayment($paymentId, $response)
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Update Payment
            $stmt = $db->prepare("UPDATE payments SET status = 'completed', transaction_id = ? WHERE id = ?");
            $stmt->execute(['TXN_' . bin2hex(random_bytes(4)), $paymentId]);

            // Get payment info
            $payment = $db->prepare("SELECT business_id, coupon_id FROM payments WHERE id = ?");
            $payment->execute([$paymentId]);
            $payment = $payment->fetch();

            // Mark coupon as used
            if ($payment['coupon_id']) {
                $db->prepare("UPDATE coupons SET is_used = 1, used_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$payment['coupon_id']]);
            }

            // Activate Featured Listing (e.g., 30 days)
            $stmt = $db->prepare("INSERT INTO featured_listings (business_id, start_date, end_date, payment_id) VALUES (?, CURRENT_TIMESTAMP, datetime('now', '+30 days'), ?)");
            $stmt->execute([$payment['business_id'], $paymentId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }
}
