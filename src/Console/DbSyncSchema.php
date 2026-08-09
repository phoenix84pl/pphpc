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

        $cleanSchemaFile = self::prepareCleanSchemaFile($schemaFile);
        $mysqldefBin = self::getMysqldefBinary($baseDir);

        echo "🔍 Porównywanie struktury w database/schema.sql z bazą '{$dbName}'...\n\n";

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

        // Odfiltrowujemy nagłówki dry-run i pominięte operacje widoków/tabel
        $filteredOutput = array_filter($output, function ($line) {
            $trimmed = trim($line);
            if (empty($trimmed)) return false;
            if (strpos($trimmed, '-- dry run --') !== false) return false;
            if (strpos($trimmed, 'BEGIN;') !== false) return false;
            if (strpos($trimmed, 'COMMIT;') !== false) return false;
            if (strpos($trimmed, '-- Skipped:') !== false) return false;
            return true;
        });

        $diffSql = trim(implode("\n", $filteredOutput));

        if (empty($diffSql) || strpos($diffSql, 'nothing to apply') !== false) {
            echo "✅ Struktura tabel bazy danych jest idealnie zgodna z plikiem database/schema.sql!\n";
            echo "ℹ️ Brak zmian do wdrożenia.\n";
            @unlink($cleanSchemaFile);
            exit(0);
        }

        echo "--------------------------------------------------\n";
        echo "⚠️ WYKRYTO RÓŻNICE W STRUKTURZE BAZY DANYCH:\n";
        echo "--------------------------------------------------\n";
        echo $diffSql . "\n";
        echo "--------------------------------------------------\n";

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

    private static function prepareCleanSchemaFile(string $originalSchemaFile): string
    {
        $content = file_get_contents($originalSchemaFile);

        // Usuń linie DROP TABLE / DROP VIEW oraz instrukcje CREATE VIEW
        $content = preg_replace('/^DROP TABLE IF EXISTS.*?;/m', '', $content);
        $content = preg_replace('/^DROP VIEW IF EXISTS.*?;/m', '', $content);
        $content = preg_replace('/^CREATE VIEW.*?;/s', '', $content);

        // Usuń komentarze systemowe MySQL
        $content = preg_replace('/\/\*!.*?\*\//s', '', $content);

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