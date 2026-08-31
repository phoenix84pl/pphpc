<?php

namespace Phoenix\Core\Library;

class Tastytrade
{
    private $__version__ = "1.1";
    private $refresh_token;
    private $client_secret;
    private $url;
    private $access_token;
    private $account_number;
    
    public function __construct(string $login, string $haslo) {
        /*
         * Args:
         *   login: Refresh Token wygenerowany przez "Create Grant"
         *   haslo: Client Secret z OAuth App (z my.tastytrade.com)
         */
        $this->refresh_token = $login;
        $this->client_secret = $haslo;
        $this->url = "https://api.tastyworks.com/";
        $this->access_token = null;
        $this->account_number = null;
        $this->_pobierz_access_token();
    }
    
    private function _get_headers(): array {
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: Mozilla/5.0 (compatible; TT-PHP-Client/1.1)"
        ];
        if ($this->access_token) {
            $headers[] = "Authorization: Bearer " . $this->access_token;
        }
        return $headers;
    }
    
    private function _pobierz_access_token() {
        $endpoint = "oauth/token";
        $dane = [
            "grant_type" => "refresh_token",
            "refresh_token" => $this->refresh_token,
            "client_secret" => $this->client_secret
        ];
        
        $url = $this->url . $endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dane));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: Mozilla/5.0 (compatible; TT-PHP-Client/1.1)"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($status_code != 200) {
            return null;
        }
        
        $dane_tokenu = json_decode($response, true);
        $this->access_token = $dane_tokenu['access_token'] ?? null;
        
        // Pobierz numer konta
        $konta = $this->wykonaj('customers/me/accounts');
        if ($konta && isset($konta['data']) && isset($konta['data']['items'])) {
            if (count($konta['data']['items']) > 0) {
                $this->account_number = $konta['data']['items'][0]['account']['account-number'] ?? null;
            }
        }
    }
    
    public function wykonaj($endpoint, $method = "GET", $json = null) {
        $url = $this->url . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_get_headers());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($json !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
            }
        } elseif ($method == "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        } elseif ($method != "GET") {
            throw new \InvalidArgumentException("Nieobsługiwana metoda HTTP: " . $method);
        }
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Jeśli token wygasł (401), odśwież i spróbuj ponownie
        if ($status_code == 401) {
            $this->_pobierz_access_token();
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_get_headers());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            if ($method == "POST") {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($json !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
                }
            } elseif ($method == "DELETE") {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            }
            
            $response = curl_exec($ch);
            curl_close($ch);
        }
        
        return json_decode($response, true);
    }
    
    public function saldo() {
        if (!$this->account_number) {
            return false;
        }
        
        $endpoint = 'accounts/' . $this->account_number . '/balances';
        $dane = $this->wykonaj($endpoint);
        if ($dane === null || !isset($dane['data'])) {
            return false;
        }
        
        $balans = $dane['data'];
        return [
            'saldo' => floatval($balans['cash-balance'] ?? 0),
            'inwestycja' => floatval($balans['long-equity-value'] ?? 0),
            'wartosc' => floatval($balans['net-liquidating-value'] ?? 0),
            'bilans' => null
        ];
    }
    
    public function pozycje() {
        if (!$this->account_number) {
            return [];
        }
        
        $endpoint = 'accounts/' . $this->account_number . '/positions';
        $dane = $this->wykonaj($endpoint);
        if ($dane === null || !isset($dane['data']) || !isset($dane['data']['items'])) {
            return [];
        }
        
        $wynik = [];
        foreach ($dane['data']['items'] as $rekord) {
            if (($rekord['instrument-type'] ?? '') != 'Equity') {
                continue;
            }
            
            $ticker = $rekord['symbol'] ?? '';
            if (!$ticker) continue;

            $czas = new \DateTime($rekord['created-at'] ?? '0');
            
            $wynik[$ticker] = [
                'tickerBroker' => $ticker,
                'wolumen' => floatval($rekord['quantity'] ?? 0),
                'openTicker' => floatval($rekord['average-open-price'] ?? 0),
                'closeTicker' => floatval($rekord['close-price'] ?? 0),
                'bilans' => floatval($rekord['realized-day-gain'] ?? 0),
                'odKiedy' => $czas->getTimestamp()
            ];
        }
        
        return $wynik;
    }
    
    public function handluj(string $ticker, float $ilosc) {
        if (!$this->account_number) {
            return ['error' => 'Brak numeru konta TT'];
        }
        
        if ($ilosc == 0) {
            return ['error' => 'Ilość musi być różna od zera'];
        }
        
        $endpoint = 'accounts/' . $this->account_number . '/orders';
        $akcja = $ilosc > 0 ? "Buy to Open" : "Sell to Close";
        
        $dane = [
            "time-in-force" => "Day",
            "order-type" => "Market",
            "legs" => [
                [
                    "instrument-type" => "Equity",
                    "symbol" => $ticker,
                    "quantity" => abs(floatval($ilosc)),
                    "action" => $akcja
                ]
            ]
        ];
        
        $wynik = $this->wykonaj($endpoint, "POST", $dane);
        
        if ($wynik === null) {
            return ['error' => 'Brak odpowiedzi / błąd połączenia z API Tastytrade'];
        }

        if (isset($wynik['data']) && isset($wynik['data']['order']) && isset($wynik['data']['order']['id'])) {
            return ['id' => $wynik['data']['order']['id']];
        }
        
        // Bezpieczne wyłapanie komunikatu o błędzie z API Tastytrade
        $errorMsg = $wynik['error']['errors'][0]['message'] ?? ('Nieznany błąd API: ' . json_encode($wynik));
        return ['error' => $errorMsg];
    }
}