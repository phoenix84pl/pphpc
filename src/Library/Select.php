<?php

namespace Phoenix\Core\Library;

use Phoenix\Core\Database;

/**
 * Klasa generująca elementy HTML <select>
 * 
 * 20260311 Start klasy
 * 20260319 +atrybuty()
 * 20260319 Obsługa atrybutów wielowymiarowych/dynamicznych (każda opcja ma inne)
 * 20260813 Refaktoryzacja PSR-12, dodanie typowania i namespace Phoenix\Core\Library
 */
class Select
{
    public mixed $multi = null;
    public array $opcje = [];
    public array $flagi = [];        // Atrybuty tagu <select>
    public array $atrybuty = [];     // Atrybuty tagów <option>
    public bool $pusta = false;
    public mixed $selected = null;

    public function __construct(array $opcje = [], array $flagi = [], mixed $selected = null, bool $pusta = false)
    {
        $this->opcje($opcje);
        $this->flagi($flagi);
        $this->selected = $selected;
        $this->pusta = $pusta;
    }

    /**
     * Ustawia lub scala flagi (atrybuty) dla głównego tagu <select>
     */
    public function flagi(array $flagi): self
    {
        $this->flagi = array_replace($this->flagi, $flagi);
        return $this;
    }

    /**
     * Ustawia lub scala atrybuty dla opcji <option>
     */
    public function atrybuty(array $atrybuty): self
    {
        // Jeśli pierwszy element jest tablicą, traktujemy jako atrybuty wielowymiarowe (dedykowane dla konkretnych kluczy)
        if (is_array(reset($atrybuty))) {
            $this->atrybuty = $atrybuty;
        } else {
            $this->atrybuty = array_replace($this->atrybuty, $atrybuty);
        }

        return $this;
    }

    /**
     * Dodaje/podmienia opcje do selecta
     */
    public function opcje(?array $opcje = [], mixed $selected = null, ?array $atrybuty = null): self
    {
        $opcje = $opcje ?? [];

        $this->opcje = array_replace($this->opcje, $opcje);
        if ($selected !== null) {
            $this->selected = $selected;
        }
        if ($atrybuty !== null) {
            $this->atrybuty($atrybuty);
        }

        return $this;
    }

    /**
     * Pobiera opcje bezpośrednio z obiektu bazy danych Database
     */
    public function DB2Opcje(Database $oDB, string $tabela, string $klucz, string $wartosc, mixed $warunki, mixed $selected = null, ?array $atrybuty = null): self
    {
        $opcje = $oDB->klucz($tabela, $klucz, $wartosc, $warunki);
        $this->opcje($opcje, $selected, $atrybuty);

        return $this;
    }

    /**
     * Włącza dodawanie pustej opcji na początku listy
     */
    public function pusta(bool $pusta = true): self
    {
        $this->pusta = $pusta;
        return $this;
    }

    /**
     * Generuje końcowy kod HTML elementu <select>
     */
    public function generuj(): string
    {
        $flagiHtml = '';
        $opcjeHtml = '';
        $jednowymiaroweAtrybutyHtml = '';

        if ($this->pusta) {
            $opcjeHtml .= "<option></option>";
        }

        // Generowanie atrybutów dla tagu <select>
        foreach ($this->flagi as $klucz => $wartosc) {
            if (is_int($klucz) || $klucz === null) {
                $flagiHtml .= " " . htmlspecialchars((string)$wartosc, ENT_QUOTES);
            } else {
                $flagiHtml .= " " . htmlspecialchars((string)$klucz, ENT_QUOTES) . "=\"" . htmlspecialchars((string)$wartosc, ENT_QUOTES) . "\"";
            }
        }

        // Sprawdzamy czy atrybuty options są jednowymiarowe (stałe dla wszystkich)
        $jestWielowymiarowe = !empty($this->atrybuty) && is_array(reset($this->atrybuty));

        if (!empty($this->atrybuty) && !$jestWielowymiarowe) {
            foreach ($this->atrybuty as $klucz => $wartosc) {
                if (is_int($klucz) || $klucz === null) {
                    $jednowymiaroweAtrybutyHtml .= " " . htmlspecialchars((string)$wartosc, ENT_QUOTES);
                } else {
                    $jednowymiaroweAtrybutyHtml .= " " . htmlspecialchars((string)$klucz, ENT_QUOTES) . "=\"" . htmlspecialchars((string)$wartosc, ENT_QUOTES) . "\"";
                }
            }
        }

        // Generowanie poszczególnych tagów <option>
        foreach ($this->opcje as $klucz => $wartosc) {
            $atrybutyOpcjiHtml = $jednowymiaroweAtrybutyHtml;

            // Jeśli mamy atrybuty wielowymiarowe dla konkretnych opcji
            if ($jestWielowymiarowe && isset($this->atrybuty[$klucz]) && is_array($this->atrybuty[$klucz])) {
                $atrybutyOpcjiHtml = '';
                foreach ($this->atrybuty[$klucz] as $aKlucz => $aWartosc) {
                    if (is_int($aKlucz) || $aKlucz === null) {
                        $atrybutyOpcjiHtml .= " " . htmlspecialchars((string)$aWartosc, ENT_QUOTES);
                    } else {
                        $atrybutyOpcjiHtml .= " " . htmlspecialchars((string)$aKlucz, ENT_QUOTES) . "=\"" . htmlspecialchars((string)$aWartosc, ENT_QUOTES) . "\"";
                    }
                }
            }

            $isSelected = isset($this->selected) && ((string)$klucz === (string)$this->selected);
            $selectedAttr = $isSelected ? ' selected' : '';

            $valEscaped = htmlspecialchars((string)$klucz, ENT_QUOTES);
            $textEscaped = htmlspecialchars((string)$wartosc, ENT_QUOTES);

            $opcjeHtml .= "<option{$atrybutyOpcjiHtml} value=\"{$valEscaped}\"{$selectedAttr}>{$textEscaped}</option>";
        }

        return "<select{$flagiHtml}>{$opcjeHtml}</select>";
    }
}