<?php

	//20170124 tostring
	//20180526 poczatek przejscia na PDO
	//20200402 poprawka usuwania rekordów zgodna z PDO
	//20200412 dodany if do foreach w lista_bezposrednie, bo sie sypalo przy pustym wyniku z bazy
	//20210208 poprawiona funkcja bezposrednie, bo wywalalo error przy DELETE FROM
	//20210414 poprawiona komorka_bezposrednie, bo nie działało wcale, do tego komorka() teraz tylko tworzy SQL i przekazuje do komorka_bezposrednie (unifikacja obu metod)
	//20240919 poprawienie wszystkich klamr na docelowe
	//20240919 dodanie obsługi distinct do tabela
	//20240919 kolumna odwołuje się do tabela (unifikacja)
	//20240919 dodanie select()
	//20240919 dodanie jako()
	//20240919 dodanie sql()
	//20240919 usunięcie $sql z argumentacji pobierz (teraz leci przez $this->sql)
	//20240919 usunięcie odwołań do nieużywanej klasy raporty
	//20240919 usunięcie sprawdzanie w każdej funkcji czy jest aktywne połączenie z bazą
	//20240919 baza_ustaw→bazaSet
	//20240919 baza_sprawdz→bazaGet
	//20240919 prefix_ustaw→prefixSet
	//20240919 dodanie colNum()
	//20240924 poprawienie jako('kolumna') do działania na kolumnach tekstowych
	//20240928 rozwiązanie problemów jeśli był błędny SQL i PDO zwracało FALSE
	//20240930 dodanie opcji jako('tablica')
	//20241001 poprawka jako('komorka'), żeby prawidłowo obsługiwał kolumny po nazwach
	//20250221	dodanie aliasu rekordUsun
	//20250415	poprawka kolumna_klucz_bezposrednie, by działała na kolumnach po nazwach
	//20260112	dodanie obsługi pól typu set
	//20260115	dodanie typu jako('grupa')
	//20260115	przejście na utf8->utf8mb4 (obsługa emoji)
	//20260208	dodanie filtra blokującego część SQL Injection
	//20260307	dodanie insert oraz depracated to do rekord_dodaj i dodaj_rekord
	//20260307	dodanie _where która zabezpiecza przed sql injection w where
	//20260307	dodanie update oraz deprecated to do aktualizuj
	//20260314	usunięcie dodawanego bez sensu WHERE w każdym select (AI dodał chuj wie po co).
	//20260314	usunięcie starych metod jeszcze z czasów lilion update
	//20260314	dodanie upsert()
	//20260404	zamiana rekord_id na id
	//20260425	dodanie obsługi FIND_IN_SET w _WHERE
	//20260603	usunięcie $filtry i SQL_Skontroluj (przejście na pełne Prepared Statements)
	//20260603  Dodanie obsługi raw, raw(), _raw() (ochrona przez XSS)
	//20260607	Przejście na Github + Composer + Namespace + zmiana nazwy na Database
	//20260607  dodanie metody query w ramach kompatybilności ze standardami do obsługi zapytań bezwynikowych typu DROP czy TRUNCATE
	//20260809  Dodanie metody exec jako aliasu do query
	//20260810  Usunięcie nieużywanych (zakomentowanych od dawna) metod

namespace Phoenix\Core;
class Database
{
	private	$oDB;
	private	$host;
	private	$login;
	private	$haslo;
	private $_parametry = [];
	private $_raw = FALSE;

	public	$baza;
	public	$prefix;
	public	$ilosc_zapytan=0;

	public	$sql;				//ostatnio wykonane zapytanie
	public	$id;				//id ostatnio dodanego rekordu
	public	$error;				//ostatni komunikat bledu
	public	$colNum=NULL;		//czy kolumny ma zwrócić po numerach?
	public	$distinct=NULL;		//przechowanie DISTINCT do następnego zapytania
	public	$set=[];			//lista kolumn typu set, żeby silnik wiedział, że dane ma zwrócić/zapisać jako array, a nie tekst

