<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class CategoryController
{
    public function index(Request $request, Response $response): Response
    {
        $db = Database::getInstance();
        $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/categories.twig', ['categories' => $categories]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? '';
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

        if ($name) {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            try {
                $stmt->execute([$name, $slug]);
            } catch (\Exception $e) {
                // Handle duplicate slug etc
            }
        }

        return $response->withHeader('Location', '/categories')->withStatus(302);
    }
}
