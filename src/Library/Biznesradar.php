<?php

namespace Phoenix\Terminal\Controller\Action;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phoenix\Core\Database;
use Phoenix\Core\Library\Biznesradar;

class BiznesradarAction
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $tryb = $params['tryb'] ?? null;
        $tickerInput = $params['ticker'] ?? null;

        if ($tryb === 'sprawozdania' && !empty($tickerInput)) {
            $oBRAPI = new Biznesradar();
            $dzielnik = 1000000;
            $sprawozdania = $oBRAPI->sprawozdania($tickerInput);

            if (!empty($sprawozdania) && is_array($sprawozdania)) {
                foreach ($sprawozdania as $data => $sprawozdanie) {
                    $dataSkr = substr($data, 0, 4) . '-01-01';

                    $rekord = [
                        'ticker'       => htmlspecialchars($tickerInput),
                        'typ'          => 0,
                        'data'         => htmlspecialchars($dataSkr),
                        'tRevenue'     => ($sprawozdanie['Przychody ze sprzedaży'] ?? 0) / $dzielnik,
                        'COGS'         => ($sprawozdanie['Techniczny koszt wytworzenia produkcji sprzedanej'] ?? 0) / $dzielnik,
                        'nIncome'      => ($sprawozdanie['Zysk netto'] ?? 0) / $dzielnik,
                        'oIncome'      => ($sprawozdanie['Zysk operacyjny (EBIT)'] ?? 0) / $dzielnik,
                        'depreciation' => (($sprawozdanie['EBITDA'] ?? 0) - ($sprawozdanie['Zysk operacyjny (EBIT)'] ?? 0)) / $dzielnik,
                        'EBITDA'       => ($sprawozdanie['EBITDA'] ?? 0) / $dzielnik,
                    ];

                    // Pobranie ID za pomocą nowej składni: select(...) + jako('komorka') z warunkami w tablicy
                    $id = $this->db->select(
                        'raporty', 
                        ['id'], 
                        [
                            'ticker' => $tickerInput, 
                            'typ' => 0, 
                            'data' => $dataSkr
                        ]
                    )->jako('komorka');

                    if (empty($id)) {
                        $this->db->insert('raporty', $rekord);
                    } else {
                        $this->db->update('raporty', $rekord, ['id' => $id]);
                    }
                }

                $this->db->update('tickery', ['czasFA' => null], ['ticker' => $tickerInput]);

                return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], 'OK');
            }

            $payload = json_encode([
                'status' => 'error',
                'message' => 'Brak danych sprawozdań',
                'errors' => $oBRAPI->error
            ], JSON_UNESCAPED_UNICODE);

            return new Response(400, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
        }

        return new Response(400, ['Content-Type' => 'application/json; charset=utf-8'], json_encode([
            'status' => 'error',
            'message' => 'Nieprawidłowy tryb lub brak tickera'
        ]));
    }
}