<?php

namespace Phoenix\Core\Library;

/* Historia

20200412	+array2int, +int2array
20240906	+selectGeneruj, +arrayValueIsKey
20240907	poprawki w czasRelatywnyGeneruj
20240911	+ifNot
20250416	dostosowanie array null do najnowszych wymogów (?array)
20250607	+stDev()
20260315	+include2str()
20260318	+wygladz()
20260319	+AIGeneruj(), DEPRECATED wszystkie select/option generuj
20260321	+round()

*/

class Uzytki
{

	public static function czasRelatywnyGeneruj($czas, $czas2 = null, $str_przerwa = '', $literki = null)
	{
		//~ funkcja zwraca czas w postaci stringa z opisem (minut, sekund, itp)
		//~ $czas = time() //jako liczba, czas będzie mierzony do teraz, chyba że ustalony będzie $czas2 (wtedy różnica będzie od tych dwóch czasów)
		//~ $str_przerwa to znak np ' ' między liczbą a opisem(minut, itp)
		//~ jeśli $literki to tablica o indeksach takich jak tablica $standardowe_literki to będzie brana z $literki (możemy zamienić np 'g'=>'h' (godzin na hours))
		$standardowe_literki = array('sek'=>'s', 'min'=>'m', 'hour'=>'h', 'day'=>'d', 'mon'=>'M', 'year'=>'y');
		if(!is_array($literki)) $literki = $standardowe_literki;
		if(!isset($czas2)) $czas2 = time();
		if(is_string($czas) and !ctype_digit($czas)) $czas = strtotime($czas);
		if(is_string($czas2) and !ctype_digit($czas)) $czas2 = strtotime($czas2);
		$sek = intval(abs($czas2 - $czas)); //różnica czasu
		
		// Nowy próg konwersji - 100 zamiast poprzedniego progu (60, 24, 30, 12)
		$prog_konwersji = 100;
		
		$dzielniki = array('sek'=>60, 'min'=>60, 'hour'=>24, 'day'=>30, 'mon'=>12, 'year'=>1000);
		
		// Wartość w aktualnej jednostce
		$aktualna_wartosc = $sek;
		
		// Aktualny mnożnik (ile sekund w danej jednostce)
		$aktualny_mnoznik = 1;
		
		foreach($dzielniki as $index => $d)
		{
			// Jeśli wartość mniejsza niż próg konwersji, zwracamy wynik
			if($aktualna_wartosc < $prog_konwersji)
			{
				$opis = is_array($literki) && isset($literki[$index]) ? $literki[$index] : $standardowe_literki[$index];
				return $aktualna_wartosc.$str_przerwa.$opis;
			}
			
			// W przeciwnym razie przygotowujemy się do konwersji na wyższą jednostkę
			$aktualny_mnoznik *= $d;
			$aktualna_wartosc = (int)($sek / $aktualny_mnoznik);
		}
		
		// Jeśli doszliśmy do końca i nic nie zwróciliśmy, zwracamy ostatnią dostępną jednostkę
		$opis = is_array($literki) && isset($literki['year']) ? $literki['year'] : $standardowe_literki['year'];
		return $aktualna_wartosc.$str_przerwa.$opis;
	}

	public	static function var_dump($zmienna, $opis='')
	{
		echo "$opis <br /><pre>".var_export($zmienna, TRUE)."</pre>";
	}

	public	static function array_wzor($tablica_dane, $szablon)
	{
		//funkcja przetwarza i zwraca tablice wg podanego wzoru... do podmiany [$klucz]

		$wynik = array();
		if(!empty($tablica_dane) and is_array($tablica_dane)) foreach($tablica_dane as $rekord)
			{
			$tablica_z = array();
			$tablica_do = array();

			if(!empty($rekord) and is_array($rekord))foreach($rekord as $klucz=>$wartosc)
				{
				$tablica_z[] = "[$klucz]";
				$tablica_do[] = $wartosc;
				}
				if(!empty($tablica_z))
					$wynik[] = str_replace($tablica_z,$tablica_do,$szablon);
			}

		return $wynik;

	}

	public	static function tablica_odwroc($dane)
	{
		if(empty($dane) or !is_array($dane))
			return NULL;

		$wynik = array();

		foreach($dane as $x=>$linia)
			if(is_array($linia))
				foreach($linia as $y=>$wartosc)
					$wynik[$y][$x]=$wartosc;

		return $wynik;
	}

	public static function tablica_sortuj(array $tablica, $klucz_sortowania)
	{
		//funkcja sortuje tablicę wielowymiarową po wartościach w podanym kluczu podtablicy
		foreach($tablica as $klucz=>$wiersz)
		{
			$wartosci[]=$wiersz[$klucz_sortowania];		//w liscie $wartosci trzymamy wartosci wg ktorych sortujemy
		}

		array_multisort($wartosci, $tablica);

		return $tablica;
	}

	public static function weighted_rand(array $weightedValues)
	{
			//funkcja zwraca wagowo losowane (chyba musza byc integery w tablicy)
		$rand = mt_rand(1, (int) array_sum($weightedValues));
//		var_dump($rand, array_sum($weightedValues), $weightedValues);

		foreach ($weightedValues as $key => $value)
		{
			$rand-=$value;
			if($rand<= 0)
			{
				return $key;
			}
		}
	}