	// W pliku Database.php:
	public function __construct($host = null, $login = null, $haslo = null, $baza = null)
	{
		if (is_array($host)) {
			$this->host  = $host['host']  ?? null;
			$this->login = $host['login'] ?? null;
			$this->haslo = $haslo['haslo'] ?? null;
			$this->baza  = $host['baza']  ?? null;
		} else {
			$this->host  = $host  ?? $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? null;
			$this->login = $login ?? $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? null;
			$this->haslo = $haslo ?? $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? null;
			$this->baza  = $baza  ?? $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? null;
		}

		if (!empty($this->host) && !empty($this->login) && !empty($this->haslo)) {
			$this->_polacz();
		}
	}

	private function _polacz()
	{
		try {
			$this->oDB = new \PDO("mysql:host={$this->host};dbname={$this->baza};charset=utf8mb4", $this->login, $this->haslo);
		} catch (\PDOException $e) {
			$this->error = "Nie udało się połączyć z bazą: " . $e->getMessage();
			$this->oDB = null;
		}

		return $this;
	}

	public	function bazaSet($baza)
	{
		//funkcja zmienia używaną bazę danych
		if($this->oDB)
		{
			$this->baza=$baza;
			$this->oDB->query("USE {$this->baza};");
		}

		return $this;
	}

	public	function bazaGet()
	{
		//funkcja zwraca nazwe aktualnie uzywanej bazy

		return $this->baza;
	}

	public	function prefixSet($prefix)
	{
		//funkcja ustawia prefix dla tabel
		$this->prefix=$prefix;
		
		return $this;
	}

	private	function _przygotuj()
	{
		//funkcja wykonuje się przed wykonaniem zapytania
		if ($this->oDB === null) throw new \Exception("Brak aktywnego połączenia z bazą danych (oDB is null). Upewnij się, że podałeś poprawne dane konfiguracyjne.");
	
		$this->bazaSet($this->baza);		//bo mu sie czasem pierdola polaczenia
		$this->ilosc_zapytan++;
		
		return $this;
	}
	
	private function _pobierz()
	{
		if ($this->sql == NULL) return FALSE;

		$efekt = NULL;
		try {
			$this->_przygotuj();
			$stmt = $this->oDB->prepare($this->sql);
			$stmt->execute($this->_parametry);
			$efekt = $stmt;

			$info = $stmt->errorInfo();
			$this->error = ($info[0] !== '00000') ? var_export($info, true) : null;

			$this->sql = $this->_debug_sql($this->sql, $this->_parametry);
		} catch (\PDOException $e) {
			echo "PDO ERROR: \"{$e->getMessage()}\".\nSQL:\"{$this->sql}\"";
			return FALSE;
		}

		if ($efekt == FALSE) return FALSE;

		$wynik = array();
		if ($efekt->rowCount() > 0) {
			foreach ($efekt as $klucz => $rekord) {
				foreach ($rekord as $kolumna => $wartosc) {
					if ($this->colNum === TRUE) {
						if (is_int($kolumna)) $wynik[$klucz][$kolumna] = $wartosc;
					} else {
						if (!is_int($kolumna)) {
							if (!empty($this->set) && in_array($kolumna, $this->set)) {
								$wynik[$klucz][$kolumna] = is_string($wartosc) ? array_values(array_filter(array_map('trim', explode(',', $wartosc)))) : [];
							} else {
								$wynik[$klucz][$kolumna] = $wartosc;
							}
						}
					}
				}
			}
			$efekt->closeCursor();
		}

		$this->colNum = NULL;
		return $wynik;
	}

//funkcje operujace na rekordach

		//metody eksperymentalne
	public	function sql($sql)
	{
			//funkcja ustawia sql do wykonania
		$this->sql=$sql;
		
		return $this;
	}

	public	function colNum($colNum)
	{
			//funkcja ustawia colNum do wykonania (zwracanie po numerach kolumn, a nie po nazwach kolumn)
		$this->colNum=$colNum;
		
		return $this;
	}

