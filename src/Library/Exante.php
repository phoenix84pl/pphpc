<?php

namespace Phoenix\Core\Library;

class Exante
{
    private $login;
    private $key;
    private $secret;
    private $url = "https://api-live.exante.eu/md/3.0/";
    private $trade_url = "https://api-live.exante.eu/trade/3.0/";
    private $base_currency = "USD";
    public $version = "1.5";
    
    // Mapowanie giełd → Yahoo suffixes
    private $gieldy_mapa = [
        'NASDAQ' => '',
        'NYSE' => '',
        'ARCA' => '',
        'AMEX' => '',
        'BATS' => '',

        'TMX' => '.TO',
        'ASX' => '.AX',
        'SGX' => '.SI',
        'HKEX' => '.HK',
        'DFM' => '.AE',
        'SA' => '.SR',

        'XETRA' => '.DE',
        'LSE' => '.L',

        'BM' => '.MC',
        'WSE' => '.WA',
        'VSE' => '.VI',
        'SIX' => '.SW',
        'ATH' => '.AT',

        'SOMX' => '.ST',
        'OMXC' => '.CO',
        'OMXH' => '.HE',
        'OMXS' => '.ST',
    ];
    
    // Mapowanie EURONEXT po country code
    private $euronext_country_map = [
        'NL' => '.AS',      // Amsterdam
        'FR' => '.PA',      // Paris
        'BE' => '.BR',      // Brussels
        'ES' => '.MC',      // Madrid
        'PT' => '.LS',      // Lisbon
        'IT' => '.MI',      // Milan
    ];
    
    public function __construct(string $login, string $haslo, bool $demo = false)
    {
        $this->login = $login;
        
        // Rozdziel haslo (format: KEY:SECRET)
        $parts = explode(':', $haslo, 2);
        $this->key = $parts[0];
        $this->secret = isset($parts[1]) ? $parts[1] : '';
        
        if ($demo) {
            $this->url = "https://api-demo.exante.eu/md/3.0/";
            $this->trade_url = "https://api-demo.exante.eu/trade/3.0/";
        }
    }
    
