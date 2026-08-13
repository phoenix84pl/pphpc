<?php

namespace Phoenix\Terminal\Controller\Action;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class CmslogoutAction
{
    public function index(): ResponseInterface
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        $payload = (string) json_encode([
            'status' => 'success',
            'message' => 'User logged out successfully.'
        ]);

        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
    }
}