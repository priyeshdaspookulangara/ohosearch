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

        $stmt = $db->prepare("INSERT INTO offerings (business_id, name, description, price, image_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $businessId,
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['price'] ?? 0,
            $imagePath
        ]);

        return $response->withHeader('Location', "/businesses/$businessId/offerings")->withStatus(302);
    }

    private function moveUploadedFile($directory, $uploadedFile)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);
        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        return '/uploads/' . basename($directory) . '/' . $filename;
    }
}
