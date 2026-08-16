<?php

namespace Phoenix\Core\Library;

class Alphavantage
{
    private ?string $klucz;

    public function __construct(?string $klucz = null)
    {
        $this->klucz = $klucz;
    }

    private function _linkGeneruj($ticker, $funkcja): string
    {
        $api = $this->klucz ?? 'demo';
        return "https://www.alphavantage.co/query?function={$funkcja}&symbol={$ticker}&apikey={$api}";
    }

    private function _get($ticker, $funkcja)
    {
        sleep(1);
        $json = @file_get_contents($this->_linkGeneruj($ticker, $funkcja));
        if ($json === false) return false;

        $wynik = json_decode($json, true);

        if (isset($wynik['Information'])) {
            error_log("[ERROR] {$wynik['Information']}");
        }

        return $wynik;
    }

    public function sprawozdania($ticker)
    {
        $www = $this->_get($ticker, 'INCOME_STATEMENT');
        if (isset($www['annualReports'])) {
            $sprawozdania = $www['annualReports'];
            $wynik = [];
            if (!empty($sprawozdania)) {
                foreach ($sprawozdania as $rekord) {
                    foreach ($rekord as $pole => $wartosc) {
                        if ($wartosc === 'None' || $wartosc === null || $wartosc === '') {
                            $rekord[$pole] = 0;
                        }
                    }
                    $wynik[$rekord['fiscalDateEnding']] = $rekord;
                }
                return $wynik;
            }
        }
        return false;
    }

    public function bilanse($ticker)
    {
        $www = $this->_get($ticker, 'BALANCE_SHEET');
        if (isset($www['annualReports'])) {
            $bilanse = $www['annualReports'];
            $wynik = [];
            if (!empty($bilanse)) {
                foreach ($bilanse as $rekord) {
                    foreach ($rekord as $pole => $wartosc) {
                        if ($wartosc === 'None' || $wartosc === null || $wartosc === '') {
                            $rekord[$pole] = 0;
                        }
                    }
                    $wynik[$rekord['fiscalDateEnding']] = $rekord;
                }
                return $wynik;
            }
        }
        return false;
    }

    public function przeplywy($ticker)
    {
        $www = $this->_get($ticker, 'CASH_FLOW');
        if (isset($www['annualReports'])) {
            $przeplywy = $www['annualReports'];
            $wynik = [];
            if (!empty($przeplywy)) {
                foreach ($przeplywy as $rekord) {
                    foreach ($rekord as $pole => $wartosc) {
                        if ($wartosc === 'None' || $wartosc === null || $wartosc === '') {
                            $rekord[$pole] = 0;
                        }
                    }
                    $wynik[$rekord['fiscalDateEnding']] = $rekord;
                }
                return $wynik;
            }
        }
        return false;
    }

    public function raport($ticker)
    {
        $sprawozdania = $this->sprawozdania($ticker);
        $bilanse = $this->bilanse($ticker);
        $przeplywy = $this->przeplywy($ticker);

        if ($sprawozdania && $bilanse && $przeplywy) {
            $raport = [];
            $daty = array_unique(array_merge(array_keys($sprawozdania), array_keys($bilanse), array_keys($przeplywy)));
            foreach ($daty as $data) {
                $raport[$data] = array_merge($sprawozdania[$data] ?? [], $bilanse[$data] ?? [], $przeplywy[$data] ?? []);
            }
            return $raport;
        }
        return false;
    }
}