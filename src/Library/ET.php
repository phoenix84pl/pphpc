<?php

namespace Phoenix\Core\Library;

/**
 * ET - eToro API Client (PHP port)
 * Wersja 3.2
 */
class ET
{
    const VERSION = '3.2';

    // Tablica wyjątków konwersji tickerów (ticker eToro → ticker Yahoo)
    private array $tMapa = [
        'AMCDD.ASX' => 'AMC.AX',
        'BETSB.ST'  => 'BETS-B.ST',
        'INVEB.ST'  => 'INVE-B.ST',
        'HMSN.ST'   => 'HMS.ST',
        'HLUNB.CO'  => 'HLUN-B.CO',
    ];

    // Mapowanie ExchangeID → Yahoo suffix i reguły transformacji tickera
    private array $EXCHANGE_MAP = [
        4  => ['suffix' => '',    'remove_etoro_suffix' => '.US',  'rules' => []],  // NYSE
        5  => ['suffix' => '',    'remove_etoro_suffix' => '.US',  'rules' => []],  // NASDAQ
        14 => ['suffix' => '.OL', 'remove_etoro_suffix' => '.OL',  'rules' => []],  // Norwegia
        15 => [                                                                       // Szwecja
            'suffix'              => '.ST',
            'remove_etoro_suffix' => '.ST',
            'rules'               => [['b$', '-B']],
        ],
        16 => ['suffix' => '.CO', 'remove_etoro_suffix' => '.CO',  'rules' => []],  // Dania
        21 => ['suffix' => '.HK', 'remove_etoro_suffix' => '.HK',  'rules' => []],  // Hong Kong
        31 => ['suffix' => '.AX', 'remove_etoro_suffix' => '.ASX', 'rules' => []],  // Australia
    ];

    private string $apiPublic;
    private string $apiKey;
    private string $baseUrl    = 'https://public-api.etoro.com/api/v1';
    private string $baseCurrency = 'USD';
    private int    $maxRetries = 3;
    private int    $retryDelay = 2;
    private string $tempDir;
    private string $cacheDir;
    private array  $cacheInstrumenty = [];

