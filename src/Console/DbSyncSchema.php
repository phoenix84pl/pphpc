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

        // Przygotujmy oczyszczoną wersję pliku schema.sql dla mysqldef
        $cleanSchemaFile = self::prepareCleanSchemaFile($schemaFile);

        // Ścieżka do binarki mysqldef
        $mysqldefBin = self::getMysqldefBinary($baseDir);

        echo "🔍 Porównywanie struktury w database/schema.sql z bazą '{$dbName}'...\n\n";

        // Step 1: Dry run
        $passParam = !empty($dbPass) ? sprintf('-p%s', escapeshellarg($dbPass)) : '';
        $dryRunCmd = sprintf(
            '%s -h %s -u %s %s %s --dry-run --file %s 2>&1',
            escapeshellarg($mysqldefBin),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName),
            escapeshellarg($cleanSchemaFile)
        );

        $output = [];
        $returnVar = 0;
        exec($dryRunCmd, $output, $returnVar);

        $diffSql = implode("\n", $output);

        if (strpos($diffSql, 'nothing to apply') !== false || empty(trim($diffSql))) {
            echo "✅ Struktura bazy danych jest idealnie zgodna z plikiem database/schema.sql!\n";
            echo "ℹ️ Brak zmian do wdrożenia.\n";
            @unlink($cleanSchemaFile);
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
            @unlink($cleanSchemaFile);
            exit(0);
        }

        echo "\n🚀 Aplikowanie zmian w strukturze...\n";

        $applyCmd = sprintf(
            '%s -h %s -u %s %s %s --file %s 2>&1',
            escapeshellarg($mysqldefBin),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $passParam,
            escapeshellarg($dbName),
            escapeshellarg($cleanSchemaFile)
        );

        system($applyCmd, $applyReturn);

        @unlink($cleanSchemaFile);

        if ($applyReturn === 0) {
            echo "\n✅ Struktura bazy danych została pomyślnie zaktualizowana bez utraty danych!\n";
        } else {
            echo "\n❌ Wystąpił błąd podczas aktualizacji struktury bazy.\n";
            exit(1);
        }
    }

    /**
     * Oczyszcza plik schema.sql ze zbędnych instrukcji typu DROP TABLE IF EXISTS
     * oraz komentarzy systemowych MySQL, których mysqldef nie parsuje.
     */
    private static function prepareCleanSchemaFile(string $originalSchemaFile): string
    {
        $content = file_get_contents($originalSchemaFile);

        // Usuń linie DROP TABLE / DROP VIEW
        $content = preg_replace('/^DROP TABLE IF EXISTS.*?;/m', '', $content);
        $content = preg_replace('/^DROP VIEW IF EXISTS.*?;/m', '', $content);

        // Usuń komentarze systemowe MySQL (np. /*!40101 SET ... */)
        $content = preg_replace('/\/\*!.*?\*\//s', '', $content);

        // Zapisz do pliku tymczasowego
        $tmpFile = sys_get_temp_dir() . '/clean_schema_' . md5($originalSchemaFile) . '.sql';
        file_put_contents($tmpFile, $content);

        return $tmpFile;
    }

    private static function getMysqldefBinary(string $baseDir): string
    {
        $globalPath = shell_exec('which mysqldef 2>/dev/null');
        if (!empty(trim((string)$globalPath))) {
            return trim((string)$globalPath);
        }

        $localBin = $baseDir . '/vendor/bin/mysqldef';
        if (file_exists($localBin) && is_executable($localBin)) {
            return $localBin;
        }

        echo "📦 Nie znaleziono mysqldef. Pobieranie binarki mysqldef z GitHub...\n";
        $binDir = dirname($localBin);
        if (!is_dir($binDir)) {
            @mkdir($binDir, 0755, true);
        }

        $arch = php_uname('m');

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