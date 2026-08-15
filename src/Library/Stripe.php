<?php

namespace Phoenix\Core\Library;

class Stripe
{
    public array $error = [];
    public ?array $dane = null;

    public function generuj(string $klucz, float|int $kwota, string $waluta, int|string $TID, string $opis, string $urlTAK, string $urlNIE, ?string $PMC = null): string|false
    {
        if (!is_numeric($kwota) || $kwota <= 0) {
            $this->error[] = 'Nieprawidłowa kwota';
            return false;
        }

        $kwotaInt = (int) round($kwota * 100);

        if (empty($waluta) || strlen($waluta) !== 3) {
            $this->error[] = 'Nieprawidłowa waluta — podaj kod ISO 4217 (np. GBP, USD, EUR)';
            return false;
        }

        $daneParametry = [
            'line_items[0][price_data][currency]'           => strtolower($waluta),
            'line_items[0][price_data][unit_amount]'        => $kwotaInt,
            'line_items[0][price_data][product_data][name]' => $opis,
            'line_items[0][quantity]'                       => 1,
            'mode'                                          => 'payment',
            'metadata[transaction_id]'                      => $TID,
            'success_url'                                   => $urlTAK,
            'cancel_url'                                    => $urlNIE,
        ];

        if (!empty($PMC)) {
            $daneParametry['payment_method_configuration'] = $PMC;
        }

        $parametry = http_build_query($daneParametry);

        $polaczenie = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($polaczenie, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $klucz . ':',
            CURLOPT_POSTFIELDS     => $parametry,
        ]);

        $odpowiedzSurowa = curl_exec($polaczenie);
        $kodHttp         = curl_getinfo($polaczenie, CURLINFO_HTTP_CODE);
        curl_close($polaczenie);

        $odpowiedz = json_decode($odpowiedzSurowa, true);

        if ($kodHttp !== 200 || empty($odpowiedz['url'])) {
            $this->error[] = 'Błąd Stripe API: ' . ($odpowiedz['error']['message'] ?? 'Nieznany błąd');
            return false;
        }

        return $odpowiedz['url'];
    }

    public function weryfikuj(string $klucz): bool
    {
        $zawartosc  = file_get_contents('php://input');
        $this->dane = json_decode($zawartosc, true);
        $naglowek   = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($naglowek)) {
            $this->error[] = 'Brak nagłówka Stripe-Signature';
            return false;
        }

        $znacznikCzasu = null;
        $podpis        = null;

        foreach (explode(',', $naglowek) as $czesc) {
            $pary = explode('=', $czesc, 2);
            if (count($pary) !== 2) {
                continue;
            }
            [$pole, $wartosc] = $pary;
            if ($pole === 't')  $znacznikCzasu = $wartosc;
            if ($pole === 'v1') $podpis        = $wartosc;
        }

        if (!$znacznikCzasu || !$podpis) {
            $this->error[] = 'Nieprawidłowy format nagłówka Stripe-Signature';
            return false;
        }

        $podpisanaZawartosc = $znacznikCzasu . '.' . $zawartosc;
        $oczekiwanyPodpis   = hash_hmac('sha256', $podpisanaZawartosc, $klucz);

        if (!hash_equals($oczekiwanyPodpis, $podpis)) {
            $this->error[] = 'Nieprawidłowy podpis webhooka';
            return false;
        }

        if (abs(time() - (int)$znacznikCzasu) > 300) {
            $this->error[] = 'Webhook zbyt stary — możliwy replay attack';
            return false;
        }

        return true;
    }
}