	public static function float_rand($Min, $Max, $round=0)
	{
		//funkcja liczy rand miedzy floatami

	    //validate input
	    if ($Min>$Max) { $min=$Max; $max=$Min; }
	        else { $min=$Min; $max=$Max; }

	    $randomfloat = $min + mt_rand() / mt_getrandmax() * ($max - $min);
	    if($round>0)
	        $randomfloat = round($randomfloat,$round);

	    return $randomfloat;
	}

	public static function numer_formatuj($numer, $znaczace, $round=NULL)
	{
		//NIE UZYWAC NIE DZIALA!
		//funkcja formatuje numer tak, by mial odpowiednia dlugosc wzgledem swojej wartosci
		//liczby z duza iloscia cyfr przed i po przecinku zostana zredukowane w dlugosci
		//liczby z duza iloscia cyfr tylko przed przecinkiem nie zostana zmienione
		//liczby z duza iloscia cyfr po przecinku zostana zredukowane do cyfr znaczacych

		$numer=number_format($numer, 14); //mozna sobie zmieniac (ta liczba to max cyfr po przecinku jakie beda brane pod uwage w tej funkcji)

		$t=explode('.', $numer);
		$przed=$t[0];	//przed przecinkiem
		$po=$t[1];		//po przecinku

		if((!$po) || (strlen($przed)>=$znaczace)) $wynik=$przed;
		else
		{
			$znaczace-=strlen($przed);	//ustalanie ilosci cyfr znaczacych po przecinku z uwzglednieniem tych z przed przecinka
			if($przed==0) $znaczace++;

			$dlugosc=strlen($po);

			for($i0=0; $i0<$dlugosc; $i0++)
			{
				if($po[$i0]!=0)
				{
					$pozycja=$i0;
					break;
				}
			}

			$wynik="$przed.".substr($po, 0, $pozycja+$znaczace);
		}

/*		if($round)
		{
			if($round>$pozycja+$znaczace) $wynik=number_format($wynik, $pozycja+$znaczace, '.', '');
			else $wynik=number_format($wynik, $round, '.', '');
		}
		else $wynik=number_format($wynik, $pozycja+$znaczace, '.', '');
*/
		$wynik=number_format($wynik, $pozycja+$znaczace, '.', '');

//		var_dump($wynik, $po, $pozycja); exit();
		return $wynik;
	}

	public static function shuffle($array)
	{
		//funkcja shuffluje tablice, ale pamieta jej klucze

		$klucze=array_keys($array);
		shuffle($klucze);
		foreach($klucze as $klucz)
		{
			$wynik[$klucz]=$array[$klucz];
		}

		return $wynik;
	}

	public static function str_shuffle($string)
	{
		//funkcja shuffluje slowo zachowując jego utf-8 (bo normalny shuffle rozdziela znaki utf-8 na kilka jednobajtowych)

		$dlugosc=mb_strlen($string, 'utf-8');
		for($i0=0; $i0<$dlugosc; $i0++)
		{
			//dla kazdego znaku
			$wynik[$i0]=mb_substr($string, $i0, 1);
		}
		shuffle($wynik);
		$wynik=implode('', $wynik);

		return $wynik;
	}

	public static function pomiedzy($wartosc, $min, $max)
	{
		//funkcja zwraca true/false w zaleznosci od tego czy $wartosc jest pomiedzy min i max

		if((($wartosc==$max) && ($wartosc==$min)) || (($max>$wartosc) && ($wartosc>$min)) || (($max<$wartosc) &&($wartosc<$min))) return true;
		else return false;
	}
	
	public static function arrayValue2Key(array $tablica)
	{
		//funkcja przetwarza tablicę tak, że jej wartości stają się kluczami (przydatne, gdy np. dostajesz tablicę bez kluczy, a chcesz zrobić selecta, gdzie treść jest wartością)
				
		if(isset($tablica)) foreach($tablica as $wartosc) $wynik[$wartosc]=$wartosc;
		
		return $wynik;
	}

	public static function selectGeneruj($id=NULL, ?array $dane=NULL, $selected=NULL, $onChange=NULL)
	{
		@trigger_error('Method selectGeneruj() is deprecated. Use Select Class instead', E_USER_DEPRECATED);
		//funkcja generuje select
						
		$hId=$id==NULL?'':"id=\"{$id}\"";
		$hOnChange=$onChange==NULL?'':"onChange=\"{$onChange}\"";

		$wynik="<select {$hId} {$hOnChange}>";
		if(isset($dane)) foreach($dane as $klucz=>$wartosc)
		{
			if(is_int($klucz)) $klucz=(string)$klucz;		//potrzebne, bo option zawsze zwraca klucz jako string, a array() zawsze zwraca jako int klucz rozpoznany jako liczbę, nawet jeśli jest to liczba jako string
			if($klucz===$selected) $wynik.="<option value=\"{$klucz}\" selected>{$wartosc}</option>";
			else $wynik.="<option value=\"{$klucz}\">{$wartosc}</option>";
		}
		$wynik.="</select>";
		
		return $wynik;
	}

