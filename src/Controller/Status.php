<?php
// vendor/phoenix84pl/pphpc/src/Controller/Status.php

namespace Phoenix\Core\Controller;

use Nyholm\Psr7\Response;

class Status
{
    public function ping(): string
    {
        return "pong";
    }

    public function db(): Response
    {
        global $db;

        try {
            if ($db === null) {
                throw new \Exception("Obiekt bazy (\$db) ma wartość null w public/index.php.");
            }

            if (!($db instanceof \Phoenix\Core\Database)) {
                throw new \Exception("Zmienna \$db nie jest instancją klasy Phoenix\\Core\\Database.");
            }

            // Testujemy naprawioną metodę query()
            $stmt = $db->query("SELECT 1");

            if ($stmt === FALSE) {
                throw new \Exception($db->error ?? "Brak odpowiedzi z MySQL.");
            }

            $status = ['database' => 'OK'];
        } catch (\Exception $e) {
            $status = [
                'database' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($status));
    }
}