<?php

namespace Phoenix\Core\Library;

class Opennode {

    public $error = [];

    public function generuj($klucz, $kwota, $waluta, $TID, $opis, $urlTAK, $urlWebhook) {

        if (!is_numeric($kwota) || $kwota <= 0) {
            $this->error[] = 'Nieprawidłowa kwota';
            return false;
        }

        if (empty($waluta) || strlen($waluta) !== 3) {
            $this->error[] = 'Nieprawidłowa waluta — podaj kod ISO 4217 (np. USD, EUR, GBP)';
            return false;
        }

        if (strtoupper($waluta) === 'BTC') $kwota = (int) round($kwota * 100000000);

        $dane = [
            'amount'       => $kwota,
            'currency'     => strtoupper($waluta),
            'description'  => $opis,
            'callback_url' => $urlWebhook,
            'success_url'  => $urlTAK,
        ];

        if ($TID !== null) $dane['order_id'] = $TID;

        $dane = json_encode($dane);

        $polaczenie = curl_init('https://api.opennode.com/v1/charges');
        curl_setopt_array($polaczenie, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $dane,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $klucz,
                'Content-Type: application/json',
            ],
        ]);

        $odpowiedz = json_decode(curl_exec($polaczenie), true);
        $kodHttp   = curl_getinfo($polaczenie, CURLINFO_HTTP_CODE);
        curl_close($polaczenie);

        if ($kodHttp !== 200 || empty($odpowiedz['data']['hosted_checkout_url'])) {
            $this->error[] = 'Błąd OpenNode API: ' . ($odpowiedz['message'] ?? 'Nieznany błąd');
            return false;
        }

        return $odpowiedz['data']['hosted_checkout_url'];
    }

    public function weryfikuj($klucz) {

        $dane = $_POST;

        if (empty($dane) || !is_array($dane)) {
            $this->error[] = 'Brak danych webhooka';
            return false;
        }

        if (empty($dane['hashed_order']) || empty($dane['id'])) {
            $this->error[] = 'Nieprawidłowa struktura webhooka';
            return false;
        }

        $oczekiwanyPodpis = hash_hmac('sha256', $dane['id'], $klucz);

        if (!hash_equals($oczekiwanyPodpis, $dane['hashed_order'])) {
            $this->error[] = 'Nieprawidłowy podpis webhooka';
            return false;
        }

        return true;
    }

}