	public static function db2option($oDB, $tabela, $kolumna_klucz, $kolumna_wartosc, $warunek, $selected=NULL, $atrybuty=NULL)
	{
		@trigger_error('Method db2option() is deprecated. Use Select Class instead', E_USER_DEPRECATED);
		//funkcja zwraca <option> wzgledem danych z bazy, w selected podajemy klucz opcji, ktora ma byc domyslna

		$db = $oDB->klucz($tabela, $kolumna_klucz, $kolumna_wartosc, $warunek);
		$wynik='';
		if(isset($db)) foreach($db as $klucz=>$wartosc)	if($klucz!='')
		{
			if($klucz==$selected) $wynik.="<option $atrybuty value=\"$klucz\" selected=\"selected\">$wartosc</option>";
			else $wynik.="<option $atrybuty value=\"$klucz\">$wartosc</option>";
		}

		return $wynik;
	}

	public static function db2select($oDB, $tabela, $kolumna_klucz, $kolumna_wartosc, $warunek, $selected=NULL, $atrybuty=NULL, $atrybuty_option=NULL, ?array $options=NULL)
	{
        @trigger_error('Method db2select() is deprecated. Use Select Class instead', E_USER_DEPRECATED);
		//funkcja zwraca <option> wzgledem danych z bazy
		
		//$option to dodatkowe opcje spoza bazy jako $klucz=>$wartosc

		$wynik="<select $atrybuty>";
		if(!empty($options)) foreach($options as $klucz=>$wartosc)
		{
			if($klucz==$selected) $wynik.="<option $atrybuty_option value=\"$klucz\" selected=\"selected\">$wartosc</option>";
			else $wynik.="<option $atrybuty_option value=\"$klucz\">$wartosc</option>";
		}
		$wynik.=self::db2option($oDB, $tabela, $kolumna_klucz, $kolumna_wartosc, $warunek, $selected, $atrybuty_option);
		$wynik.='</select>';

		return $wynik;
	}

