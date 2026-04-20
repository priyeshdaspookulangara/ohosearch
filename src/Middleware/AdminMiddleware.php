<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AdminMiddleware
{
    public function __invoke(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            $response = new SlimResponse();
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}
