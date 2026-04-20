<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class BusinessController
{
    public function create(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/business_form.twig', ['categories' => $categories]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $db = Database::getInstance();

        $hero_image = '';
        if (isset($files['hero_image']) && $files['hero_image']->getError() === UPLOAD_ERR_OK) {
            $uploadedFile = $files['hero_image'];
            $hero_image = $this->moveUploadedFile(__DIR__ . '/../../uploads/hero', $uploadedFile);
        }

        $status = ($_SESSION['user_role'] === 'admin') ? 'live' : 'pending_approval';

        $stmt = $db->prepare("INSERT INTO businesses (name, description, address, district, state, pin, phone, email, whatsapp, latitude, longitude, category_id, user_id, status, hero_image, youtube_video_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['address'],
            $data['district'],
            $data['state'],
            $data['pin'],
            $data['phone'],
            $data['email'],
            $data['whatsapp'],
            $data['latitude'],
            $data['longitude'],
            $data['category_id'],
            $_SESSION['user_id'],
            $status,
            $hero_image,
            $data['youtube_video_id']
        ]);

        $businessId = $db->lastInsertId();

        // Gallery
        if (isset($files['gallery'])) {
            $stmtGallery = $db->prepare("INSERT INTO business_gallery (business_id, image_path) VALUES (?, ?)");
            foreach ($files['gallery'] as $uploadedFile) {
                if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                    $path = $this->moveUploadedFile(__DIR__ . '/../../uploads/gallery', $uploadedFile);
                    $stmtGallery->execute([$businessId, $path]);
                }
            }
        }

        // Working Hours
        if (isset($data['hours'])) {
            $stmtHours = $db->prepare("INSERT INTO working_hours (business_id, day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['hours'] as $day => $hours) {
                $is_closed = isset($hours['is_closed']) ? 1 : 0;
                $stmtHours->execute([
                    $businessId,
                    $day,
                    $hours['open'] ?: null,
                    $hours['close'] ?: null,
                    $is_closed
                ]);
            }
        }

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    private function moveUploadedFile($directory, $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return '/uploads/' . basename($directory) . '/' . $filename;
    }
}
