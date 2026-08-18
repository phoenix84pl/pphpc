<?php

namespace Phoenix\Core\Library;

class Trading212
{
    private $login;
    private $haslo;
    private $url = "https://live.trading212.com/api/v0/";
    private $_cache_instrumenty = null;
    public $version = "1.3";
    
    // Tablica wyjątków konwersji tickerów (Yahoo → T212 base)
    private $tMapa = [
        'HY9H.F' => 'HY9H',     // Frankfurt zamiast .DE
        'FDR.MC' => 'FDR',      // klasa zwraca .SW zamiast .MC
    ];
    
    public function __construct($login, $haslo)
    {
        $this->login = $login;
        $this->haslo = $haslo;
    }
    
    private function wykonaj($endpoint, $method = "GET", $data = null)
    {
        // Wykonuje zapytanie i przekazuje odpowiedź
        $url = $this->url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->haslo);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        $headers = ["Content-Type: application/json"];
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
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function saldo()
    {
        // Zwraca saldo
        $dane = $this->wykonaj('equity/account/cash');
        if ($dane === null) {
            return false;
        }
        
        if (!isset($dane['free'])) {
            print_r($dane);
        }
        
        $wynik = [
            'saldo' => $dane['free'],
            'inwestycja' => $dane['invested'],
            'wartosc' => $dane['total'],
            'bilans' => $dane['ppl']
        ];
        
        return $wynik;
    }
    
    public function broker2yahoo($ticker)
    {
        // Zamienia tickery T212 na Yahoo używając shortName z API
        $mapa = [
            'a_EQ' => '.AS',
            'd_EQ' => '.DE',
            'e_EQ' => '.MC',
            'l_EQ' => '.L',
            'm_EQ' => '.MI',
            'pp_EQ' => '.PA',
            'p_EQ' => '.PA',
            's_EQ' => '.SW',
            '_AT_EQ' => '.VI',
            '_BE_EQ' => '.BR',
            '_BB_EQ' => '.BR',
            '_CA_EQ' => '.TO',
            '_PT_EQ' => '.LS',
            '_US_EQ' => '',
            '_EQ_US' => ''
        ];
        
        $instrumenty = $this->instrumenty(false, true);
        
        if (!isset($instrumenty[$ticker])) {
            return null;
        }
        
        $info = $instrumenty[$ticker];
        $short_name = $info['shortName'];
        
        $short_name = str_replace('/', '-', $short_name);
        
        $koncowka = null;
        foreach ($mapa as $konc => $gielda_val) {
            if (substr($ticker, -strlen($konc)) === $konc) {
                $koncowka = $konc;
                break;
            }
        }
        
        if ($koncowka === null) {
            return null;
        }
        
        $gielda = isset($mapa[$koncowka]) ? $mapa[$koncowka] : '';
        $ticker_potencjalny = $gielda ? $short_name . $gielda : $short_name;
        
        foreach ($this->tMapa as $yahoo_ticker => $t212_base) {
            if ($short_name === $t212_base) {
                return $yahoo_ticker;
            }
        }
        
        return $ticker_potencjalny;
    }
    
    public function yahoo2broker($ticker)
    {
        $mapa = [
            '.AS' => 'a_EQ',
            '.DE' => 'd_EQ',
            '.F' => 'd_EQ',
            '.MC' => 'e_EQ',
            '.L' => 'l_EQ',
            '.MI' => 'm_EQ',
            '.PA' => 'p_EQ',
            '.SW' => 's_EQ',
            '.VI' => '_AT_EQ',
            '.BR' => '_BE_EQ',
            '.TO' => '_CA_EQ',
            '.LS' => '_PT_EQ',
            '' => '_US_EQ'
        ];
        
        if (isset($this->tMapa[$ticker])) {
            $base_ticker = $this->tMapa[$ticker];
            if (strpos($ticker, '.') !== false) {
                $parts = explode('.', $ticker);
                $gielda = '.' . end($parts);
            } else {
                $gielda = '';
            }
        } else {
            if (strpos($ticker, '.') !== false) {
                $parts = explode('.', $ticker);
                $gielda = '.' . array_pop($parts);
                $base_ticker = implode('.', $parts);
            } else {
                $base_ticker = $ticker;
                $gielda = '';
            }
            
            $base_ticker = str_replace('-', '/', $base_ticker);
        }
        
        if (!isset($mapa[$gielda])) {
            return null;
        }
        $koncowka = $mapa[$gielda];
        
        $instrumenty = $this->instrumenty(false, true);
        
        foreach ($instrumenty as $ticker_broker => $info) {
            if ($info['shortName'] === $base_ticker && substr($ticker_broker, -strlen($koncowka)) === $koncowka) {
                return $ticker_broker;
            }
        }
        
        return null;
    }
    