    private function wykonaj($endpoint, $method = "GET", $data = null)
    {
        $url = $this->url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->key . ':' . $this->secret);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method == "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 400) {
            return ['error' => "Exante API error {$http_code}: " . substr($response, 0, 200)];
        }
        
        return json_decode($response, true);
    }
    
    private function wykonaj_trade($endpoint, $method = "GET", $data = null)
    {
        $url = $this->trade_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->key . ':' . $this->secret);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method == "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 400) {
            return ['error' => "Exante Trade API error {$http_code}: " . substr($response, 0, 200)];
        }
        
        return json_decode($response, true);
    }
    
    private function broker2yahoo($ticker_broker, $country = null)
    {
        if (strpos($ticker_broker, '.') === false) {
            return $ticker_broker;
        }
        
        $parts = explode('.', $ticker_broker);
        $symbol = $parts[0];
        $exchange = isset($parts[1]) ? $parts[1] : '';
        
        $symbol = str_replace('/', '-', $symbol);
        
        if ($exchange === 'EURONEXT' && $country) {
            $suffix = isset($this->euronext_country_map[$country]) 
                ? $this->euronext_country_map[$country] 
                : '.PA';
        } else {
            $suffix = isset($this->gieldy_mapa[$exchange]) 
                ? $this->gieldy_mapa[$exchange] 
                : '';
        }
        
        return $symbol . $suffix;
    }
    
    public function saldo()
    {
        $endpoint = "summary/" . $this->login . "/" . $this->base_currency;
        $dane = $this->wykonaj($endpoint);
        if ($dane === null || isset($dane['error'])) {
            return false;
        }
        
        try {
            $free_money = floatval($dane['freeMoney'] ?? 0);
            $net_asset_value = floatval($dane['netAssetValue'] ?? 0);
            $inwestycja = $net_asset_value - $free_money;
            
            $bilans = 0;
            if (isset($dane['positions']) && is_array($dane['positions'])) {
                foreach ($dane['positions'] as $pos) {
                    $bilans += floatval($pos['convertedPnl'] ?? 0);
                }
            }
            
            return [
                'saldo' => $free_money,
                'inwestycja' => $inwestycja,
                'wartosc' => $net_asset_value,
                'bilans' => $bilans
            ];
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    public function pozycje()
    {
        $endpoint = "summary/" . $this->login . "/" . $this->base_currency;
        $dane = $this->wykonaj($endpoint);
        if ($dane === null || isset($dane['error'])) {
            return [];
        }
        
        $wynik = [];
        
        if (!isset($dane['positions']) || !is_array($dane['positions'])) {
            return [];
        }
        
        foreach ($dane['positions'] as $rekord) {
            $symbol_broker = isset($rekord['symbolId']) ? $rekord['symbolId'] : null;
            if (!$symbol_broker) {
                continue;
            }
            
            $country = isset($rekord['country']) ? $rekord['country'] : null;
            $ticker_yahoo = $this->broker2yahoo($symbol_broker, $country);
            
            $r = [
                'tickerBroker' => $symbol_broker,
                'wolumen' => floatval($rekord['quantity'] ?? 0),
                'open' => floatval($rekord['averagePrice'] ?? 0),
                'close' => floatval($rekord['price'] ?? 0),
                'bilans' => floatval($rekord['convertedPnl'] ?? 0),
                'zrodlo' => 'Exante'
            ];
            
            if (isset($dane['timestamp'])) {
                $r['odKiedy'] = intval($dane['timestamp'] / 1000);
            }
            
            $wynik[$ticker_yahoo] = $r;
        }
        
        return $wynik;
    }
    
    public function handluj(string $ticker, float $ilosc)
    {
        if ($ilosc == 0) {
            return ['error' => 'Ilość musi być różna od zera'];
        }
        
        $side = $ilosc > 0 ? 'buy' : 'sell';
        $qty = floatval(abs($ilosc));
        
        $payload = [
            'accountId' => $this->login,
            'symbolId' => $ticker,
            'side' => $side,
            'quantity' => strval($qty),
            'orderType' => 'market',
            'duration' => 'day'
        ];
        
        $wynik = $this->wykonaj_trade('orders', 'POST', $payload);
        
        if ($wynik === null) {
            return ['error' => 'Brak odpowiedzi / błąd połączenia z API Exante'];
        }

        if (isset($wynik['error'])) {
            return $wynik;
        }
        
        // Obsługa formatu odpowiedzi jako lista lub pojedynczy obiekt
        if (is_array($wynik)) {
            if (isset($wynik['orderId'])) {
                return ['id' => $wynik['orderId']];
            }
            if (isset($wynik[0]['orderId'])) {
                return ['id' => $wynik[0]['orderId']];
            }
        }
        
        return ['error' => 'Unexpected response: ' . print_r($wynik, true)];
    }
    
    public function instrumenty($gielda = 'NASDAQ')
    {
        $endpoint = "exchanges/" . $gielda;
        $dane = $this->wykonaj($endpoint);
        if ($dane === null || isset($dane['error'])) {
            return [];
        }
        
        $wynik = [];
        $instruments_list = is_array($dane) ? $dane : (isset($dane['symbols']) ? $dane['symbols'] : []);
        
        foreach ($instruments_list as $rekord) {
            $symbol_broker = isset($rekord['symbolId']) ? $rekord['symbolId'] : null;
            if (!$symbol_broker) {
                continue;
            }
            
            $country = isset($rekord['country']) ? $rekord['country'] : null;
            $ticker_yahoo = $this->broker2yahoo($symbol_broker, $country);
            $identifiers = isset($rekord['identifiers']) ? $rekord['identifiers'] : [];
            
            $wynik[$ticker_yahoo] = [
                'symbolId' => $symbol_broker,
                'name' => isset($rekord['name']) ? $rekord['name'] : '',
                'type' => isset($rekord['symbolType']) ? $rekord['symbolType'] : '',
                'currency' => isset($rekord['currency']) ? $rekord['currency'] : '',
                'isin' => isset($identifiers['ISIN']) ? $identifiers['ISIN'] : '',
                'country' => $country
            ];
        }
        
        return $wynik;
    }
}