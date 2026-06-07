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
        // CAŁKOWITY RESET – ŻADNEGO REQESTU, ŻADNEGO DB. 
        // Sprawdzamy tylko, czy sam kontroler potrafi wypluć JSONA.
        
        $status = ['test_kontrolera' => 'DZIALA_BEZ_500'];
        
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($status));
    }
}