	private function _debug_sql(string $sql, array $parametry): string
	{
			//zamienia pytajniki na faktyczne dane, które poszły do bazy
		$i = 0;
		return preg_replace_callback('/\?/', function() use (&$parametry, &$i) {
			$val = $parametry[$i++] ?? null;
			if ($val === null)    return 'NULL';
			if (is_bool($val))    return $val ? 'TRUE' : 'FALSE';
			if (is_numeric($val)) return $val;
			return "'" . addslashes($val) . "'";
		}, $sql);
	}

	private function _where($warunki): array
	{
			//generuje where
	//['id' => 5, 'status >=' => 1]
	//['sql' => 'DATE(created) > ? OR id IN (?,?,?)', 'parametry' => ['2024-01-01', 1,2,3]]
	//['sql' => 'status=1 ORDER BY name LIMIT 10']
	//['ticker' => ['AAPL', 'GOOG', 'MSFT']] → IN (?,?,?)	//jeśli dasz tablicę wartości to zinterpretuje to jako IN
	//['typy' => ['ETF', 'REIT']] → FIND_IN_SET AND	//jeśli kolumna jest w $this->set to użyj FIND_IN_SET
	//['typy' => 'ETF'] → FIND_IN_SET				//pojedyncza wartość też idzie przez FIND_IN_SET jeśli kolumna jest w $this->set
		if (empty($warunki)) {
			return ['sql' => '', 'parametry' => [], 'bezWhere' => false];
		}
		// Przypadek 2 i 3 — surowy sql
		if (isset($warunki['sql'])) {
			return [
				'sql'       => $warunki['sql'],
				'parametry' => $warunki['parametry'] ?? [],
				'bezWhere'  => (bool)preg_match('/^\s*(ORDER|LIMIT|GROUP|HAVING)\b/i', $warunki['sql'])
			];
		}
		// Przypadek 1 — tablica warunków (tylko AND)
		$parts     = [];
		$parametry = [];
		foreach ($warunki as $klucz => $wartosc) {
			preg_match('/^([a-zA-Z0-9_]+)\s*(=|!=|<>|<=|>=|<|>|LIKE|NOT LIKE)?$/i', trim($klucz), $m);
			$kolumna  = $m[1];
			$operator = strtoupper(trim($m[2] ?? '=')) ?: '=';
			if ($wartosc === null) {
				$parts[] = "`$kolumna` IS NULL";
			} elseif (in_array($kolumna, $this->set)) {
				// SET — zawsze FIND_IN_SET AND, niezależnie czy string czy tablica
				foreach ((array)$wartosc as $v) {
					$parts[]     = "FIND_IN_SET(?, `$kolumna`)";
					$parametry[] = $v;
				}
			} elseif (is_array($wartosc)) {
				$wartosc = array_values(array_filter($wartosc));
				if (empty($wartosc)) {
					$parts[] = '1=0';
				} else {
					$placeholders = implode(',', array_fill(0, count($wartosc), '?'));
					$parts[]      = "`$kolumna` IN ($placeholders)";
					$parametry    = array_merge($parametry, $wartosc);
				}
			} else {
				$parts[]     = "`$kolumna` $operator ?";
				$parametry[] = $wartosc;
			}
		}
		return [
			'sql'       => implode(' AND ', $parts),
			'parametry' => $parametry,
			'bezWhere'  => false
		];
	}