    public function instrumenty($yahoo = true, $cache = true)
    {
        if ($cache && $this->_cache_instrumenty !== null) {
            $dane_cache = $this->_cache_instrumenty;
        } else {
            $dane = $this->wykonaj('equity/metadata/instruments');
            if ($dane === null || !is_array($dane)) {
                return [];
            }
            
            $dane_cache = [];
            foreach ($dane as $instrument) {
                $ticker = isset($instrument['ticker']) ? $instrument['ticker'] : null;
                if ($ticker) {
                    $dane_cache[$ticker] = [
                        'name' => isset($instrument['name']) ? $instrument['name'] : '',
                        'shortName' => isset($instrument['shortName']) ? $instrument['shortName'] : '',
                        'isin' => isset($instrument['isin']) ? $instrument['isin'] : '',
                        'currency' => isset($instrument['currencyCode']) ? $instrument['currencyCode'] : '',
                        'type' => isset($instrument['type']) ? $instrument['type'] : ''
                    ];
                }
            }
            
            if ($cache) {
                $this->_cache_instrumenty = $dane_cache;
            }
        }
        
        if ($yahoo) {
            $instrumenty_yahoo = [];
            foreach ($dane_cache as $ticker_t212 => $info) {
                $ticker_yahoo = $this->broker2yahoo($ticker_t212);
                if ($ticker_yahoo === null) {
                    continue;
                }
                $info_copy = $info;
                $info_copy['tickerBroker'] = $ticker_t212;
                $instrumenty_yahoo[$ticker_yahoo] = $info_copy;
            }
            return $instrumenty_yahoo;
        } else {
            return $dane_cache;
        }
    }
    
    public function pozycje()
    {
        $dane = $this->wykonaj('equity/portfolio');
        if ($dane === null) {
            return [];
        }
        
        $wynik = [];
        foreach ($dane as $rekord) {
            $rekord['tickerBroker'] = $rekord['ticker'];
            $ticker = $this->broker2yahoo($rekord['tickerBroker']);
            
            if ($ticker === null) {
                continue;
            }
            
            unset($rekord['ticker']);
            unset($rekord['fxPpl']);
            unset($rekord['maxBuy']);
            unset($rekord['maxSell']);
            unset($rekord['pieQuantity']);
            
            if (isset($rekord['quantity'])) {
                $rekord['wolumen'] = $rekord['quantity'];
                unset($rekord['quantity']);
            }
            if (isset($rekord['averagePrice'])) {
                $rekord['open'] = $rekord['averagePrice'];
                unset($rekord['averagePrice']);
            }
            if (isset($rekord['currentPrice'])) {
                $rekord['close'] = $rekord['currentPrice'];
                unset($rekord['currentPrice']);
            }
            if (isset($rekord['ppl'])) {
                $rekord['bilans'] = $rekord['ppl'];
                unset($rekord['ppl']);
            }
            if (isset($rekord['frontend'])) {
                $rekord['zrodlo'] = $rekord['frontend'];
                unset($rekord['frontend']);
            }
            $rekord['ilosc'] = 1;
            $czas = strtotime($rekord['initialFillDate']);
            $rekord['odKiedy'] = $czas;
            unset($rekord['initialFillDate']);
            
            $wynik[$ticker] = $rekord;
        }
        
        return $wynik;
    }
        
    public function handluj($ticker, $ilosc, $extended_hours = false)
    {
        if (strpos($ticker, '_EQ') === false) {
            $ticker_broker = $this->yahoo2broker($ticker);
            if ($ticker_broker === null) {
                return null;
            }
        } else {
            $ticker_broker = $ticker;
        }
        
        $endpoint = "equity/orders/market";
        $dane = [
            "ticker" => $ticker_broker,
            "quantity" => floatval($ilosc),
            "extendedHours" => $extended_hours
        ];
        
        $wynik = $this->wykonaj($endpoint, "POST", $dane);
        if ($wynik === null) {
            return null;
        }
        
        if (isset($wynik['id'])) {
            return ['id' => $wynik['id']];
        }
        
        $error_detail = $wynik['detail'] ?? '';
        if (preg_match('/invalid quantity precision (\d+)/', $error_detail, $matches)) {
            $correct_precision = intval($matches[1]) - 1;
            $dane['quantity'] = round(floatval($ilosc), $correct_precision);
            
            $wynik2 = $this->wykonaj($endpoint, "POST", $dane);
            if ($wynik2 === null) {
                return null;
            }
            
            if (isset($wynik2['id'])) {
                return ['id' => $wynik2['id']];
            } else {
                $title = $wynik2['title'] ?? 'Unknown';
                $detail = $wynik2['detail'] ?? 'No details';
                return ['error' => $title . ': ' . $detail];
            }
        }
        
        $title = $wynik['title'] ?? 'Unknown';
        return ['error' => $title . ': ' . $error_detail];
    }
}