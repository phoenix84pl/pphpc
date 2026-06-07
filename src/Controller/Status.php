<?php
// pphpc/src/Controller/Status.php

namespace Phoenix\Core\Controller;

use Nyholm\Psr7\Response;

class Status
{
    /**
     * URL: /core/status/ping
     */
    public function ping(): string
    {
        return "pong";
    }

    /**
     * URL: /core/status/db
     */
    public function db(): Response
    {
        global $db;

        try {
            $stmt = $db->query("SELECT 1");

            if ($stmt === FALSE) {
                throw new \Exception($db->error ?? "Brak odpowiedzi od serwera MySQL.");
            }

            $status = ['database' => 'OK'];
            $code = 200;
        } catch (\Exception $e) {
            $status = [
                'database' => 'ERROR',
                'error' => $e->getMessage()
            ];
            $code = 500;
        }

        return new Response($code, ['Content-Type' => 'application/json'], json_encode($status));
    }
}