	public function query(string $sql, array $parametry = [])
	{
			//funkcja w ramach kompatybilności z innymi klasami do obsługi DB czy do wykonywania zapytań bezwynikowych typu DROP czy TRUNCATE 

		$this->_przygotuj();
		$this->sql = $sql;
		$this->ilosc_zapytan++;

		try {
			// Jeśli nie ma parametrów, wykonujemy szybkie, surowe zapytanie
			if (empty($parametry)) {
				$stmt = $this->oDB->query($sql);
			} else {
				// Jeśli są parametry, bezpiecznie je przygotowujemy
				$stmt = $this->oDB->prepare($sql);
				$stmt->execute($parametry);
			}

			// Zapisujemy błędy i debugujemy SQL, jeśli coś poszło nie tak
			if ($stmt) {
				$info = $stmt->errorInfo();
				$this->error = ($info[0] !== '00000') ? var_export($info, true) : null;
				if (!empty($parametry)) {
					$this->sql = $this->_debug_sql($sql, $parametry);
				}
			}

			return $stmt; // Zwraca obiekt \PDOStatement lub FALSE
		} catch (\PDOException $e) {
			$this->error = $e->getMessage();
			return FALSE;
		}
	}

	public function exec(string $sql)
	{
			//alias query
		return $this->query($sql);
	}

	public function select($tabelaLUBsql, $kolumny = NULL, $warunki = NULL)
	{
		$this->_parametry = [];
		if ($kolumny == NULL) {
			// Przypadek 4 — goły SQL, developer odpowiada sam
			// Przypadek 5 — goły SQL z parametrami ['parametry' => [...]]
			$this->sql = $tabelaLUBsql;
			$this->_parametry = $warunki['parametry'] ?? [];
		} else {
			if (is_array($kolumny)) {
				$SQLKolumny = '`' . implode('`, `', $kolumny) . '`';
			} else {
				$SQLKolumny = $kolumny;
			}
			$distinct         = $this->distinct ? 'DISTINCT' : '';
			$this->distinct   = NULL;
			$where            = $this->_where($warunki);
			$this->_parametry = $where['parametry'];
			$this->sql        = "SELECT {$distinct} {$SQLKolumny} FROM `{$this->prefix}{$tabelaLUBsql}`";
			if ($where['bezWhere'])        $this->sql .= ' ' . $where['sql'];
			elseif (!empty($where['sql'])) $this->sql .= ' WHERE ' . $where['sql'];
		}
		return $this;
	}

	public function raw()
	{
		$this->_raw = TRUE;
		return $this;
	}

	private function _raw($dane)
	{
		// Jeśli włączyliśmy tryb surowy przez ->raw()
		if ($this->_raw === TRUE) {
			$this->_raw = FALSE; // Resetujemy prywatną flagę dla kolejnych zapytań
			return $dane;        // Zwracamy nienaruszone dane z bazy
		}

		// Jeśli to tablica, przechodzimy po niej rekurencyjnie
		if (is_array($dane)) {
			return array_map([$this, '_raw'], $dane);
		}

		// Filtrujemy tylko i wyłącznie stringi
		if (is_string($dane)) {
			return htmlspecialchars($dane, ENT_QUOTES, 'UTF-8');
		}

		return $dane;
	}

