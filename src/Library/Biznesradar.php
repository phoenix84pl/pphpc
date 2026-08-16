<?php

namespace Phoenix\Core\Library;

use DOMDocument;
use DOMXPath;

class Biznesradar
{
    public array $error = [];

    public function __construct()
    {
    }

    private function _linkGeneruj($ticker, $funkcja): string
    {
        return "https://www.biznesradar.pl/{$funkcja}/{$ticker}";
    }

    private function _get($ticker, $funkcja)
    {
        $url = $this->_linkGeneruj($ticker, $funkcja);
        
        $options = [
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ]
        ];
        $context = stream_context_create($options);
        
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            $this->error[] = "Nie udało się pobrać danych dla tickera: {$ticker}";
            return false;
        }

        $oDOM = new DOMDocument();
        libxml_use_internal_errors(true);
        $oDOM->loadHTML($html);
        libxml_clear_errors();
        
        $oXPath = new DOMXPath($oDOM);
        $tabela = $oXPath->query('//table[contains(@class, "report-table")]')->item(0);
        
        if (!$tabela) {
            $this->error[] = "Nie znaleziono tabeli raportu finansowego dla: {$ticker}";
            return false;
        }

        $tablica = [];
        $wiersze = $oXPath->query('.//tr', $tabela);

        foreach ($wiersze as $wNumer => $wiersz) {
            if ($wNumer > 0) {
                $komorki = $oXPath->query('.//td', $wiersz);
                if ($komorki->length > 0) {
                    $klucz = trim($komorki->item(0)->nodeValue);
                    for ($kNumer = 1; $kNumer < $komorki->length; $kNumer++) {
                        $tablica[$kNumer - 1][$klucz] = trim($komorki->item($kNumer)->nodeValue);
                    }
                }
            }
        }

        // Ustawianie daty przyszłej dla 12TTM (powtarzający się rok publikacji)
        $poprzedniRok = 0;
        foreach ($tablica as $klucz => $wiersz) {
            if (!isset($wiersz['Data publikacji'])) continue;
            $aktualnyRok = substr($wiersz['Data publikacji'], 0, 4);
            if ($aktualnyRok == $poprzedniRok) {
                $nastepnyRok = (int)$aktualnyRok + 1;
                $tablica[$klucz]['Data publikacji'] = "{$nastepnyRok}-01-01";
            }
            $poprzedniRok = $aktualnyRok;
        }

        $wynik = [];
        foreach ($tablica as $wiersz) {
            if (!isset($wiersz['Data publikacji'])) continue;
            $dataPub = $wiersz['Data publikacji'];
            $wynik[$dataPub] = $wiersz;

            foreach ($wiersz as $klucz => $wartosc) {
                if (preg_match('/-?\d[\d\s]*\.?\d*/', $wartosc, $wyniki)) {
                    $wartosc = str_replace(' ', '', $wyniki[0]);
                } else {
                    $wartosc = 0;
                }
                $wynik[$dataPub][$klucz] = $wartosc;
            }
            unset($wynik[$dataPub]['Data publikacji']);

            // Wyliczenia pól uzupełniających, jeśli brakuje ich w tabeli
            if (!isset($wynik[$dataPub]['Przychody ze sprzedaży'])) {
                $wynik[$dataPub]['Przychody ze sprzedaży'] = 
                    ($wynik[$dataPub]['Przychody odsetkowe'] ?? 0) + 
                    ($wynik[$dataPub]['Przychody prowizyjne'] ?? 0) + 
                    ($wynik[$dataPub]['Wynik handlowy i rewaluacja'] ?? 0) + 
                    ($wynik[$dataPub]['Pozostałe przychody operacyjne'] ?? 0) + 
                    ($wynik[$dataPub]['Przychody z tytułu dywidend'] ?? 0);
            }
            if (!isset($wynik[$dataPub]['Techniczny koszt wytworzenia produkcji sprzedanej'])) {
                $wynik[$dataPub]['Techniczny koszt wytworzenia produkcji sprzedanej'] = 
                    ($wynik[$dataPub]['Koszty odsetkowe'] ?? 0) + 
                    ($wynik[$dataPub]['Koszty prowizyjne'] ?? 0);
            }
            if (!isset($wynik[$dataPub]['Zysk operacyjny (EBIT)'])) {
                $wynik[$dataPub]['Zysk operacyjny (EBIT)'] = $wynik[$dataPub]['Wynik operacyjny'] ?? 0;
            }
        }
        
        unset($wynik['']);
        return $wynik;
    }

    public function _ticker($ticker): string
    {
        $podmianki = ['AMB.WA' => 'AMBRA'];
        
        if (array_key_exists($ticker, $podmianki)) {
            return $podmianki[$ticker];
        }
            
        $koncowka = explode('.', $ticker);
        return count($koncowka) == 1 ? $ticker : $koncowka[0];
    }

    public function sprawozdania(string $ticker)
    {
        return $this->_get($this->_ticker($ticker), 'raporty-finansowe-rachunek-zyskow-i-strat');
    }
}