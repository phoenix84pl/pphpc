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
            // 1. KROK BEZPIECZEŃSTWA: Sprawdzamy, czy zmienna w ogóle została zainicjalizowana
            if ($db === null) {
                throw new \Exception("Database object (\$db) is null. Verify your public/index.php configuration.");
            }

            // 2. KROK BEZPIECZEŃSTVA: Sprawdzamy, czy to na pewno jest obiekt naszej klasy Database
            if (!($db instanceof \Phoenix\Core\Database)) {
                throw new \Exception("Database object is invalid or wrong instance type.");
            }

            // Dopiero gdy mamy 100% pewności, że obiekt żyje, odpytujemy bazę
            $stmt = $db->query("SELECT 1");

            if ($stmt === FALSE) {
                throw new \Exception($db->error ?? "Brak odpowiedzi od serwera MySQL.");
            }

            $status = ['database' => 'OK'];
            $code = 200;

        } catch (\Exception $e) {
            // Każdy błąd (w tym brak obiektu) ląduje tutaj i zwraca czytelny JSON zamiast błędu 500!
            $status = [
                'database' => 'ERROR',
                'error' => $e->getMessage()
            ];
            $code = 500;
        }

        return new Response($code, ['Content-Type' => 'application/json'], json_encode($status));
    }
}