	public static function dediakrytyzuj($text)
	{
		$trans = array(
			'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Ç'=>'C','È'=>'E',
			'É'=>'E','Ê'=>'E','Ë'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ñ'=>'N',
			'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O','Ù'=>'U','Ú'=>'U',
			'Û'=>'U','Ü'=>'U','Ý'=>'Y','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
			'å'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i',
			'î'=>'i','ï'=>'i','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
			'ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','Ā'=>'A',
			'ā'=>'a','Ă'=>'A','ă'=>'a','Ą'=>'A','ą'=>'a','Ć'=>'C','ć'=>'c','Ĉ'=>'C',
			'ĉ'=>'c','Ċ'=>'C','ċ'=>'c','Č'=>'C','č'=>'c','Ď'=>'D','ď'=>'d','Đ'=>'D',
			'đ'=>'d','Ē'=>'E','ē'=>'e','Ĕ'=>'E','ĕ'=>'e','Ė'=>'E','ė'=>'e','Ę'=>'E',
			'ę'=>'e','Ě'=>'E','ě'=>'e','Ĝ'=>'G','ĝ'=>'g','Ğ'=>'G','ğ'=>'g','Ġ'=>'G',
			'ġ'=>'g','Ģ'=>'G','ģ'=>'g','Ĥ'=>'H','ĥ'=>'h','Ħ'=>'H','ħ'=>'h','Ĩ'=>'I',
			'ĩ'=>'i','Ī'=>'I','ī'=>'i','Ĭ'=>'I','ĭ'=>'i','Į'=>'I','į'=>'i','İ'=>'I',
			'ı'=>'i','Ĵ'=>'J','ĵ'=>'j','Ķ'=>'K','ķ'=>'k','Ĺ'=>'L','ĺ'=>'l','Ļ'=>'L',
			'ļ'=>'l','Ľ'=>'L','ľ'=>'l','Ŀ'=>'L','ŀ'=>'l','Ł'=>'L','ł'=>'l','Ń'=>'N',
			'ń'=>'n','Ņ'=>'N','ņ'=>'n','Ň'=>'N','ň'=>'n','ŉ'=>'n','Ō'=>'O','ō'=>'o',
			'Ŏ'=>'O','ŏ'=>'o','Ő'=>'O','ő'=>'o','Ŕ'=>'R','ŕ'=>'r','Ŗ'=>'R','ŗ'=>'r',
			'Ř'=>'R','ř'=>'r','Ś'=>'S','ś'=>'s','Ŝ'=>'S','ŝ'=>'s','Ş'=>'S','ş'=>'s',
			'Š'=>'S','š'=>'s','Ţ'=>'T','ţ'=>'t','Ť'=>'T','ť'=>'t','Ŧ'=>'T','ŧ'=>'t',
			'Ũ'=>'U','ũ'=>'u','Ū'=>'U','ū'=>'u','Ŭ'=>'U','ŭ'=>'u','Ů'=>'U','ů'=>'u',
			'Ű'=>'U','ű'=>'u','Ų'=>'U','ų'=>'u','Ŵ'=>'W','ŵ'=>'w','Ŷ'=>'Y','ŷ'=>'y',
			'Ÿ'=>'Y','Ź'=>'Z','ź'=>'z','Ż'=>'Z','ż'=>'z','Ž'=>'Z','ž'=>'z','ƀ'=>'b',
			'Ɓ'=>'B','Ƃ'=>'B','ƃ'=>'b','Ƈ'=>'C','ƈ'=>'c','Ɗ'=>'D','Ƌ'=>'D','ƌ'=>'d',
			'Ƒ'=>'F','ƒ'=>'f','Ɠ'=>'G','Ɨ'=>'I','Ƙ'=>'K','ƙ'=>'k','ƚ'=>'l','Ɲ'=>'N',
			'ƞ'=>'n','Ɵ'=>'O','Ơ'=>'O','ơ'=>'o','Ƥ'=>'P','ƥ'=>'p','ƫ'=>'t','Ƭ'=>'T',
			'ƭ'=>'t','Ʈ'=>'T','Ư'=>'U','ư'=>'u','Ʋ'=>'V','Ƴ'=>'Y','ƴ'=>'y','Ƶ'=>'Z',
			'ƶ'=>'z','ǅ'=>'D','ǈ'=>'L','ǋ'=>'N','Ǎ'=>'A','ǎ'=>'a','Ǐ'=>'I','ǐ'=>'i',
			'Ǒ'=>'O','ǒ'=>'o','Ǔ'=>'U','ǔ'=>'u','Ǖ'=>'U','ǖ'=>'u','Ǘ'=>'U','ǘ'=>'u',
			'Ǚ'=>'U','ǚ'=>'u','Ǜ'=>'U','ǜ'=>'u','Ǟ'=>'A','ǟ'=>'a','Ǡ'=>'A','ǡ'=>'a',
			'Ǥ'=>'G','ǥ'=>'g','Ǧ'=>'G','ǧ'=>'g','Ǩ'=>'K','ǩ'=>'k','Ǫ'=>'O','ǫ'=>'o',
			'Ǭ'=>'O','ǭ'=>'o','ǰ'=>'j','ǲ'=>'D','Ǵ'=>'G','ǵ'=>'g','Ǹ'=>'N','ǹ'=>'n',
			'Ǻ'=>'A','ǻ'=>'a','Ǿ'=>'O','ǿ'=>'o','Ȁ'=>'A','ȁ'=>'a','Ȃ'=>'A','ȃ'=>'a',
			'Ȅ'=>'E','ȅ'=>'e','Ȇ'=>'E','ȇ'=>'e','Ȉ'=>'I','ȉ'=>'i','Ȋ'=>'I','ȋ'=>'i',
			'Ȍ'=>'O','ȍ'=>'o','Ȏ'=>'O','ȏ'=>'o','Ȑ'=>'R','ȑ'=>'r','Ȓ'=>'R','ȓ'=>'r',
			'Ȕ'=>'U','ȕ'=>'u','Ȗ'=>'U','ȗ'=>'u','Ș'=>'S','ș'=>'s','Ț'=>'T','ț'=>'t',
			'Ȟ'=>'H','ȟ'=>'h','Ƞ'=>'N','ȡ'=>'d','Ȥ'=>'Z','ȥ'=>'z','Ȧ'=>'A','ȧ'=>'a',
			'Ȩ'=>'E','ȩ'=>'e','Ȫ'=>'O','ȫ'=>'o','Ȭ'=>'O','ȭ'=>'o','Ȯ'=>'O','ȯ'=>'o',
			'Ȱ'=>'O','ȱ'=>'o','Ȳ'=>'Y','ȳ'=>'y','ȴ'=>'l','ȵ'=>'n','ȶ'=>'t','ȷ'=>'j',
			'Ⱥ'=>'A','Ȼ'=>'C','ȼ'=>'c','Ƚ'=>'L','Ⱦ'=>'T','ȿ'=>'s','ɀ'=>'z','Ƀ'=>'B',
			'Ʉ'=>'U','Ɇ'=>'E','ɇ'=>'e','Ɉ'=>'J','ɉ'=>'j','ɋ'=>'q','Ɍ'=>'R','ɍ'=>'r',
			'Ɏ'=>'Y','ɏ'=>'y','ɓ'=>'b','ɕ'=>'c','ɖ'=>'d','ɗ'=>'d','ɟ'=>'j','ɠ'=>'g',
			'ɦ'=>'h','ɨ'=>'i','ɫ'=>'l','ɬ'=>'l','ɭ'=>'l','ɱ'=>'m','ɲ'=>'n','ɳ'=>'n',
			'ɵ'=>'o','ɼ'=>'r','ɽ'=>'r','ɾ'=>'r','ʂ'=>'s','ʄ'=>'j','ʈ'=>'t','ʉ'=>'u',
			'ʋ'=>'v','ʐ'=>'z','ʑ'=>'z','ʝ'=>'j','ʠ'=>'q','ͣ'=>'a','ͤ'=>'e','ͥ'=>'i',
			'ͦ'=>'o','ͧ'=>'u','ͨ'=>'c','ͩ'=>'d','ͪ'=>'h','ͫ'=>'m','ͬ'=>'r','ͭ'=>'t',
			'ͮ'=>'v','ͯ'=>'x','ᵢ'=>'i','ᵣ'=>'r','ᵤ'=>'u','ᵥ'=>'v','ᵬ'=>'b','ᵭ'=>'d',
			'ᵮ'=>'f','ᵯ'=>'m','ᵰ'=>'n','ᵱ'=>'p','ᵲ'=>'r','ᵳ'=>'r','ᵴ'=>'s','ᵵ'=>'t',
			'ᵶ'=>'z','ᵻ'=>'i','ᵽ'=>'p','ᵾ'=>'u','ᶀ'=>'b','ᶁ'=>'d','ᶂ'=>'f','ᶃ'=>'g',
			'ᶄ'=>'k','ᶅ'=>'l','ᶆ'=>'m','ᶇ'=>'n','ᶈ'=>'p','ᶉ'=>'r','ᶊ'=>'s','ᶌ'=>'v',
			'ᶍ'=>'x','ᶎ'=>'z','ᶏ'=>'a','ᶑ'=>'d','ᶒ'=>'e','ᶖ'=>'i','ᶙ'=>'u','᷊'=>'r',
			'ᷗ'=>'c','ᷚ'=>'g','ᷜ'=>'k','ᷝ'=>'l','ᷠ'=>'n','ᷣ'=>'r','ᷤ'=>'s','ᷦ'=>'z',
			'Ḁ'=>'A','ḁ'=>'a','Ḃ'=>'B','ḃ'=>'b','Ḅ'=>'B','ḅ'=>'b','Ḇ'=>'B','ḇ'=>'b',
			'Ḉ'=>'C','ḉ'=>'c','Ḋ'=>'D','ḋ'=>'d','Ḍ'=>'D','ḍ'=>'d','Ḏ'=>'D','ḏ'=>'d',
			'Ḑ'=>'D','ḑ'=>'d','Ḓ'=>'D','ḓ'=>'d','Ḕ'=>'E','ḕ'=>'e','Ḗ'=>'E','ḗ'=>'e',
			'Ḙ'=>'E','ḙ'=>'e','Ḛ'=>'E','ḛ'=>'e','Ḝ'=>'E','ḝ'=>'e','Ḟ'=>'F','ḟ'=>'f',
			'Ḡ'=>'G','ḡ'=>'g','Ḣ'=>'H','ḣ'=>'h','Ḥ'=>'H','ḥ'=>'h','Ḧ'=>'H','ḧ'=>'h',
			'Ḩ'=>'H','ḩ'=>'h','Ḫ'=>'H','ḫ'=>'h','Ḭ'=>'I','ḭ'=>'i','Ḯ'=>'I','ḯ'=>'i',
			'Ḱ'=>'K','ḱ'=>'k','Ḳ'=>'K','ḳ'=>'k','Ḵ'=>'K','ḵ'=>'k','Ḷ'=>'L','ḷ'=>'l',
			'Ḹ'=>'L','ḹ'=>'l','Ḻ'=>'L','ḻ'=>'l','Ḽ'=>'L','ḽ'=>'l','Ḿ'=>'M','ḿ'=>'m',
			'Ṁ'=>'M','ṁ'=>'m','Ṃ'=>'M','ṃ'=>'m','Ṅ'=>'N','ṅ'=>'n','Ṇ'=>'N','ṇ'=>'n',
			'Ṉ'=>'N','ṉ'=>'n','Ṋ'=>'N','ṋ'=>'n','Ṍ'=>'O','ṍ'=>'o','Ṏ'=>'O','ṏ'=>'o',
			'Ṑ'=>'O','ṑ'=>'o','Ṓ'=>'O','ṓ'=>'o','Ṕ'=>'P','ṕ'=>'p','Ṗ'=>'P','ṗ'=>'p',
			'Ṙ'=>'R','ṙ'=>'r','Ṛ'=>'R','ṛ'=>'r','Ṝ'=>'R','ṝ'=>'r','Ṟ'=>'R','ṟ'=>'r',
			'Ṡ'=>'S','ṡ'=>'s','Ṣ'=>'S','ṣ'=>'s','Ṥ'=>'S','ṥ'=>'s','Ṧ'=>'S','ṧ'=>'s',
			'Ṩ'=>'S','ṩ'=>'s','Ṫ'=>'T','ṫ'=>'t','Ṭ'=>'T','ṭ'=>'t','Ṯ'=>'T','ṯ'=>'t',
			'Ṱ'=>'T','ṱ'=>'t','Ṳ'=>'U','ṳ'=>'u','Ṵ'=>'U','ṵ'=>'u','Ṷ'=>'U','ṷ'=>'u',
			'Ṹ'=>'U','ṹ'=>'u','Ṻ'=>'U','ṻ'=>'u','Ṽ'=>'V','ṽ'=>'v','Ṿ'=>'V','ṿ'=>'v',
			'Ẁ'=>'W','ẁ'=>'w','Ẃ'=>'W','ẃ'=>'w','Ẅ'=>'W','ẅ'=>'w','Ẇ'=>'W','ẇ'=>'w',
			'Ẉ'=>'W','ẉ'=>'w','Ẋ'=>'X','ẋ'=>'x','Ẍ'=>'X','ẍ'=>'x','Ẏ'=>'Y','ẏ'=>'y',
			'Ẑ'=>'Z','ẑ'=>'z','Ẓ'=>'Z','ẓ'=>'z','Ẕ'=>'Z','ẕ'=>'z','ẖ'=>'h','ẗ'=>'t',
			'ẘ'=>'w','ẙ'=>'y','ẚ'=>'a','Ạ'=>'A','ạ'=>'a','Ả'=>'A','ả'=>'a','Ấ'=>'A',
			'ấ'=>'a','Ầ'=>'A','ầ'=>'a','Ẩ'=>'A','ẩ'=>'a','Ẫ'=>'A','ẫ'=>'a','Ậ'=>'A',
			'ậ'=>'a','Ắ'=>'A','ắ'=>'a','Ằ'=>'A','ằ'=>'a','Ẳ'=>'A','ẳ'=>'a','Ẵ'=>'A',
			'ẵ'=>'a','Ặ'=>'A','ặ'=>'a','Ẹ'=>'E','ẹ'=>'e','Ẻ'=>'E','ẻ'=>'e','Ẽ'=>'E',
			'ẽ'=>'e','Ế'=>'E','ế'=>'e','Ề'=>'E','ề'=>'e','Ể'=>'E','ể'=>'e','Ễ'=>'E',
			'ễ'=>'e','Ệ'=>'E','ệ'=>'e','Ỉ'=>'I','ỉ'=>'i','Ị'=>'I','ị'=>'i','Ọ'=>'O',
			'ọ'=>'o','Ỏ'=>'O','ỏ'=>'o','Ố'=>'O','ố'=>'o','Ồ'=>'O','ồ'=>'o','Ổ'=>'O',
			'ổ'=>'o','Ỗ'=>'O','ỗ'=>'o','Ộ'=>'O','ộ'=>'o','Ớ'=>'O','ớ'=>'o','Ờ'=>'O',
			'ờ'=>'o','Ở'=>'O','ở'=>'o','Ỡ'=>'O','ỡ'=>'o','Ợ'=>'O','ợ'=>'o','Ụ'=>'U',
			'ụ'=>'u','Ủ'=>'U','ủ'=>'u','Ứ'=>'U','ứ'=>'u','Ừ'=>'U','ừ'=>'u','Ử'=>'U',
			'ử'=>'u','Ữ'=>'U','ữ'=>'u','Ự'=>'U','ự'=>'u','Ỳ'=>'Y','ỳ'=>'y','Ỵ'=>'Y',
			'ỵ'=>'y','Ỷ'=>'Y','ỷ'=>'y','Ỹ'=>'Y','ỹ'=>'y','Ỿ'=>'Y','ỿ'=>'y','ⁱ'=>'i',
			'ⁿ'=>'n','ₐ'=>'a','ₑ'=>'e','ₒ'=>'o','ₓ'=>'x','⒜'=>'a','⒝'=>'b','⒞'=>'c',
			'⒟'=>'d','⒠'=>'e','⒡'=>'f','⒢'=>'g','⒣'=>'h','⒤'=>'i','⒥'=>'j','⒦'=>'k',
			'⒧'=>'l','⒨'=>'m','⒩'=>'n','⒪'=>'o','⒫'=>'p','⒬'=>'q','⒭'=>'r','⒮'=>'s',
			'⒯'=>'t','⒰'=>'u','⒱'=>'v','⒲'=>'w','⒳'=>'x','⒴'=>'y','⒵'=>'z','Ⓐ'=>'A',
			'Ⓑ'=>'B','Ⓒ'=>'C','Ⓓ'=>'D','Ⓔ'=>'E','Ⓕ'=>'F','Ⓖ'=>'G','Ⓗ'=>'H','Ⓘ'=>'I',
			'Ⓙ'=>'J','Ⓚ'=>'K','Ⓛ'=>'L','Ⓜ'=>'M','Ⓝ'=>'N','Ⓞ'=>'O','Ⓟ'=>'P','Ⓠ'=>'Q',
			'Ⓡ'=>'R','Ⓢ'=>'S','Ⓣ'=>'T','Ⓤ'=>'U','Ⓥ'=>'V','Ⓦ'=>'W','Ⓧ'=>'X','Ⓨ'=>'Y',
			'Ⓩ'=>'Z','ⓐ'=>'a','ⓑ'=>'b','ⓒ'=>'c','ⓓ'=>'d','ⓔ'=>'e','ⓕ'=>'f','ⓖ'=>'g',
			'ⓗ'=>'h','ⓘ'=>'i','ⓙ'=>'j','ⓚ'=>'k','ⓛ'=>'l','ⓜ'=>'m','ⓝ'=>'n','ⓞ'=>'o',
			'ⓟ'=>'p','ⓠ'=>'q','ⓡ'=>'r','ⓢ'=>'s','ⓣ'=>'t','ⓤ'=>'u','ⓥ'=>'v','ⓦ'=>'w',
			'ⓧ'=>'x','ⓨ'=>'y','ⓩ'=>'z','Ⱡ'=>'L','ⱡ'=>'l','Ɫ'=>'L','Ᵽ'=>'P','Ɽ'=>'R',
			'ⱥ'=>'a','ⱦ'=>'t','Ⱨ'=>'H','ⱨ'=>'h','Ⱪ'=>'K','ⱪ'=>'k','Ⱬ'=>'Z','ⱬ'=>'z',
			'Ɱ'=>'M','ⱱ'=>'v','Ⱳ'=>'W','ⱳ'=>'w','ⱴ'=>'v','ⱸ'=>'e','ⱺ'=>'o','ⱼ'=>'j',
			'Ꝁ'=>'K','ꝁ'=>'k','Ꝃ'=>'K','ꝃ'=>'k','Ꝅ'=>'K','ꝅ'=>'k','Ꝉ'=>'L','ꝉ'=>'l',
			'Ꝋ'=>'O','ꝋ'=>'o','Ꝍ'=>'O','ꝍ'=>'o','Ꝑ'=>'P','ꝑ'=>'p','Ꝓ'=>'P','ꝓ'=>'p',
			'Ꝕ'=>'P','ꝕ'=>'p','Ꝗ'=>'Q','ꝗ'=>'q','Ꝙ'=>'Q','ꝙ'=>'q','Ꝛ'=>'R','ꝛ'=>'r',
			'Ꝟ'=>'V','ꝟ'=>'v','Ａ'=>'A','Ｂ'=>'B','Ｃ'=>'C','Ｄ'=>'D','Ｅ'=>'E','Ｆ'=>'F',
			'Ｇ'=>'G','Ｈ'=>'H','Ｉ'=>'I','Ｊ'=>'J','Ｋ'=>'K','Ｌ'=>'L','Ｍ'=>'M','Ｎ'=>'N',
			'Ｏ'=>'O','Ｐ'=>'P','Ｑ'=>'Q','Ｒ'=>'R','Ｓ'=>'S','Ｔ'=>'T','Ｕ'=>'U','Ｖ'=>'V',
			'Ｗ'=>'W','Ｘ'=>'X','Ｙ'=>'Y','Ｚ'=>'Z','ａ'=>'a','ｂ'=>'b','ｃ'=>'c','ｄ'=>'d',
			'ｅ'=>'e','ｆ'=>'f','ｇ'=>'g','ｈ'=>'h','ｉ'=>'i','ｊ'=>'j','ｋ'=>'k','ｌ'=>'l',
			'ｍ'=>'m','ｎ'=>'n','ｏ'=>'o','ｐ'=>'p','ｑ'=>'q','ｒ'=>'r','ｓ'=>'s','ｔ'=>'t',
			'ｕ'=>'u','ｖ'=>'v','ｗ'=>'w','ｘ'=>'x','ｙ'=>'y','ｚ'=>'z', 'ß'=>'ss',
//cyrylica
			'́'=>'', 'ё'=>'е');
		return strtr( $text, $trans);
	}

