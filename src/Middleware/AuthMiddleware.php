<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user_id'])) {
            $response = new SlimResponse();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $handler->handle($request);
    }
}
