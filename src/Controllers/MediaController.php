<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class MediaController
{
    public function index(Request $request, Response $response): Response
    {
        $db = Database::getInstance();

        // Fetch Hero Images
        $heroImages = $db->query("SELECT id, name, hero_image as path, 'hero' as type FROM businesses WHERE hero_image != '' AND hero_image IS NOT NULL")->fetchAll();

        // Fetch Gallery Images
        $galleryImages = $db->query("SELECT g.id, b.name, g.image_path as path, 'gallery' as type FROM business_gallery g JOIN businesses b ON g.business_id = b.id")->fetchAll();

        // Fetch Offering Images
        $offeringImages = $db->query("SELECT o.id, b.name || ' - ' || o.name as name, o.image_path as path, 'offering' as type FROM offerings o JOIN businesses b ON o.business_id = b.id WHERE o.image_path != '' AND o.image_path IS NOT NULL")->fetchAll();

        // Fetch Achievement Images
        $achievementImages = $db->query("SELECT a.id, b.name || ' - ' || a.title as name, a.image_path as path, 'achievement' as type FROM achievements a JOIN businesses b ON a.business_id = b.id WHERE a.image_path != '' AND a.image_path IS NOT NULL")->fetchAll();

        $allMedia = array_merge($heroImages, $galleryImages, $offeringImages, $achievementImages);

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/media.twig', [
            'media' => $allMedia
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $type = $args['type'];
        $id = $args['id'];
        $db = Database::getInstance();

        switch ($type) {
            case 'hero':
                $stmt = $db->prepare("UPDATE businesses SET hero_image = NULL WHERE id = ?");
                $stmt->execute([$id]);
                break;
            case 'gallery':
                $stmt = $db->prepare("DELETE FROM business_gallery WHERE id = ?");
                $stmt->execute([$id]);
                break;
            case 'offering':
                $stmt = $db->prepare("UPDATE offerings SET image_path = NULL WHERE id = ?");
                $stmt->execute([$id]);
                break;
            case 'achievement':
                $stmt = $db->prepare("UPDATE achievements SET image_path = NULL WHERE id = ?");
                $stmt->execute([$id]);
                break;
        }

        return $response->withHeader('Location', '/admin/media')->withStatus(302);
    }
}
