<?php

namespace Phoenix\Core\Library;

/*
 * 20250608 Start klasy
 */

class Wykres
{
	private $_id=NULL;			//id wykresu
	public $dane=array();		//dane wykresu

	public function __construct()
	{
		$this->_id='wykres_'.uniqid();
		
		$this->dane=array
		(
			'data'=>NULL,
			'options'=>array
			(
				'responsive'=>TRUE,
				'maintainAspectRatio'=>TRUE,
				'tooltip'=>array
				(
					'mode'=>'index',
					'intersect'=>FALSE,
				),
				'plugins'=>array(),
			),
		);	

	}
	
	private function _tablicaGeneruj($dane)
	{
			//funkcja generuje tablicę z danych niezależnie od tego, co weszło
			
		if (is_array($dane)) {
			return $dane;
		} elseif (is_string($dane)) {
			// Zamień JS na poprawny JSON (np. 'display: false' na '"display": false')
			$dane = preg_replace('/(\w+):/', '"$1":', $dane);
			$dane = preg_replace("/'/", '"', $dane); // Zamień ' na "
			$decoded = json_decode($dane, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return $decoded;
			}
			return [$dane]; // Fallback
		} elseif (is_object($dane)) {
			return json_decode(json_encode($dane), true);
		}
		return [$dane];
	}

	public function etykietyDodaj($dane)
	{
			//funkcja dodaje etykiety do wykresu
//$oWykres->etykietyDodaj(array(1, 2, 3, 4, 5));
//$oWykres->etykietyDodaj('[1,2,3,4,5]');

		$this->dane['data']['labels']=$this->_tablicaGeneruj($dane);
	}

	public function liniaDodaj($dane)
	{
			//funkcja dodaje linię do wykresu
//$oWykres->liniaDodaj("label: 'Test', yAxisID: 'kwota', data: [2,5,8,3,1], borderColor: 'red'");
//$oWykres->liniaDodaj(['label' => 'Test2', 'yAxisID' => 'kwota', 'data' => [5,8,3,2,1], 'borderColor' => 'blue']);

		if (is_string($dane)) {
			$konfiguracja = $this->_tablicaGeneruj('{' . $dane . '}');
			if (is_array($konfiguracja)) {
				$this->dane['data']['datasets'][] = $konfiguracja;
			}
		} elseif (is_array($dane)) {
			$this->dane['data']['datasets'][] = $dane;
		}
	}
	
	public function skalaDodaj($dane)
	{
			//funkcja dodaje skale do wykredu
//$oWykres->skalaDodaj(array('x'=>array('display'=> FALSE)));
//$oWykres->skalaDodaj("kwota: {type: 'linear', display: true, position: 'left'}");
//$oWykres->skalaDodaj("oscylator: {type: 'linear', display: false, position: 'right', min: -100,	max: 100, grid: {drawOnChartArea: false}}");

		if (is_string($dane) && preg_match('/^(\w+):\s*{(.+)}$/', $dane, $matches)) {
			// String JS, np. "x: {display: false}"
			$klucz = $matches[1];
			$konfiguracja = $this->_tablicaGeneruj('{' . $matches[2] . '}');
			$this->dane['options']['scales'][$klucz] = $konfiguracja;
		} elseif (is_array($dane) && ($klucz = key($dane))) {
			// Tablica, np. ['x' => ['display' => false]]
			$this->dane['options']['scales'][$klucz] = $dane[$klucz];
		}
	}
	
public function generuj()
    {
        $id = htmlspecialchars($this->_id, ENT_QUOTES, 'UTF-8');
        $daneJson = json_encode($this->dane, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return <<<HTML
<div id="{$id}" class="max"></div>
<script>
    $(document).ready(function() {
        var el = document.getElementById('{$id}');
        if (el && typeof CMSWykresGeneruj === 'function') {
            CMSWykresGeneruj(el, {$daneJson});
        } else if (!el) {
            console.error('PPHPC Chart Error: Nie znaleziono kontenera dla wykresu o ID: {$id}');
        }
    });
</script>
HTML;
    }
}
