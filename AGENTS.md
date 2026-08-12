1. Rola i Ramy Architektoniczne
pphpc (Phoenix Core) to uniwersalny silnik i mikroframework dla aplikacji z rodziny Phoenix.

Jest to wyłącznie warstwa techniczna i narzędziowa — silnik nie zawiera zadnej logiki domenowej konkretnych aplikacji (np. giełdowej w Phoenix Terminal).

Vendor Namespace (PSR-4): Phoenix\Core\ (mapowany na katalog src/)

Standard wyjścia: Elastyczne serwowanie pełnych szablonów HTML (templates/layout.phtml) dla żądań z paska adresu oraz surowych wycinków HTML dla AJAX / widoków okienkowych (?render=...).

2. Mapa Katalogów i Odpowiedzialności
pphpc/
├── assets/                 # Globalne statyczne zasoby silnika (CSS, JS)
│   ├── css/core.css        # Główny, bazowy arkusz stylów interfejsu (CMS/PWA)
│   └── js/                 # Skrypty silnika i biblioteki bazowe
│       ├── core.js         # Obsługa interfejsu, okien, AJAX, CMSInit()
│       └── jquery.min.js   # Zależność frontendowa
│
├── bin/
│   └── pphpc               # Plik wykonywalny CLI silnika (dla terminala/konsoli)
│
├── src/                    # Główny katalog klas silnika (Namespace: Phoenix\Core)
│   ├── Bootstrap.php       # Inicjalizacja środowiska, ładowanie zmiennych/sesji
│   ├── Database.php        # Zbudowana klasa wrappera PDO (wyczyszczone parametry, operacje)
│   ├── Router.php          # Uniwersalny mechanizm mapowania URL na kontrolery
│   │
│   ├── Console/            # Narzędzia CLI i zarządzania bazą danych
│   │   ├── DbDumpData.php     # Eksport danych bazy
│   │   ├── DbDumpSchema.php   # Eksport struktury (schematu) bazy
│   │   ├── DbImportData.php   # Import danych
│   │   ├── DbPullData.php     # Pobieranie danych z zewnętrznych instancji
│   │   ├── DbSyncSchema.php   # Synchronizacja struktur tabel
│   │   └── DbUpdateViews.php  # Aktualizacja widoków SQL
│   │
│   ├── Controller/         # Uniwersalne kontrolery systemowe
│   │   ├── Component.php   # Generyczna obsługa komponentów interfejsu
│   │   ├── Status.php      # Odpowiedzi statusu systemowego / healthcheck
│   │   ├── action/         # Obsługa uniwersalnych akcji systemowych
│   │   ├── api/            # Punkt wejścia dla API silnika
│   │   └── file/           # Kontroler do serwowania/obsługi plików
│   │
│   └── Library/            # Biblioteki narzędziowe silnika
│       ├── Uzytki.php      # Helpery systemowe (przyciskGeneruj, AIGeneruj, dekodery)
│       └── Wykres.php      # Klasa generująca struktury wykresów
│
├── templates/              # Domyślny katalog szablonów i widoków (.phtml)
├── composer.json           # Definicja autoloada i zależności Composer
└── composer.lock           # Zablokowane wersje pakietów
3. Złote Zasady Pracy z Repo pphpc
Zero zależności od aplikacji (Zero App-Knowledge):

Żaden plik w src/ nie może wiedzieć o istnieniu tickerów, spółek, portfeli czy dywidend. Kod ma być w 100% zdatny do użycia w dowolnym nowym projekcie (np. sklepie, panelu administracyjnym czy CMS-ie).

Dwuwarstwowe renderowanie (Full vs AJAX):

Domyślnie kontroler ładuje układ z otoczką layout.phtml.

Gdy w zapytaniu obecny jest parametr ?render=... (np. window, widget, intro) lub nagłówek AJAX, kontroler wycina otoczkę i zwraca sam czysty wycinek z templates/.

Czystość kodu i separacja HTML:

HTML nie jest sklejany w zmiennych PHP wewnątrz klas src/ (brak echo "<tr>...").

Logika przygotowuje czysty zestaw danych (tablicę/obiekt), a do generowania znaczników używa się plików .phtml w katalogu templates/.

Narzędzia Bazy Danych (Console/):

Wszelkie automatyzacje DB (dump, sync, update widoków) opierają się wyłącznie na rozwiązaniach z katalogu src/Console/ i są wywoływane przez skrypt bin/pphpc.