	public  function jako($jako=NULL)
    {
        // Jeśli brak formy, zwracamy zapytanie SQL natychmiast (bez dotykania mechanizmu raw)
        if ($jako === NULL) {
            return $this->sql;
        }
        
        $wynik = NULL;
        
        switch($jako)
        {
            case 'tabela': 
                $wynik = $this->_pobierz(); //zwraca tabelę jak jest
                break;
                
            case 'kolumna':
                $wynik = array();
                $tabela = $this->_pobierz();
                if(!empty($tabela)) foreach($tabela as $klucz=>$wartosc)
                {
                    $kolumny = array_keys($wartosc); //ustalenie nazw pobranych kolumn
                    $wynik[$klucz] = $wartosc[$kolumny[0]];
                }
                break;

            case 'wiersz':
                $tabela = $this->_pobierz();
                if(!empty($tabela)) foreach($tabela as $klucz=>$wartosc) $wynik = $wartosc; //w praktyce przekazuje tylko ostatni zwrocony wiersz
                break;

            case 'komorka':
                $tabela = $this->_pobierz();
                if(!$tabela) {
                    $wynik = NULL;
                } else {
                    $kolumny = array_keys($tabela[0]);
                    foreach($tabela as $klucz=>$wartosc) $wynik = $wartosc[$kolumny[0]]; //w praktyce przekazuje tylko pojedyncza $kolumna z pierwszego wiersza
                }
                break;

            case 'tablica':
				// Przekształca wynik dwukolumnowy: kolumna_1 staje się kluczem głównym, 
                // a wartościami jest tablica elementów z kolumna_2 (np. [ticker][] = idKonta)               $wynik = array();
                $tabela = $this->_pobierz();
                if(!empty($tabela)) foreach($tabela as $klucz=>$wartosc)
                {
                    $kolumny = array_keys($wartosc); //ustalenie nazw pobranych kolumn
                    $wynik[$wartosc[$kolumny[0]]][] = $wartosc[$kolumny[1]];
                }
                break;                  

            case 'grupa':
                $wynik = array();
                $tabela = $this->_pobierz();
                if(!empty($tabela)) foreach($tabela as $klucz=>$wartosc)
                {
                    $kolumny = array_keys($wartosc);
                    if(count($kolumny) < 2) continue;  // Potrzebujemy przynajmniej 2 kolumn
                    
                    $klucz_grupy = $wartosc[$kolumny[0]];  // Pierwsza kolumna jako klucz główny
                    $klucz_podgrupy = $wartosc[$kolumny[1]];  // Druga kolumna jako klucz w podgrupie
                    
                    unset($wartosc[$kolumny[0]]);
                    unset($wartosc[$kolumny[1]]);
                    
                    $wynik[$klucz_grupy][$klucz_podgrupy] = $wartosc;
                }
                break;                  
        }

        // Jeden, elegancki return na samym końcu dla wszystkich typów danych!
        return $this->_raw($wynik);
    }

	public function insert($tabela, array $dane)
	{
		$kolumny = $wartosci = $insert = [];
		foreach($dane as $klucz => $wartosc)
		{
			if (in_array($klucz, $this->set) && is_array($wartosc)) {
				$wartosc = implode(',', array_map('trim', array_unique($wartosc)));
			}
			$kolumny[]       = "`$klucz`";
			$wartosci[]      = "?";
			$insert[]        = $wartosc;
		}
		$sql = "INSERT INTO `{$this->baza}`.`{$this->prefix}$tabela` ("
			 . implode(', ', $kolumny) . ") VALUES ("
			 . implode(', ', $wartosci) . ")";

		$stmt            = $this->oDB->prepare($sql);
		$wynik           = $stmt->execute($insert);
		$this->sql       = $this->_debug_sql($sql, $insert);
		$info            = $stmt->errorInfo();
		$this->error     = ($info[0] !== '00000') ? var_export($info, true) : null;
		$this->id		 = $this->oDB->lastInsertId();

		return $wynik;
	}

	public function upsert($tabela, array $dane): bool
	{
		$kolumny   = [];
		$wartosci  = [];
		$insert    = [];
		$update    = [];

		foreach ($dane as $klucz => $wartosc) {
			if (in_array($klucz, $this->set) && is_array($wartosc)) {
				$wartosc = implode(',', array_map('trim', array_unique($wartosc)));
			}
			$kolumny[]  = "`$klucz`";
			$wartosci[] = '?';
			$insert[]   = $wartosc;
			$update[]   = "`$klucz`=VALUES(`$klucz`)";
		}

		$sql = "INSERT INTO `{$this->baza}`.`{$this->prefix}$tabela` ("
			 . implode(', ', $kolumny) . ") VALUES ("
			 . implode(', ', $wartosci) . ")"
			 . " ON DUPLICATE KEY UPDATE "
			 . implode(', ', $update);

		$stmt            = $this->oDB->prepare($sql);
		$wynik           = $stmt->execute($insert);
		$this->sql       = $this->_debug_sql($sql, $insert);
		$info            = $stmt->errorInfo();
		$this->error     = ($info[0] !== '00000') ? var_export($info, true) : null;
		$this->id		 = $this->oDB->lastInsertId();

		return (bool)$wynik;
	}