    public function __construct(string $login, string $haslo, bool $demo = false)
    {
        $this->apiPublic = $login;
        $this->apiKey    = $haslo;

        $this->tempDir  = dirname(__DIR__) . '/tmp';
        $this->cacheDir = $this->tempDir . '/' . get_class($this);

        if (file_exists($this->tempDir) && !is_dir($this->tempDir)) {
            echo "[INFO] " . get_class($this) . ": Usuwam plik 'tmp' blokujący utworzenie katalogu...\n";
            unlink($this->tempDir);
        }

        if (!is_dir($this->tempDir))  mkdir($this->tempDir,  0775, true);
        if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0775, true);
    }

    // =========================================================================
    // Nagłówki
    // =========================================================================

    private function getHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'x-api-key: '      . $this->apiPublic,
            'x-user-key: '     . $this->apiKey,
            'x-request-id: '   . $this->generateUuid(),
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Pragma: no-cache',
        ];
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // =========================================================================
    // Cache helpers
    // =========================================================================

    private function isCacheFresh(string $cacheFile, int $ttlDays = 7): bool
    {
        if (!file_exists($cacheFile)) return false;
        return (time() - filemtime($cacheFile)) < ($ttlDays * 86400);
    }

    private function loadCache(string $cacheFile): mixed
    {
        try {
            if (!file_exists($cacheFile)) return null;
            return json_decode(file_get_contents($cacheFile), true);
        } catch (\Throwable $e) {
            echo "[WARNING] " . get_class($this) . ": Błąd ładowania cache: {$e->getMessage()}\n";
            return null;
        }
    }

    private function saveCache(string $cacheFile, mixed $data): void
    {
        try {
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            echo "[WARNING] " . get_class($this) . ": Nie udało się zapisać cache: {$e->getMessage()}\n";
        }
    }

    // =========================================================================
    // Konwersja tickerów
    // =========================================================================

    public function broker2yahoo(string $tickerBroker, ?int $exchangeId = null): string
    {
        if ($tickerBroker === '') return $tickerBroker;

        $ticker = $tickerBroker;

        // 0. Sprawdź mapę wyjątków PRZED jakąkolwiek transformacją
        if (isset($this->tMapa[$ticker])) return $this->tMapa[$ticker];

        if ($exchangeId === null || !isset($this->EXCHANGE_MAP[$exchangeId])) return $ticker;

        $cfg = $this->EXCHANGE_MAP[$exchangeId];

        // 1. Usuń suffix dodany przez eToro
        $etoroSuffix = $cfg['remove_etoro_suffix'];
        if ($etoroSuffix !== '' && str_ends_with($ticker, $etoroSuffix)) {
            $ticker = substr($ticker, 0, -strlen($etoroSuffix));
        }

        // 2. Zastosuj reguły transformacji
        foreach ($cfg['rules'] as [$pattern, $replacement]) {
            $ticker = preg_replace('/' . $pattern . '/', $replacement, $ticker);
        }

        // 3. Specjalna obsługa Hong Kong - zawsze 4 cyfry
        if ($exchangeId === 21) {
            $tickerNumeric = preg_replace('/\D/', '', $ticker);
            if ($tickerNumeric !== '') {
                $ticker = substr(str_pad($tickerNumeric, 4, '0', STR_PAD_LEFT), -4);
            }
        }

        // 4. Dodaj Yahoo suffix
        if ($cfg['suffix'] !== '') $ticker .= $cfg['suffix'];

        return $ticker;
    }

    public function yahoo2broker(string $tickerYahoo): ?string
    {
        if (ctype_digit($tickerYahoo)) return $tickerYahoo;

        $instrumentsMap = $this->instrumenty(null, true);

        if (isset($instrumentsMap[$tickerYahoo])) {
            return $instrumentsMap[$tickerYahoo]['symbolId'];
        }

        // Sprawdź mapę odwrotną
        $reverseTMapa = array_flip($this->tMapa);
        if (isset($reverseTMapa[$tickerYahoo])) {
            $etoroTicker = $reverseTMapa[$tickerYahoo];
            foreach ($instrumentsMap as $info) {
                if (($info['SymbolFull'] ?? '') === $etoroTicker) return $info['symbolId'];
            }
        }

        // Szukaj po SymbolFull
        foreach ($instrumentsMap as $info) {
            if (($info['SymbolFull'] ?? '') === $tickerYahoo) return $info['symbolId'];
        }

        // Ostatnia próba - API search
        $result = $this->instrumentPobierz($tickerYahoo);
        if ($result && !empty($result['instrumentId'])) {
            return (string)$result['instrumentId'];
        }

        echo "[ERROR] " . get_class($this) . ": Nie znaleziono instrumentu dla tickera: {$tickerYahoo}\n";
        return null;
    }

    // =========================================================================
    // HTTP request
    // =========================================================================

    public function wykonaj(
        string  $endpoint,
        string  $method          = 'GET',
        ?array  $params          = null,
        ?array  $jsonData        = null,
        int     $retryCount      = 0,
        bool    $forceNewSession = false   // zachowany dla kompatybilności z Pythonem
    ): mixed {
        $url = $this->baseUrl . '/' . $endpoint;

        // Dodaj timestamp do GET aby uniknąć cache
        if ($method === 'GET') {
            if ($params === null) $params = [];
            $params['_t'] = (int)(microtime(true) * 1000);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_FORBID_REUSE   => true,
        ]);

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . (!empty($params) ? '?' . http_build_query($params) : ''));
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url . (!empty($params) ? '?' . http_build_query($params) : ''));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData !== null ? json_encode($jsonData) : '{}');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_URL, $url . (!empty($params) ? '?' . http_build_query($params) : ''));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else {
            curl_close($ch);
            throw new \InvalidArgumentException("Nieobsługiwana metoda HTTP: {$method}");
        }

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $curlError    = curl_error($ch);
        curl_close($ch);

        // Błąd połączenia / timeout
        if ($curlError !== '') {
            if ($retryCount < $this->maxRetries) {
                echo "[ERROR] " . get_class($this) . ": connection error - retry " . ($retryCount + 1) . "/{$this->maxRetries}\n";
                sleep($this->retryDelay);
                return $this->wykonaj($endpoint, $method, $params, $jsonData, $retryCount + 1);
            }
            echo "[ERROR] " . get_class($this) . ": connection error: {$curlError}\n";
            return null;
        }

        // Rate limit
        if ($httpCode === 429) {
            if ($retryCount < $this->maxRetries) {
                $wait = $this->retryDelay * (2 ** $retryCount);
                echo "[ERROR] " . get_class($this) . ": Rate limit - czekam {$wait}s\n";
                sleep($wait);
                return $this->wykonaj($endpoint, $method, $params, $jsonData, $retryCount + 1);
            }
            echo "[ERROR] " . get_class($this) . ": API rate limit exceeded\n";
            return null;
        }

        // Błędy serwera
        if (in_array($httpCode, [500, 502, 503], true)) {
            if ($retryCount < $this->maxRetries) {
                $wait = $this->retryDelay * (2 ** $retryCount);
                echo "[ERROR] " . get_class($this) . ": API error {$httpCode} - retry " . ($retryCount + 1) . "/{$this->maxRetries}\n";
                sleep($wait);
                return $this->wykonaj($endpoint, $method, $params, $jsonData, $retryCount + 1);
            }
            echo "[ERROR] " . get_class($this) . ": API unavailable - status {$httpCode}\n";
            return null;
        }

        // Pusta odpowiedź
        if (empty(trim($responseBody))) {
            echo "[ERROR] " . get_class($this) . ": API returned empty response for {$endpoint}\n";
            return null;
        }

        // Zły Content-Type
        if (strpos($contentType, 'application/json') === false) {
            echo "[WARNING] " . get_class($this) . ": unexpected Content-Type: {$contentType}\n";
            if ($retryCount < $this->maxRetries) {
                sleep($this->retryDelay);
                return $this->wykonaj($endpoint, $method, $params, $jsonData, $retryCount + 1);
            }
            return null;
        }

        // Błąd HTTP >= 400
        if ($httpCode >= 400) {
            echo "[ERROR] " . get_class($this) . ": API error {$httpCode}: " . substr($responseBody, 0, 200) . "\n";
            return null;
        }

        // Dekoduj JSON
        $decoded = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "[ERROR] " . get_class($this) . ": JSON decode failed for {$endpoint}\n";
            if ($retryCount < $this->maxRetries) {
                sleep($this->retryDelay);
                return $this->wykonaj($endpoint, $method, $params, $jsonData, $retryCount + 1);
            }
            return null;
        }

        return $decoded;
    }

    // =========================================================================
    // saldo()
    // =========================================================================

    public function saldo(): array|false
    {
        $dane = $this->wykonaj('trading/info/real/pnl');
        if ($dane === null) return false;

        try {
            $portfolio     = $dane['clientPortfolio'] ?? [];
            $credit        = (float)($portfolio['credit']        ?? 0);
            $unrealizedPnL = (float)($portfolio['unrealizedPnL'] ?? 0);
            $positions     = $portfolio['positions']     ?? [];
            $ordersForOpen = $portfolio['ordersForOpen'] ?? [];

            $inwestycja = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $positions));
            $zamrozone  = array_sum(array_map(fn($o) => (float)($o['amount'] ?? 0), $ordersForOpen));

            return [
                'saldo'      => $credit - $zamrozone,
                'inwestycja' => $inwestycja,
                'wartosc'    => $credit + $inwestycja + $unrealizedPnL,
                'bilans'     => $unrealizedPnL,
            ];
        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": saldo() parse error: {$e->getMessage()}\n";
            return false;
        }
    }

    // =========================================================================
    // pozycje()
    // =========================================================================

    public function pozycje(): array
    {
        $dane = $this->wykonaj('trading/info/real/pnl');
        if ($dane === null) return [];

        $instrumentsMap = $this->instrumenty(null, true);

        $idToInfo = [];
        foreach ($instrumentsMap as $tickerYahoo => $info) {
            $idToInfo[$info['symbolId']] = [
                'ticker'     => $tickerYahoo,
                'exchangeId' => $info['exchangeId'] ?? null,
            ];
        }

        $tempPositions = [];
        $positions     = $dane['clientPortfolio']['positions'] ?? [];

        foreach ($positions as $pos) {
            $instrumentId = $pos['instrumentID'] ?? null;
            if ($instrumentId === null) continue;

            $idStr     = (string)$instrumentId;
            $info      = $idToInfo[$idStr] ?? [];
            $ticker    = $info['ticker'] ?? $idStr;

            $unrealized = $pos['unrealizedPnL'] ?? [];
            $closeRate  = (float)($unrealized['closeRate'] ?? $pos['openRate'] ?? 0);
            $pnl        = (float)($unrealized['pnL']       ?? 0);
            $units      = (float)($pos['units']    ?? 0);
            $openRate   = (float)($pos['openRate'] ?? 0);

            $timestamp = null;
            if (!empty($pos['openDateTime'])) {
                try {
                    $timestamp = (new \DateTime($pos['openDateTime']))->getTimestamp();
                } catch (\Throwable) {}
            }

            if (isset($tempPositions[$ticker])) {
                $e = &$tempPositions[$ticker];
                $newTotal = $e['wolumen'] + $units;
                if ($newTotal > 0) {
                    $e['open']  = ($e['open']  * $e['wolumen'] + $openRate  * $units) / $newTotal;
                    $e['close'] = ($e['close'] * $e['wolumen'] + $closeRate * $units) / $newTotal;
                }
                $e['wolumen'] += $units;
                $e['bilans']  += $pnl;
                $e['ilosc']   += 1;
                if ($timestamp !== null && ($e['odKiedy'] === null || $timestamp < $e['odKiedy'])) {
                    $e['odKiedy'] = $timestamp;
                }
                unset($e);
            } else {
                $tempPositions[$ticker] = [
                    'tickerBroker' => $idStr,
                    'wolumen'      => $units,
                    'open'         => $openRate,
                    'close'        => $closeRate,
                    'bilans'       => $pnl,
                    'odKiedy'      => $timestamp,
                    'zrodlo'       => 'ET',
                    'ilosc'        => 1,
                ];
            }
        }

        return $tempPositions;
    }

    // =========================================================================
    // handluj()
    // =========================================================================

    public function handluj(string $ticker, float $ilosc, bool $extendedHours = false): array|null
    {
        $tickerBroker = $this->yahoo2broker($ticker);
        if ($tickerBroker === null) {
            return ['error' => "Nie znaleziono instrumentu dla tickera: {$ticker}"];
        }

        if ($ilosc == 0) return ['error' => 'Ilość musi być różna od zera'];

        $instrumentId = (int)$tickerBroker;
        $units        = abs($ilosc);

        return $ilosc > 0
            ? $this->kup($instrumentId, $units)
            : $this->sprzedaj($instrumentId, $tickerBroker, $ticker, $units);
    }

    // =========================================================================
    // _kup()
    // =========================================================================

    private function kup(int $instrumentId, float $units): array|null
    {
        // KROK 1: Stan pozycji PRZED złożeniem zlecenia
        $positionsBefore = $this->pobierzPozycjeInstrumentu($instrumentId);

        // KROK 2: Saldo PRZED
        $saldoBefore = ($sd = $this->saldo()) ? (float)($sd['saldo'] ?? 0) : 0.0;

        $payload = [
            'InstrumentID'   => $instrumentId,
            'IsBuy'          => true,
            'Leverage'       => 1,
            'AmountInUnits'  => $units,
            'IsNoStopLoss'   => true,
            'IsNoTakeProfit' => true,
        ];

        try {
            $dane = $this->wykonaj('trading/execution/market-open-orders/by-units', 'POST', null, $payload);

            if ($dane === null) return null;

            // WERYFIKACJA 1: errorMessageCode
            $errorCode = $dane['errorMessageCode'] ?? null;
            if ($errorCode && $errorCode !== 0) {
                $msg = $dane['errorMessage'] ?? $dane['message'] ?? "Error code: {$errorCode}";
                $this->oczekujaceUsun();
                return ['error' => $msg];
            }

            // WERYFIKACJA 2: klucze error
            if (isset($dane['error']) || isset($dane['errorMessage'])) {
                $msg = $dane['errorMessage'] ?? $dane['error'];
                $this->oczekujaceUsun();
                return ['error' => $msg];
            }

            $orderData = $dane['orderForOpen'] ?? [];
            if (empty($orderData)) {
                $this->oczekujaceUsun();
                return ['error' => 'No orderForOpen in response'];
            }

            $orderId = $orderData['orderID'] ?? null;
            if (!$orderId) {
                $this->oczekujaceUsun();
                return ['error' => 'No order ID returned'];
            }

            // DEBUG: Stan przed
            echo "\n[DEBUG] Stan PRZED złożeniem zlecenia:\n";
            echo "  Instrument ID: {$instrumentId}\n";
            printf("  Saldo: \$%.2f\n", $saldoBefore);
            echo "  Liczba pozycji: " . count($positionsBefore) . "\n";
            if ($positionsBefore) {
                foreach ($positionsBefore as $i => $pos) {
                    printf("  #%d: posID=%s, orderID=%s, units=%.4f, amount=\$%.2f\n",
                        $i + 1, $pos['positionID'], $pos['orderID'], $pos['units'], $pos['amount']);
                }
            } else {
                echo "  (brak pozycji dla tego instrumentu)\n";
            }

            $maxAttempts = 20;
            $waitTime    = 5; // sekund

            echo "\n[DEBUG] Rozpoczynam weryfikację (max {$maxAttempts} prób co {$waitTime}s)...\n";

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                sleep($waitTime);

                echo "\n[DEBUG] Próba {$attempt}/{$maxAttempts}:\n";

                $saldoAfter = ($sd = $this->saldo()) ? (float)($sd['saldo'] ?? 0) : 0.0;
                $saldoDiff  = $saldoBefore - $saldoAfter;

                printf("  Saldo: before=\$%.2f, after=\$%.2f, diff=\$%.2f\n", $saldoBefore, $saldoAfter, $saldoDiff);

                $verification = $this->pozycjaSprawdz((string)$orderId, $instrumentId, $positionsBefore);

                echo "  Result: exists=" . ($verification['exists'] ? 'true' : 'false')
                    . ", reason='{$verification['reason']}'\n";

                // Saldo spadło znacząco, pozycja może być jeszcze w cache
                if ($saldoDiff > 5.0 && !$verification['exists']) {
                    printf("  ✓ SUKCES - saldo się zmniejszyło o \$%.2f (pozycje mogą być w cache)\n", $saldoDiff);
                    $this->oczekujaceUsun();
                    return ['id' => $orderId];
                }

                if ($verification['exists']) {
                    echo "  ✓ SUKCES - pozycja zweryfikowana!\n";
                    $this->oczekujaceUsun();
                    return ['id' => $orderId];
                }

                // Zlecenie pending - rynek zamknięty
                if (strpos($verification['reason'], 'Pending') !== false) {
                    echo "  ✗ Zlecenie w pending - rynek zamknięty\n";
                    $this->oczekujacaUsun((string)$orderId);
                    $this->oczekujaceUsun();
                    return ['error' => $verification['reason']];
                }

                if ($attempt === $maxAttempts) {
                    echo "  ✗ Ostatnia próba - brak zmian w pozycjach\n";
                    $this->oczekujacaUsun((string)$orderId);
                    $this->oczekujaceUsun();
                    return ['error' => "{$verification['reason']} (verified {$maxAttempts} times)"];
                }

                echo "  → Retry za {$waitTime}s...\n";
            }

            // Fallback
            $this->oczekujaceUsun();
            return ['id' => $orderId];

        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": kup() exception: {$e->getMessage()}\n";
            $this->oczekujaceUsun();
            return null;
        }
    }

    // =========================================================================
    // _pobierz_pozycje_instrumentu()
    // =========================================================================

    private function pobierzPozycjeInstrumentu(int $instrumentId, bool $forceFresh = false): array
    {
        try {
            $dane = $this->wykonaj('trading/info/real/pnl', 'GET', null, null, 0, $forceFresh);
            if ($dane === null) return [];

            $idStr    = (string)$instrumentId;
            $matching = [];

            foreach ($dane['clientPortfolio']['positions'] ?? [] as $pos) {
                if ((string)($pos['instrumentID'] ?? '') === $idStr) {
                    $matching[] = [
                        'positionID' => $pos['positionID'],
                        'orderID'    => $pos['orderID'],
                        'units'      => (float)($pos['units']  ?? 0),
                        'amount'     => (float)($pos['amount'] ?? 0),
                    ];
                }
            }

            return $matching;

        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": pobierzPozycjeInstrumentu() error: {$e->getMessage()}\n";
            return [];
        }
    }

    // =========================================================================
    // _pozycjaSprawdz()
    // =========================================================================

    private function pozycjaSprawdz(string $orderId, int $instrumentId, array $positionsBefore): array
    {
        try {
            $dane = $this->wykonaj('trading/info/real/pnl', 'GET', null, null, 0, true);
            if ($dane === null) {
                return ['exists' => false, 'reason' => 'Cannot verify - API error', 'details' => []];
            }

            $portfolio = $dane['clientPortfolio'] ?? [];

            // KROK 1: Czy zlecenie jest w ordersForOpen?
            foreach ($portfolio['ordersForOpen'] ?? [] as $order) {
                if ((string)($order['orderID'] ?? '') === $orderId) {
                    return ['exists' => false, 'reason' => 'Order Pending. Market closed?', 'details' => $order];
                }
            }

            // KROK 2: Czy istnieje pozycja z naszym orderID?
            foreach ($portfolio['positions'] ?? [] as $pos) {
                if ((string)($pos['orderID'] ?? '') === $orderId) {
                    return ['exists' => true, 'reason' => 'Position created (matched by orderID)', 'details' => $pos];
                }
            }

            // KROK 3: Porównaj liczbę pozycji
            $positionsAfter = $this->pobierzPozycjeInstrumentu($instrumentId, true);

            echo "  Pozycje PO sprawdzeniu (FRESH):\n";
            echo "    Liczba pozycji: " . count($positionsAfter) . "\n";
            foreach ($positionsAfter as $i => $pos) {
                printf("    #%d: posID=%s, orderID=%s, units=%.4f, amount=\$%.2f\n",
                    $i + 1, $pos['positionID'], $pos['orderID'], $pos['units'], $pos['amount']);
            }

            if (count($positionsAfter) > count($positionsBefore)) {
                $b = count($positionsBefore);
                $a = count($positionsAfter);
                return ['exists' => true, 'reason' => "New position detected (before: {$b}, after: {$a})", 'details' => $positionsAfter];
            }

            // KROK 4: Czy units się zwiększyły?
            $totalBefore = array_sum(array_column($positionsBefore, 'units'));
            $totalAfter  = array_sum(array_column($positionsAfter,  'units'));

            printf("  Total units: before=%.6f, after=%.6f, diff=%.6f\n",
                $totalBefore, $totalAfter, $totalAfter - $totalBefore);

            if ($totalAfter > $totalBefore + 0.0001) {
                return [
                    'exists'  => true,
                    'reason'  => sprintf('Units increased (before: %.4f, after: %.4f)', $totalBefore, $totalAfter),
                    'details' => $positionsAfter,
                ];
            }

            // KROK 5: Brak zmian
            return ['exists' => false, 'reason' => 'Order rejected or canceled (no changes detected)', 'details' => []];

        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": pozycjaSprawdz() error: {$e->getMessage()}\n";
            return ['exists' => false, 'reason' => "Verification error: {$e->getMessage()}", 'details' => []];
        }
    }

    // =========================================================================
    // _oczekujacaSprawdz()
    // =========================================================================

    private function oczekujacaSprawdz(string $orderId, int $instrumentId): bool
    {
        try {
            $dane = $this->wykonaj('trading/info/real/pnl');
            if ($dane === null) return false;

            $portfolio = $dane['clientPortfolio'] ?? [];

            foreach ($portfolio['ordersForOpen'] ?? [] as $order) {
                if ((string)($order['orderID'] ?? '') === $orderId) return true;
            }

            $idStr = (string)$instrumentId;
            foreach ($portfolio['positions'] ?? [] as $pos) {
                if ((string)($pos['instrumentID'] ?? '') === $idStr) return false;
            }

            return true; // Bezpieczniejsze założenie

        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": oczekujacaSprawdz() error: {$e->getMessage()}\n";
            return false;
        }
    }

    // =========================================================================
    // _oczekujacaUsun()
    // =========================================================================

    private function oczekujacaUsun(string $orderId): bool
    {
        try {
            $dane = $this->wykonaj("trading/execution/market-open-orders/{$orderId}", 'DELETE');
            if ($dane === null) {
                echo "[WARNING] " . get_class($this) . ": Nie udało się usunąć pending order {$orderId}\n";
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": oczekujacaUsun({$orderId}) error: {$e->getMessage()}\n";
            return false;
        }
    }

    // =========================================================================
    // _oczekujaceUsun()
    // =========================================================================

    private function oczekujaceUsun(): array
    {
        try {
            $dane = $this->wykonaj('trading/info/real/pnl');
            if ($dane === null) return ['usunieto' => 0, 'bledy' => 0];

            $ordersForOpen = $dane['clientPortfolio']['ordersForOpen'] ?? [];
            if (empty($ordersForOpen)) return ['usunieto' => 0, 'bledy' => 0];

            $count = count($ordersForOpen);
            echo "[INFO] " . get_class($this) . ": Znaleziono {$count} pending orders - usuwam...\n";

            $usunieto = $bledy = 0;
            foreach ($ordersForOpen as $order) {
                $orderId = $order['orderID'] ?? null;
                if ($orderId) {
                    $this->oczekujacaUsun((string)$orderId) ? $usunieto++ : $bledy++;
                }
            }

            return ['usunieto' => $usunieto, 'bledy' => $bledy];

        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": oczekujaceUsun() error: {$e->getMessage()}\n";
            return ['usunieto' => 0, 'bledy' => 0];
        }
    }

    // =========================================================================
    // _sprzedaj()
    // =========================================================================

    private function sprzedaj(int $instrumentId, string $tickerBroker, string $ticker, float $units): array|null
    {
        $danePnl = $this->wykonaj('trading/info/real/pnl');
        if ($danePnl === null) return ['error' => 'Nie udało się pobrać informacji o pozycjach'];

        $positions = $danePnl['clientPortfolio']['positions'] ?? [];

        // Znajdź wszystkie pozycje dla danego instrumentu
        $matchingPositions = [];
        foreach ($positions as $pos) {
            if ((string)($pos['instrumentID'] ?? '') !== $tickerBroker) continue;

            $posUnits   = (float)($pos['units']    ?? 0);
            $openRate   = (float)($pos['openRate'] ?? 0);
            $unrealized = $pos['unrealizedPnL'] ?? [];
            $closeRate  = (float)($unrealized['closeRate'] ?? $openRate);

            $positionValue = $posUnits * $closeRate;
            $pnlPercent    = $openRate > 0 ? (($closeRate / $openRate - 1) * 100) : 0;

            $matchingPositions[] = [
                'positionID'  => $pos['positionID'],
                'units'       => $posUnits,
                'value'       => $positionValue,
                'openRate'    => $openRate,
                'closeRate'   => $closeRate,
                'pnl_percent' => $pnlPercent,
                'is_large'    => $positionValue >= 20.0,
            ];
        }

        $sep = str_repeat('=', 80);
        echo "\n{$sep}\nDEBUG: WSZYSTKIE OTWARTE POZYCJE DLA {$ticker} (instrument_id={$tickerBroker})\n{$sep}\n";

        if (empty($matchingPositions)) {
            echo "BRAK POZYCJI!\n";
            return ['error' => "Brak otwartej pozycji dla {$ticker}"];
        }

        foreach ($matchingPositions as $i => $pos) {
            $n = $i + 1;
            echo "\nPozycja #{$n}:\n";
            echo "  Position ID: {$pos['positionID']}\n";
            printf("  Units: %.4f\n  Value: \$%.2f\n  Open Rate: \$%.4f\n  Close Rate: \$%.4f\n  P&L: %.2f%%\n",
                $pos['units'], $pos['value'], $pos['openRate'], $pos['closeRate'], $pos['pnl_percent']);
            echo "  Is Large (>=20\$): " . ($pos['is_large'] ? 'true' : 'false') . "\n";
        }

        $totalUnits = array_sum(array_column($matchingPositions, 'units'));
        printf("\nCałkowita liczba units: %.4f\nŻądana liczba units do sprzedaży: %.4f\n", $totalUnits, $units);

        if ($units > $totalUnits) {
            return ['error' => "Próba sprzedaży {$units} jednostek, dostępne: {$totalUnits}"];
        }

        // Sortuj: najpierw zyskowne malejąco, potem stratne od najmniejszej straty
        usort($matchingPositions, function ($a, $b) {
            $aSign = $a['pnl_percent'] > 0 ? -1 : 1;
            $bSign = $b['pnl_percent'] > 0 ? -1 : 1;
            if ($aSign !== $bSign) return $aSign - $bSign;
            return $b['pnl_percent'] <=> $a['pnl_percent'];
        });

        echo "\n{$sep}\nPOZYCJE PO SORTOWANIU (priorytet zamykania):\n{$sep}\n";
        foreach ($matchingPositions as $i => $pos) {
            printf("%d. Position %s: %.4f units, \$%.2f, P&L: %.2f%%\n",
                $i + 1, $pos['positionID'], $pos['units'], $pos['value'], $pos['pnl_percent']);
        }

        $MIN_VALUE      = 10.5;
        $pozostalo      = $units;
        $positionsToClose = [];

        echo "\n{$sep}\nALGORYTM ZAMYKANIA:\n{$sep}\n";

        // --- FAZA 1: Zamykanie dużych pozycji (>= 20$) ---
        echo "\nFAZA 1: Częściowe zamykanie dużych pozycji (>=20\$)\n";
        foreach ($matchingPositions as $pos) {
            if ($pozostalo <= 0) break;

            if (!$pos['is_large']) {
                printf("  Pomijam pozycję %s - za mała (\$%.2f)\n", $pos['positionID'], $pos['value']);
                continue;
            }

            $positionId     = $pos['positionID'];
            $closeRate      = $pos['closeRate'];
            $availableUnits = $pos['units'];
            $TOLERANCE      = 0.0001;
            $isFullClose    = abs($pozostalo - $availableUnits) <= $TOLERANCE || $pozostalo >= $availableUnits;

            if ($isFullClose) {
                printf("  ✓ Position %s: CAŁKOWITE zamknięcie %.6f units (\$%.2f)\n",
                    $positionId, $availableUnits, $pos['value']);
                $positionsToClose[] = [
                    'positionID'     => $positionId,
                    'instrument_id'  => $instrumentId,
                    'units_to_close' => null,
                    'close_value'    => $pos['value'],
                    'phase'          => 'FAZA 1 - całkowite',
                    'actual_units'   => $availableUnits,
                ];
                $pozostalo -= $availableUnits;
                continue;
            }

            $minUnitsToKeep  = (int)ceil($MIN_VALUE / $closeRate);
            $maxUnitsToClose = $availableUnits - $minUnitsToKeep;

            if ($maxUnitsToClose <= 0) {
                echo "  Pomijam pozycję {$positionId} - nie można częściowo zamknąć\n";
                continue;
            }

            $unitsToClose   = min($pozostalo, $maxUnitsToClose);
            $closeValue     = $unitsToClose * $closeRate;
            $remainingValue = ($availableUnits - $unitsToClose) * $closeRate;

            if ($closeValue < $MIN_VALUE) {
                printf("  Pomijam pozycję %s - wartość zamknięcia \$%.2f < \$%.1f\n", $positionId, $closeValue, $MIN_VALUE);
                continue;
            }
            if ($remainingValue < $MIN_VALUE) {
                printf("  Pomijam pozycję %s - pozostała wartość \$%.2f < \$%.1f\n", $positionId, $remainingValue, $MIN_VALUE);
                continue;
            }

            printf("  ✓ Position %s: częściowe zamknięcie %.6f units (\$%.2f), zostanie %.6f units (\$%.2f)\n",
                $positionId, $unitsToClose, $closeValue, $availableUnits - $unitsToClose, $remainingValue);

            $positionsToClose[] = [
                'positionID'     => $positionId,
                'instrument_id'  => $instrumentId,
                'units_to_close' => $unitsToClose,
                'close_value'    => $closeValue,
                'phase'          => 'FAZA 1 - częściowe',
                'actual_units'   => $unitsToClose,
            ];
            $pozostalo -= $unitsToClose;
        }

        // --- FAZA 2: Best fit - jedna mała pozycja ---
        if ($pozostalo > 0) {
            printf("\nFAZA 2: Best fit dla pozostałych %.6f units\n", $pozostalo);
            $smallPositions = array_values(array_filter($matchingPositions, fn($p) => !$p['is_large']));

            if (!empty($smallPositions)) {
                $maxSmallUnits = max(array_column($smallPositions, 'units'));

                if ($pozostalo <= $maxSmallUnits) {
                    $bestPosition = null;
                    $bestScore    = -PHP_FLOAT_MAX;

                    foreach ($smallPositions as $pos) {
                        $score = $pos['pnl_percent'] * 100 - abs($pos['units'] - $pozostalo);
                        if ($score > $bestScore) {
                            $bestScore    = $score;
                            $bestPosition = $pos;
                        }
                    }

                    if ($bestPosition !== null) {
                        printf("  ✓ Best fit: Position %s: CAŁKOWITE zamknięcie %.6f units (\$%.2f)\n",
                            $bestPosition['positionID'], $bestPosition['units'], $bestPosition['value']);
                        $positionsToClose[] = [
                            'positionID'     => $bestPosition['positionID'],
                            'instrument_id'  => $instrumentId,
                            'units_to_close' => null,
                            'close_value'    => $bestPosition['value'],
                            'phase'          => 'FAZA 2 - best fit',
                            'actual_units'   => $bestPosition['units'],
                        ];
                        $pozostalo -= $bestPosition['units'];
                    }
                }
            }
        }

        // --- FAZA 3: Kolejne małe pozycje ---
        if ($pozostalo > 0) {
            printf("\nFAZA 3: Zamykanie kolejnych małych pozycji (pozostało %.6f units)\n", $pozostalo);
            $selectedIds    = array_column($positionsToClose, 'positionID');
            $smallPositions = array_filter($matchingPositions, fn($p) => !$p['is_large']);

            foreach ($smallPositions as $pos) {
                if ($pozostalo <= 0) break;
                if (in_array($pos['positionID'], $selectedIds, true)) continue;

                printf("  ✓ Position %s: CAŁKOWITE zamknięcie %.6f units (\$%.2f)\n",
                    $pos['positionID'], $pos['units'], $pos['value']);
                $positionsToClose[] = [
                    'positionID'     => $pos['positionID'],
                    'instrument_id'  => $instrumentId,
                    'units_to_close' => null,
                    'close_value'    => $pos['value'],
                    'phase'          => 'FAZA 3 - kolejne małe',
                    'actual_units'   => $pos['units'],
                ];
                $pozostalo -= $pos['units'];
            }
        }

        // --- Podsumowanie wyboru ---
        echo "\n{$sep}\nPOZYCJE WYBRANE DO ZAMKNIĘCIA:\n{$sep}\n";

        if (empty($positionsToClose)) {
            echo "BRAK! Nie udało się wybrać żadnej pozycji.\n";
            return ['error' => 'Nie udało się wybrać pozycji do zamknięcia'];
        }

        foreach ($positionsToClose as $i => $pos) {
            $n = $i + 1;
            echo "\n#{$n}: {$pos['phase']}\n";
            echo "  Position ID: {$pos['positionID']}\n";
            $unitsDisplay  = $pos['units_to_close'] === null ? 'WSZYSTKIE' : sprintf('%.6f', $pos['units_to_close']);
            $actualDisplay = isset($pos['actual_units']) ? sprintf(' (%.6f actual)', $pos['actual_units']) : '';
            echo "  Units to close: {$unitsDisplay}{$actualDisplay}\n";
            printf("  Close value: \$%.2f\n", $pos['close_value']);
        }

        $zamknieto = $units - $pozostalo;
        echo "\n{$sep}\nPODSUMOWANIE:\n";
        printf("  Żądano: %.4f units\n  Zostanie zamknięte: %.4f units\n  Pozostało niezamknięte: %.4f units\n",
            $units, $zamknieto, $pozostalo);
        echo "{$sep}\n\n";

        // --- Wysyłanie zleceń ---
        echo "{$sep}\nWYSYŁANIE ZLECEŃ DO BROKERA:\n{$sep}\n\n";

        $closedOrders = [];
        foreach ($positionsToClose as $i => $posToClose) {
            $posId        = $posToClose['positionID'];
            $instId       = $posToClose['instrument_id'];
            $unitsToClose = $posToClose['units_to_close'];

            $n = $i + 1;
            echo "Zlecenie #{$n}: {$posToClose['phase']}\n  Position ID: {$posId}\n";
            $unitsDisplay  = $unitsToClose === null ? 'WSZYSTKIE (null)' : sprintf('%.6f', $unitsToClose);
            $actualDisplay = isset($posToClose['actual_units']) ? sprintf(' (%.6f actual)', $posToClose['actual_units']) : '';
            echo "  Units to close: {$unitsDisplay}{$actualDisplay}\n";
            printf("  Value: \$%.2f\n", $posToClose['close_value']);

            $result = $this->pozycjaZamknij($posId, $instId, $unitsToClose);
            if ($result !== null) {
                $closedOrders[] = $result;
                echo "  ✓ SUKCES - Order ID: {$result}\n\n";
            } else {
                echo "  ✗ BŁĄD - nie udało się zamknąć pozycji\n\n";
            }
        }

        echo "{$sep}\n\n";

        if (empty($closedOrders)) return ['error' => 'Nie udało się zamknąć żadnej pozycji'];

        $id = count($closedOrders) > 1 ? $closedOrders : $closedOrders[0];

        return $zamknieto == $units
            ? ['id' => $id]
            : ['warning' => "Zamknięto {$zamknieto} z {$units} jednostek", 'id' => $id];
    }

    // =========================================================================
    // _pozycjaZamknij()
    // =========================================================================

    private function pozycjaZamknij(string $positionId, int $instrumentId, ?float $unitsToClose): ?string
    {
        $endpoint = "trading/execution/market-close-orders/positions/{$positionId}";

        $payload = [
            'InstrumentId'  => $instrumentId,
            'UnitsToDeduct' => $unitsToClose,
        ];

        try {
            echo "  [REQUEST] POST {$endpoint}\n";
            echo "  [PAYLOAD] " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

            $dane = $this->wykonaj($endpoint, 'POST', null, $payload);

            echo "  [RESPONSE]\n";
            echo $dane !== null
                ? json_encode($dane, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n"
                : "  None (błąd API)\n\n";

            if ($dane) {
                if (isset($dane['orderForClose']['orderID'])) return (string)$dane['orderForClose']['orderID'];
                $fallback = $dane['orderId'] ?? $dane['orderID'] ?? $dane['id'] ?? null;
                if ($fallback) return (string)$fallback;
            }
        } catch (\Throwable $e) {
            echo "  [EXCEPTION] Błąd zamykania pozycji {$positionId}: {$e->getMessage()}\n\n";
        }

        return null;
    }

    // =========================================================================
    // Instrumenty
    // =========================================================================

    private function getRawInstruments(bool $cache = true): array
    {
        if ($cache && isset($this->cacheInstrumenty['all'])) {
            return $this->cacheInstrumenty['all'];
        }

        $cacheFile = $this->cacheDir . '/instruments_all.json';
        $daneCache = null;

        if (file_exists($cacheFile)) {
            $daneCache = $this->loadCache($cacheFile);
            if ($this->isCacheFresh($cacheFile)) {
                $this->cacheInstrumenty['all'] = $daneCache;
                echo "[INFO] " . get_class($this) . ": Cache dyskowy załadowany dla instrumentów\n";
                return $daneCache;
            }
        }

        echo "[INFO] " . get_class($this) . ": Odświeżam instrumenty z API\n";

        try {
            $ch = curl_init('https://api.etorostatic.com/sapi/instrumentsmetadata/V1.1/instruments');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data            = json_decode($response, true);
                $instrumentsList = $data['InstrumentDisplayDatas'] ?? [];

                if (!empty($instrumentsList)) {
                    $this->saveCache($cacheFile, $instrumentsList);
                    $this->cacheInstrumenty['all'] = $instrumentsList;
                    $count = count($instrumentsList);
                    echo "[INFO] " . get_class($this) . ": API sukces, cache zaktualizowany ({$count} instrumentów)\n";
                    return $instrumentsList;
                }

                echo "[WARNING] " . get_class($this) . ": API zwróciło pustą listę\n";
            }
        } catch (\Throwable $e) {
            echo "[WARNING] " . get_class($this) . ": API niedostępne: {$e->getMessage()}\n";
        }

        if ($daneCache !== null) {
            $this->cacheInstrumenty['all'] = $daneCache;
            echo "[INFO] " . get_class($this) . ": Fallback cache dyskowy dla instrumentów\n";
            return $daneCache;
        }

        echo "[ERROR] " . get_class($this) . ": Brak jakiegokolwiek cache dla instrumentów\n";
        return [];
    }

    public function instrumenty(?string $gielda = null, bool $cache = true): array
    {
        $instrumentsList = $this->getRawInstruments($cache);
        $wynik = [];

        foreach ($instrumentsList as $rekord) {
            $instrumentId = $rekord['InstrumentID'] ?? null;
            if (!$instrumentId) continue;

            $symbolFull  = $rekord['SymbolFull']            ?? '';
            $displayName = $rekord['InstrumentDisplayName'] ?? '';
            $exchangeId  = $rekord['ExchangeID']            ?? null;

            $tickerYahoo = $this->broker2yahoo($symbolFull, $exchangeId);
            if (!$tickerYahoo) $tickerYahoo = (string)$instrumentId;

            $wynik[$tickerYahoo] = [
                'symbolId'    => (string)$instrumentId,
                'name'        => $displayName,
                'type'        => $rekord['InstrumentTypeID'] ?? null,
                'SymbolFull'  => $symbolFull,
                'exchangeId'  => $exchangeId,
                'priceSource' => $rekord['PriceSource'] ?? '',
            ];
        }

        return $wynik;
    }

    private function instrumentPobierz(string $ticker): ?array
    {
        $url = 'https://www.etoro.com/sapi/trade-data-real/instruments/search?'
             . http_build_query(['query' => strtoupper($ticker), 'limit' => 10]);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (!empty($data)) {
                    $instrument   = $data[0];
                    $instrumentId = $instrument['InstrumentID'] ?? $instrument['instrumentId'] ?? null;
                    $displayName  = $instrument['SymbolFull']   ?? $instrument['displayName']  ?? $ticker;
                    return ['instrumentId' => $instrumentId, 'displayName' => $displayName, 'data' => $instrument];
                }
            }
        } catch (\Throwable $e) {
            echo "[ERROR] " . get_class($this) . ": instrumentPobierz({$ticker}) failed: {$e->getMessage()}\n";
        }

        return null;
    }
}
