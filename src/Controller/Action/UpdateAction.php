<?php

namespace Phoenix\Core\Controller\Action;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class UpdateAction
{
    public function index(): ResponseInterface
    {
        $tryb = $_REQUEST['tryb'] ?? null;
        $success = false;
        $data = [];

        // 1. Ustawianie dowolnego klucza w sesji
        if ($tryb === 'session' && isset($_REQUEST['klucz'], $_REQUEST['wartosc'])) {
            $_SESSION[$_REQUEST['klucz']] = $_REQUEST['wartosc'];
            $success = true;
        }

        // 2. reOrientacja (przełącznik toggle: landscape <-> portrait)
        if ($tryb === 'reOrientuj') {
            $current = $_SESSION['CMSOrientacja'] ?? 'landscape';
            $_SESSION['CMSOrientacja'] = ($current === 'portrait') ? 'landscape' : 'portrait';
            $success = true;
            $data['orientation'] = $_SESSION['CMSOrientacja'];
        }

        // 3. Bezpośrednia zmiana orientacji (np. tryb=setOrientation&value=portrait)
        if ($tryb === 'setOrientation' && isset($_REQUEST['value'])) {
            $val = strtolower($_REQUEST['value']);
            if (in_array($val, ['portrait', 'landscape'], true)) {
                $_SESSION['CMSOrientacja'] = $val;
                $success = true;
                $data['orientation'] = $val;
            }
        }

        // 4. reUID (zmiana użytkownika przez admina)
        if ($tryb === 'reUID' && ($_SESSION['CMSS'] ?? 0) >= 8 && isset($_REQUEST['UID'])) {
            $_SESSION['CMSU'] = $_REQUEST['UID'];
            $success = true;
            $data['uid'] = $_REQUEST['UID'];
        }

        if ($success) {
            $payload = (string) json_encode([
                'status' => 'success',
                'data' => $data
            ]);
            return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
        }

        $payload = (string) json_encode([
            'status' => 'error',
            'message' => 'Invalid parameters or action failed.'
        ]);
        return new Response(400, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
    }
}