	public function update(string $tabela, array $dane, $warunki = []): bool
	{
		$set_placeholders = [];
		$set_params       = [];

		foreach ($dane as $klucz => $wartosc) {
			if ($klucz === 'sql') {
				// surowe wyrażenie SET, parametry w $dane['parametry']
				$set_placeholders[] = $wartosc;
				$set_params = array_merge($set_params, $dane['parametry'] ?? []);
				continue;
			}
			if ($klucz === 'parametry') continue;   // już obsłużone wyżej
			if (in_array($klucz, $this->set) && is_array($wartosc)) {
				$wartosc = implode(',', array_map('trim', array_unique($wartosc)));
			}
			$set_placeholders[] = "`$klucz`=?";
			$set_params[]       = $wartosc;
		}
    
		$where  = $this->_where($warunki);
		$params = array_merge($set_params, $where['parametry']);

		$sql = "UPDATE `{$this->baza}`.`{$this->prefix}$tabela` SET " . implode(', ', $set_placeholders);
		if ($where['bezWhere'])        $sql .= ' ' . $where['sql'];
		elseif (!empty($where['sql'])) $sql .= ' WHERE ' . $where['sql'];

		$stmt            = $this->oDB->prepare($sql);
		$wynik           = $stmt->execute($params);
		$this->sql       = $this->_debug_sql($sql, $params);
		$info            = $stmt->errorInfo();
		$this->error     = ($info[0] !== '00000') ? var_export($info, true) : null;
		$this->id		 = $this->oDB->lastInsertId();

		return (bool)$wynik;
	}

	public function klucz($tabela, $klucze, $wartosci, $warunki = [])
	{
		$distinct=$this->distinct ? 'DISTINCT' : '';
		$this->distinct = NULL;
		$where = $this->_where($warunki);
		$this->_parametry = $where['parametry'];
		$this->sql = "SELECT {$distinct} `$klucze`, `$wartosci` FROM `{$this->prefix}$tabela`";
		if ($where['bezWhere'])        $this->sql .= ' ' . $where['sql'];
		elseif (!empty($where['sql'])) $this->sql .= ' WHERE ' . $where['sql'];
		$efekt = $this->colNum(TRUE)->_pobierz();
		if (empty($efekt)) return NULL;
		$wynik = [];
		foreach ($efekt as $wartosc) $wynik[$wartosc[0]] = $wartosc[1];
		return $wynik;
	}

	public function operacja($tabela, $operacja, $kolumna, $warunki = [])
	{
		$where = $this->_where($warunki);
		$this->_parametry = $where['parametry'];
		$this->sql = "SELECT $operacja(`$kolumna`) as `$kolumna` FROM `{$this->prefix}$tabela`";
		if ($where['bezWhere'])        $this->sql .= ' ' . $where['sql'];
		elseif (!empty($where['sql'])) $this->sql .= ' WHERE ' . $where['sql'];
		$efekt = $this->_pobierz();
		if ($efekt == false) return false;
		$wynik = NULL;
		foreach ($efekt as $wartosc) if ($wynik == NULL) $wynik = $wartosc[$kolumna];
		return $wynik;
	}

	public function delete($tabela, $warunki = [])
	{
		$where  = $this->_where($warunki);
		$params = $where['parametry'];

		$sql = "DELETE FROM `{$this->baza}`.`{$this->prefix}$tabela`";
		if ($where['bezWhere'])        $sql .= ' ' . $where['sql'];
		elseif (!empty($where['sql'])) $sql .= ' WHERE ' . $where['sql'];

		$stmt            = $this->oDB->prepare($sql);
		$wynik           = $stmt->execute($params);
		$this->sql       = $this->_debug_sql($sql, $params);
		$info            = $stmt->errorInfo();
		$this->error     = ($info[0] !== '00000') ? var_export($info, true) : null;

		return (bool)$wynik;
	}
}