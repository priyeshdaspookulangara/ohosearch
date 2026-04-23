<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class OfferingController
{
    public function index(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['business_id'];
        $db = Database::getInstance();

        $business = $db->prepare("SELECT name FROM businesses WHERE id = ?");
        $business->execute([$businessId]);
        $business = $business->fetch();

        $offerings = $db->prepare("SELECT * FROM offerings WHERE business_id = ? ORDER BY created_at DESC");
        $offerings->execute([$businessId]);
        $offerings = $offerings->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/offerings.twig', [
            'offerings' => $offerings,
            'business_id' => $businessId,
            'business' => $business
        ]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['business_id'];
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        $db = Database::getInstance();

        // Check ownership
        $business = $db->prepare("SELECT user_id FROM businesses WHERE id = ?");
        $business->execute([$businessId]);
        $business = $business->fetch();
        if (!$business || ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id'])) {
            return $response->withStatus(403);
        }

        $imagePath = '';
        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->moveUploadedFile(__DIR__ . '/../../uploads/offerings', $files['image']);
        }

        $isFlagship = isset($data['is_flagship']) ? 1 : 0;

        if ($isFlagship) {
            // Unset other flagships for this business
            $db->prepare("UPDATE offerings SET is_flagship = 0 WHERE business_id = ?")->execute([$businessId]);
        }

        $stmt = $db->prepare("INSERT INTO offerings (business_id, name, description, price, image_path, is_flagship) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $businessId,
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['price'] ?? 0,
            $imagePath,
            $isFlagship
        ]);

        return $response->withHeader('Location', "/businesses/$businessId/offerings")->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['business_id'];
        $offeringId = $args['id'];
        $db = Database::getInstance();

        // Check ownership
        $business = $db->prepare("SELECT user_id FROM businesses WHERE id = ?");
        $business->execute([$businessId]);
        $business = $business->fetch();
        if (!$business || ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id'])) {
            return $response->withStatus(403);
        }

        $db->prepare("DELETE FROM offerings WHERE id = ? AND business_id = ?")->execute([$offeringId, $businessId]);

        return $response->withHeader('Location', "/businesses/$businessId/offerings")->withStatus(302);
    }

    public function toggleFlagship(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['business_id'];
        $offeringId = $args['id'];
        $db = Database::getInstance();

        // Check ownership
        $business = $db->prepare("SELECT user_id FROM businesses WHERE id = ?");
        $business->execute([$businessId]);
        $business = $business->fetch();
        if (!$business || ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id'])) {
            return $response->withStatus(403);
        }

        $db->beginTransaction();
        try {
            // Unset all flagships for this business
            $db->prepare("UPDATE offerings SET is_flagship = 0 WHERE business_id = ?")->execute([$businessId]);
            // Set this one as flagship
            $db->prepare("UPDATE offerings SET is_flagship = 1 WHERE id = ?")->execute([$offeringId]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $response->withHeader('Location', "/businesses/$businessId/offerings")->withStatus(302);
    }

    private function moveUploadedFile($directory, $uploadedFile)
    {
        $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowed)) {
            throw new \Exception("Invalid file extension. Only images (jpg, png, webp, gif) are allowed.");
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);
        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        return '/uploads/' . basename($directory) . '/' . $filename;
    }
}
