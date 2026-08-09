<?php

namespace Phoenix\Core\Console;

class DbSyncSchema
{
    public static function run(string $baseDir): void
    {
        $schemaFile = rtrim($baseDir, '/') . '/database/schema.sql';

        if (!file_exists($schemaFile) || filesize($schemaFile) === 0) {
            echo "❌ Błąd: Brak pliku database/schema.sql lub plik jest pusty.\n";
            echo "💡 Najpierw utwórz strukturę lub zrób zrzut komendą vendor/bin/pphpc db:dump-schema\n";
            exit(1);
        }

        $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $dbUser = $_ENV['DB_USER'] ?? '';
        $dbPass = $_ENV['DB_PASS'] ?? '';

        if (empty($dbName) || empty($dbUser)) {
            echo "❌ Błąd: Brak konfiguracji bazy danych w pliku .env\n";
            exit(1);
        }

        // Ścieżka do binarki mysqldef
        $mysqldefBin = self::getMysqldefBinary($baseDir);

        echo "🔍 Porównywanie struktury w database/schema.sql z bazą '{$dbName}'...\n\n";

        // Step 1: Dry run
        $passParam = !empty($dbPass) ? sprintf('-p%s', escapeshellarg($dbPass)) : '';
        $dryRunCmd = sprintf(
            '%s -h %s -u %s %s %s --dry-run %s 2>&1',
            escapeshellarg($mysqldefBin),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName),
            escapeshellarg($schemaFile)
        );

        $output = [];
        $returnVar = 0;
        exec($dryRunCmd, $output, $returnVar);

        $diffSql = implode("\n", $output);

        if (strpos($diffSql, 'nothing to apply') !== false || empty(trim($diffSql))) {
            echo "✅ Struktura bazy danych jest idealnie zgodna z plikiem database/schema.sql!\n";
            echo "ℹ️ Brak zmian do wdrożenia.\n";
            exit(0);
        }

        echo "--------------------------------------------------\n";
        echo "⚠️ WYKRYTO RÓŻNICE W STRUKTURZE BAZY DANYCH:\n";
        echo "--------------------------------------------------\n";
        echo $diffSql . "\n";
        echo "--------------------------------------------------\n";

        if (strpos($diffSql, 'DROP TABLE') !== false || strpos($diffSql, 'DROP COLUMN') !== false) {
            echo "⚠️ UWAGA! Wykryto zapytanie DROP (usuwanie tabeli lub kolumny)!\n";
            echo "💡 Jeśli zmieniłeś nazwę kolumny/tabeli, dodaj w schema.sql komentarz:\n";
            echo "   -- @renamed from=stara_nazwa_kolumny\n\n";
        }

        // Step 2: Confirm
        echo "❓ Czy chcesz zastosować powyższe zmiany w bazie danych? [Y/n]: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $confirmation = trim((string)$line);

        if ($confirmation !== 'Y' && $confirmation !== 'YES') {
            echo "❌ Synchronizacja struktury została anulowana.\n";
            exit(0);
        }

        echo "\n🚀 Aplikowanie zmian w strukturze...\n";

        $applyCmd = sprintf(
            '%s -h %s -u %s %s %s %s 2>&1',
            escapeshellarg($mysqldefBin),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName),
            escapeshellarg($schemaFile)
        );

        system($applyCmd, $applyReturn);

        if ($applyReturn === 0) {
            echo "\n✅ Struktura bazy danych została pomyślnie zaktualizowana bez utraty danych!\n";
        } else {
            echo "\n❌ Wystąpił błąd podczas aktualizacji struktury bazy.\n";
            exit(1);
        }
    }

    private static function getMysqldefBinary(string $baseDir): string
    {
        // Sprawdź czy jest w PATH
        $globalPath = shell_exec('which mysqldef 2>/dev/null');
        if (!empty(trim((string)$globalPath))) {
            return trim((string)$globalPath);
        }

        $localBin = $baseDir . '/vendor/bin/mysqldef';
        if (file_exists($localBin) && is_executable($localBin)) {
            return $localBin;
        }

        // Pobranie mysqldef jeśli nie istnieje
        echo "📦 Nie znaleziono mysqldef. Pobieranie binarki mysqldef z GitHub...\n";
        $binDir = dirname($localBin);
        if (!is_dir($binDir)) {
            @mkdir($binDir, 0755, true);
        }

        $arch = php_uname('m');
        $os = strtolower(PHP_OS);

        $downloadUrl = "https://github.com/sqldef/sqldef/releases/latest/download/mysqldef_linux_amd64.tar.gz";
        if (strpos($arch, 'arm') !== false || strpos($arch, 'aarch64') !== false) {
            $downloadUrl = "https://github.com/sqldef/sqldef/releases/latest/download/mysqldef_linux_arm64.tar.gz";
        }

        $tmpTar = sys_get_temp_dir() . '/mysqldef.tar.gz';
        copy($downloadUrl, $tmpTar);

        $p = new \PharData($tmpTar);
        $p->extractTo($binDir, 'mysqldef', true);
        @unlink($tmpTar);

        chmod($localBin, 0755);

        return $localBin;
    }
}