	public static function array2int($tablica)
	{
		//funkcja zamienia tablicę binarną na int (chodzi o to, by w jednym polu typu int zmieścić całą tablicę wartości binarnych)
		//uwaga! może też przyjąć tablice wartości różnych od bool false, wtedy klucze ignoruje całkowicie, a wartości traktuje jak klucze true np. array(1, 2, 4) to to samo co array(1=>true, 2=>true, 3=>false, 4=>true)

			//sprawdzamy w jakim formacie przyszlo, jesli w array(1, 2, 3) zamiast true/false to konwertuj
		if(!empty($tablica)) foreach($tablica as $wartosc) if($wartosc>1) $konwertuj=true;
		if(isset($konwertuj))
		{
			foreach($tablica as $wartosc) $przekonwertowana[$wartosc]=true;
			$tablica=$przekonwertowana;
		}

			//konwersja właściwa na int
		$wynik=0;
		if(!empty($tablica)) foreach($tablica as $klucz=>$wartosc) if($wartosc==true) $wynik+=pow(2, $klucz);

		return $wynik;
	}

	public static function int2array($liczba, $tryb='bool')
	{
		//funkcja odwrotna do tej wyżej, czyli zamienia int na array, gdzie kluczami sa kolejne inty, a wartościami true lub false (jak w enum/set)

		$wynik=array();
		$bin=decbin($liczba);
		for($i0=0; $i0<strlen($bin); $i0++)
		{
			$znak=substr(strrev($bin), $i0, 1);

			if($tryb=='bool') $wynik[$i0]=(bool) $znak;				//zwroci true/false dla kazdego klucza
			if(($tryb=='lista') && ($znak==1)) $wynik[]=$i0;		//zwroci numery bitow na ktorych byly jedynki
		}

		return $wynik;
	}
	
