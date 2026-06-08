<?php
namespace Phoenix\Core\Controller;

use Nyholm\Psr7\Response;

class Status extends Component
{
    public function ping(): string
    {
        return "pong";
    }

    public function db(): Response
    {
        global $db;

        try {
            // 1. Jeśli index.php przekazał nam obiekt błędu (\Throwable), rzucamy go od razu do catch
            if ($db instanceof \Throwable) {
                throw $db;
            }

            // 2. Jeśli obiekt jest pusty (np. ktoś wyłączył bazę w kodzie)
            if ($db === null) {
                throw new \Exception("Obiekt bazy (\$db) ma wartość null w public/index.php.");
            }

            // 3. Sprawdzenie instancji
            if (!($db instanceof \Phoenix\Core\Database)) {
                throw new \Exception("Zmienna \$db nie jest instancją klasy Phoenix\\Core\\Database.");
            }

            // Testujemy metodę query()
            $stmt = $db->query("SELECT 1");

            if ($stmt === FALSE) {
                throw new \Exception($db->error ?? "Brak odpowiedzi z MySQL.");
            }

            $status = ['database' => 'OK'];
            
        } catch (\Throwable $e) { // Zmienione na \Throwable, żeby łapało absolutnie każdy typ błędu
            $status = [
                'database' => 'ERROR',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($status));
    }
}