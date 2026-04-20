<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController
{
    public function showLogin(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $view = Twig::fromRequest($request);
        return $view->render($response, 'auth/login.twig', ['error' => 'Invalid email or password']);
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}