	public static function ifNot(array $dane, $tablica=NULL)
	{
		//funkcja deklaruje zmienne jeśli nie były zadeklarowane i ustawia im wartość domyślną, domyślnie ustawia taką zmienną, ale jeśli podamy tablicę (request, cookie, session etc) to ustawi w tej tablicy
						
		foreach($dane as $klucz=>$wartosc)
		{			
			if($tablica==NULL) $GLOBALS[$klucz]=(isset($GLOBALS[$klucz]) && (($GLOBALS[$klucz])!='undefined'))?$GLOBALS[$klucz]:$wartosc;
			else $GLOBALS[$tablica][$klucz]=(isset($GLOBALS[$tablica][$klucz]) && (($GLOBALS[$tablica][$klucz])!='undefined'))?$GLOBALS[$tablica][$klucz]:$wartosc;
		}
	}
	
	public static function stDevGeneruj($dane)
	{
		//funkcja generuje dane regresji wraz z odchyleniami standardowymi dla tablicy liniowej
		// Filtruj dane - usuń null i zachowaj indeksy
		$daneFiltrowane = [];
		$indeksyFiltrowane = [];
		
		foreach ($dane as $i => $wartosc) {
			if ($wartosc !== null) {
				$daneFiltrowane[] = $wartosc;
				$indeksyFiltrowane[] = $i;
			}
		}
		
		$n = count($daneFiltrowane);
		
		if($n < 2) return False;
		else
		{
			$sumX = 0;
			$sumY = 0;
			$sumXY = 0;
			$sumX2 = 0;

			for ($i = 0; $i < $n; $i++) {
				$x = $indeksyFiltrowane[$i]; // użyj oryginalnego indeksu
				$sumX += $x;
				$sumY += $daneFiltrowane[$i];
				$sumXY += $x * $daneFiltrowane[$i];
				$sumX2 += $x * $x;
			}

			$slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
			$intercept = ($sumY - $slope * $sumX) / $n;

			// Generuj regresję dla WSZYSTKICH oryginalnych indeksów (włącznie z null)
			$regresja = [];
			$nOryginalny = count($dane);
			for ($i = 0; $i < $nOryginalny; $i++) {
				$regresja[] = $slope * $i + $intercept;
			}

			// Oblicz odchylenie standardowe tylko na podstawie nie-null wartości
			$sumaKwadratowBledow = 0;
			for ($i = 0; $i < $n; $i++) {
				$x = $indeksyFiltrowane[$i];
				$wartoscRegresji = $slope * $x + $intercept;
				$sumaKwadratowBledow += pow($daneFiltrowane[$i] - $wartoscRegresji, 2);
			}

			$odchylenieStandardowe = sqrt($sumaKwadratowBledow / $n);

			$odchyleniaGora = [];
			$odchyleniaDol = [];
			$dwaOdchyleniaGora = [];
			$dwaOdchyleniaDol = [];

			for ($i = 0; $i < $nOryginalny; $i++) {
				$odchyleniaGora[] = $regresja[$i] + $odchylenieStandardowe;
				$odchyleniaDol[] = $regresja[$i] - $odchylenieStandardowe;
				$dwaOdchyleniaGora[] = $regresja[$i] + 2 * $odchylenieStandardowe;
				$dwaOdchyleniaDol[] = $regresja[$i] - 2 * $odchylenieStandardowe;
			}

			return ['0'=>$regresja, '1'=>$odchyleniaGora, '-1'=>$odchyleniaDol, '2'=>$dwaOdchyleniaGora, '-2'=>$dwaOdchyleniaDol];
		}
	}
	
