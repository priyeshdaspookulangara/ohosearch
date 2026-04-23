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

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $db = Database::getInstance();

        $business = $db->prepare("SELECT * FROM businesses WHERE id = ?");
        $business->execute([$id]);
        $business = $business->fetch();

        if (!$business) {
            return $response->withStatus(404);
        }

        // Check ownership or admin
        if ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id']) {
            return $response->withStatus(403);
        }

        $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

        // Get selected categories
        $stmt = $db->prepare("SELECT category_id FROM business_categories WHERE business_id = ?");
        $stmt->execute([$id]);
        $selectedCategories = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $hours = $db->prepare("SELECT * FROM working_hours WHERE business_id = ?");
        $hours->execute([$id]);
        $hours = $hours->fetchAll();

        $formattedHours = [];
        foreach ($hours as $hour) {
            $formattedHours[$hour['day_of_week']] = $hour;
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/business_form.twig', [
            'categories' => $categories,
            'business' => $business,
            'hours' => $formattedHours,
            'selected_categories' => $selectedCategories
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        $db = Database::getInstance();

        $business = $db->prepare("SELECT * FROM businesses WHERE id = ?");
        $business->execute([$id]);
        $business = $business->fetch();

        if (!$business) {
            return $response->withStatus(404);
        }

        if ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id']) {
            return $response->withStatus(403);
        }

        $hero_image = $business['hero_image'];
        if (isset($files['hero_image']) && $files['hero_image']->getError() === UPLOAD_ERR_OK) {
            $uploadedFile = $files['hero_image'];
            $hero_image = $this->moveUploadedFile(__DIR__ . '/../../uploads/hero', $uploadedFile);
        }

        $stmt = $db->prepare("UPDATE businesses SET name=?, description=?, address=?, district=?, state=?, pin=?, phone=?, email=?, whatsapp=?, latitude=?, longitude=?, hero_image=?, youtube_video_id=? WHERE id=?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['address'] ?? '',
            $data['district'] ?? '',
            $data['state'] ?? '',
            $data['pin'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['whatsapp'] ?? '',
            $data['latitude'] ?? 0,
            $data['longitude'] ?? 0,
            $hero_image,
            $data['youtube_video_id'] ?? '',
            $id
        ]);

        // Update Categories
        $db->prepare("DELETE FROM business_categories WHERE business_id = ?")->execute([$id]);
        if (!empty($data['category_ids'])) {
            $stmtCat = $db->prepare("INSERT INTO business_categories (business_id, category_id) VALUES (?, ?)");
            foreach ($data['category_ids'] as $catId) {
                $stmtCat->execute([$id, $catId]);
            }
        }

        // Update Working Hours
        if (isset($data['hours'])) {
            $db->prepare("DELETE FROM working_hours WHERE business_id = ?")->execute([$id]);
            $stmtHours = $db->prepare("INSERT INTO working_hours (business_id, day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['hours'] as $day => $hours) {
                $is_closed = isset($hours['is_closed']) ? 1 : 0;
                $stmtHours->execute([
                    $id,
                    $day,
                    $hours['open'] ?: null,
                    $hours['close'] ?: null,
                    $is_closed
                ]);
            }
        }

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $db = Database::getInstance();

        $business = $db->prepare("SELECT * FROM businesses WHERE id = ?");
        $business->execute([$id]);
        $business = $business->fetch();

        if (!$business) {
            return $response->withStatus(404);
        }

        if ($_SESSION['user_role'] !== 'admin' && $business['user_id'] != $_SESSION['user_id']) {
            return $response->withStatus(403);
        }

        $db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$id]);
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $db = Database::getInstance();

        // Validation
        $errors = [];
        if (empty($data['name'])) $errors[] = "Name is required";
        if (empty($data['category_ids'])) $errors[] = "At least one category is required";
        if (empty($data['latitude']) || empty($data['longitude'])) $errors[] = "Location on map is required";

        if (!empty($errors)) {
            $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
            $view = Twig::fromRequest($request);
            return $view->render($response, 'admin/business_form.twig', [
                'categories' => $categories,
                'errors' => $errors,
                'form_data' => $data
            ]);
        }

        $hero_image = '';
        if (isset($files['hero_image']) && $files['hero_image']->getError() === UPLOAD_ERR_OK) {
            $uploadedFile = $files['hero_image'];
            $hero_image = $this->moveUploadedFile(__DIR__ . '/../../uploads/hero', $uploadedFile);
        }

        $status = ($_SESSION['user_role'] === 'admin') ? 'live' : 'pending_approval';

        $stmt = $db->prepare("INSERT INTO businesses (name, description, address, district, state, pin, phone, email, whatsapp, latitude, longitude, user_id, status, hero_image, youtube_video_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $data['name'] ?? '',
            $data['description'] ?? '',
            $data['address'] ?? '',
            $data['district'] ?? '',
            $data['state'] ?? '',
            $data['pin'] ?? '',
            $data['phone'] ?? '',
            $data['email'] ?? '',
            $data['whatsapp'] ?? '',
            $data['latitude'] ?? 0,
            $data['longitude'] ?? 0,
            $_SESSION['user_id'],
            $status,
            $hero_image,
            $data['youtube_video_id'] ?? ''
        ]);

        $businessId = $db->lastInsertId();

        // Save Categories
        if (!empty($data['category_ids'])) {
            $stmtCat = $db->prepare("INSERT INTO business_categories (business_id, category_id) VALUES (?, ?)");
            foreach ($data['category_ids'] as $catId) {
                $stmtCat->execute([$businessId, $catId]);
            }
        }

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

    public function achievements(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $db = Database::getInstance();
        $achievements = $db->prepare("SELECT * FROM achievements WHERE business_id = ?");
        $achievements->execute([$id]);
        $achievements = $achievements->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/achievements.twig', [
            'achievements' => $achievements,
            'business_id' => $id
        ]);
    }

    public function storeAchievement(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();
        $db = Database::getInstance();

        $imagePath = '';
        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->moveUploadedFile(__DIR__ . '/../../uploads/achievements', $files['image']);
        }

        $stmt = $db->prepare("INSERT INTO achievements (business_id, title, description, issuer, date_awarded, type, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id,
            $data['title'],
            $data['description'] ?? '',
            $data['issuer'] ?? '',
            $data['date_awarded'] ?? null,
            $data['type'] ?? 'achievement',
            $imagePath
        ]);

        return $response->withHeader('Location', "/businesses/$id/achievements")->withStatus(302);
    }

    public function submitClaim(Request $request, Response $response, array $args): Response
    {
        $businessId = $args['id'];
        $userId = $_SESSION['user_id'];
        $data = $request->getParsedBody();
        $db = Database::getInstance();

        // Check if already owner
        $stmt = $db->prepare("SELECT user_id FROM businesses WHERE id = ?");
        $stmt->execute([$businessId]);
        if ($stmt->fetchColumn() == $userId) {
            return $response->withHeader('Location', "/businesses/$businessId")->withStatus(302);
        }

        $stmt = $db->prepare("INSERT INTO claim_requests (business_id, user_id, notes) VALUES (?, ?, ?)");
        $stmt->execute([$businessId, $userId, $data['notes'] ?? '']);

        return $response->withHeader('Location', "/businesses/$businessId?claimed=1")->withStatus(302);
    }

    public function listClaimRequests(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $claims = $db->query("SELECT cr.*, b.name as business_name, u.name as user_name FROM claim_requests cr
                              JOIN businesses b ON cr.business_id = b.id
                              JOIN users u ON cr.user_id = u.id
                              WHERE cr.status = 'pending'
                              ORDER BY cr.created_at DESC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/claim_requests.twig', ['claims' => $claims]);
    }

    public function approveClaim(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $db = Database::getInstance();

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT business_id, user_id FROM claim_requests WHERE id = ?");
            $stmt->execute([$id]);
            $claim = $stmt->fetch();

            if ($claim) {
                // Update business owner
                $stmt = $db->prepare("UPDATE businesses SET user_id = ? WHERE id = ?");
                $stmt->execute([$claim['user_id'], $claim['business_id']]);

                // Update claim status
                $stmt = $db->prepare("UPDATE claim_requests SET status = 'approved' WHERE id = ?");
                $stmt->execute([$id]);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $response->withHeader('Location', '/admin/claims')->withStatus(302);
    }

    private function moveUploadedFile($directory, $uploadedFile)
    {
        $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowed)) {
            throw new \Exception("Invalid file extension. Only images (jpg, png, webp, gif) are allowed.");
        }

        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return '/uploads/' . basename($directory) . '/' . $filename;
    }
}
