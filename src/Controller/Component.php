<?php
namespace Phoenix\Core\Controller;

class Component
{
    // Ta zmienna będzie automatycznie dostępna we wszystkich podklasach
    protected mixed $db = null;

    public function __construct()
    {
        // Wyciągamy bazę z pamięci globalnej RAZ przy inicjalizacji klasy
        global $db;
        $this->db = $db;
    }
}