	public static function include2str($sciezka, $globals=NULL)
	{
		//funkcja zwraca przetworzony plik PHP ze ścieżki
		
		if(!empty($globals)) foreach($globals as $global) global ${$global};	//ustawianie zmiennych globalnych np. $oDB

		ob_start();
		include $sciezka;
		return ob_get_clean();
	}
	
	public static function wygladz($dane)
	{
		// metoda wygładza liczbę (ma mniejszy zakres)
		
		$wynik = 0;
		$skok = 0;
		$liczba = abs($dane);
		
		while ($liczba > 0) {
			$skok += 1;
			if ($liczba > 0) $wynik += 1;
			$liczba -= $skok;
		}
		
		if ($dane < 0) return -$wynik;
		else return $wynik;
	}
	
	public static function AIGeneruj($zapytanie, $tytul=NULL, $silnik='grok', $flagi=NULL, $lang=NULL)
	{
		//metoda generuje guzik z zapytanie do AI
		
		$lang=$lang??substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		$zapytanie="{$zapytanie} Please answer in {$lang} language.";
		$zapytanie=urlencode(addslashes($zapytanie));
		$tytul=$tytul??$zapytanie;
		
		switch($silnik)
		{
			case 'perplexity': $link="https://www.perplexity.ai/search?q={$zapytanie}"; break;
			case 'openai': $link="https://chat.openai.com/?q={$zapytanie}"; break;
			case 'mistral': $link="https://chat.mistral.ai/chat?q={$zapytanie}"; break;
			case 'grok': $link="https://x.com/i/grok?text={$zapytanie}"; break;
		}
		
		$wynik="<button class=\"CMSButton CMSInputMini\" title=\"{$silnik}:{$tytul}\" onclick=\"window.open('{$link}', '{$silnik}')\">💬</button>";
		
		return $wynik;
	}
	
	public static function round(float $kwota, int $znaki = 1, float $grosze = 0): float
	{
			//zaokrągla kwotę do najbliższej pełnej, połówki, albo końcówki 0.99
		if ($kwota == 0) return 0.0;

		$ujemna = $kwota < 0;
		$kwota = abs($kwota);

		$rzad = floor(log10($kwota)) - ($znaki - 1);
		$krok = pow(10, $rzad) / 2;

		$wynik = \round($kwota / $krok) * $krok;

		$miejsca = max(0, -$rzad + 1);

		if ($grosze > 0) {
			$wynik -= $grosze;
			$miejsca = max($miejsca, (int)ceil(-log10($grosze)));
		}

		$wynik = \round($wynik, $miejsca);

		return $ujemna ? -$wynik : $wynik;
	}
	
	public static function buttonGeneruj($akcja, $znak=' ', $tytul=NULL)
	{
		$wynik="<button class=\"CMSButton CMSInputMini\" onClick=\"{$akcja}\" title=\"{$tytul}\">{$znak}</button>";
		
		return $wynik;
	}

	public static function buttonCloseGeneruj($znak='✗', $tytul='Close')
	{
		$wynik=self::buttonGeneruj("CMSWindowsHide();", $znak, $tytul);
		
		return $wynik;
	}
}