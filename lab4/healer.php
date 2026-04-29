<?php
declare(strict_types=1);

/*
 * LR4 healer.php
 *
 * Режимы:
 *   php healer.php scan
 *   php healer.php heal
 *   php healer.php restore
 *   php healer.php verify
 *
 * Путь к проекту можно передать вторым аргументом:
 *   php healer.php scan ../project_infected
 */

$mode = $argv[1] ?? 'scan';
$baseDir = isset($argv[2]) ? realpath($argv[2]) : __DIR__;

if ($baseDir === false || !is_dir($baseDir)) {
    fwrite(STDERR, "Базовая папка проекта не найдена.\n");
    exit(1);
}

$allowedExtensions = ['php', 'html', 'htm', 'js'];

function isAllowedFile(SplFileInfo $file, array $allowedExtensions): bool
{
    if (!$file->isFile()) {
        return false;
    }

    $ext = strtolower($file->getExtension());
    return in_array($ext, $allowedExtensions, true);
}

function iterProjectFiles(string $baseDir, array $allowedExtensions): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!isAllowedFile($file, $allowedExtensions)) {
            continue;
        }

        $path = $file->getPathname();

        if (str_ends_with($path, '.lr4bak') || str_ends_with($path, '.healbak')) {
            continue;
        }

        $files[] = $path;
    }

    sort($files);
    return $files;
}

function relPath(string $baseDir, string $path): string
{
    $base = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';
    $full = str_replace('\\', '/', $path);
    return str_starts_with($full, $base) ? substr($full, strlen($base)) : $full;
}

function readFileSafe(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Не удалось прочитать файл: {$path}");
    }
    return $content;
}

function writeFileSafe(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Не удалось записать файл: {$path}");
    }
}

function ensureHealBackup(string $path): void
{
    $backup = $path . '.healbak';
    if (!file_exists($backup)) {
        if (!copy($path, $backup)) {
            throw new RuntimeException("Не удалось создать heal backup: {$backup}");
        }
    }
}

function detectMarkers(string $content): array
{
    $patterns = [
        'php_blocks' => '/<\?php\s*\/\*\s*LR4_ATTACK_START:\s*([a-z0-9]+)\s*\*\/.*?\/\*\s*LR4_ATTACK_END:\s*\1\s*\*\/\s*\?>\s*/is',
        'html_blocks' => '/<!--\s*\/\*\s*LR4_ATTACK_START:\s*([a-z0-9]+)\s*\*\/\s*-->.*?<!--\s*\/\*\s*LR4_ATTACK_END:\s*\1\s*\*\/\s*-->\s*/is',
    ];

    $result = [
        'infected' => false,
        'php_blocks' => 0,
        'html_blocks' => 0,
    ];

    foreach ($patterns as $key => $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            $result[$key] = count($matches[0]);
            if ($result[$key] > 0) {
                $result['infected'] = true;
            }
        }
    }

    return $result;
}

function healContent(string $content): array
{
    $patterns = [
        '/<\?php\s*\/\*\s*LR4_ATTACK_START:\s*([a-z0-9]+)\s*\*\/.*?\/\*\s*LR4_ATTACK_END:\s*\1\s*\*\/\s*\?>\s*/is',
        '/<!--\s*\/\*\s*LR4_ATTACK_START:\s*([a-z0-9]+)\s*\*\/\s*-->.*?<!--\s*\/\*\s*LR4_ATTACK_END:\s*\1\s*\*\/\s*-->\s*/is',
    ];

    $removed = 0;
    $clean = $content;

    foreach ($patterns as $pattern) {
        $count = 0;
        $clean = preg_replace($pattern, '', $clean, -1, $count);
        if ($clean === null) {
            throw new RuntimeException("Ошибка preg_replace при очистке файла.");
        }
        $removed += $count;
    }

    $clean = preg_replace("/^\s*\n/u", '', $clean, 1) ?? $clean;

    return [
        'content' => $clean,
        'removed' => $removed,
    ];
}

function scanProject(string $baseDir, array $allowedExtensions): int
{
    $files = iterProjectFiles($baseDir, $allowedExtensions);
    $infectedCount = 0;

    foreach ($files as $path) {
        $content = readFileSafe($path);
        $info = detectMarkers($content);

        if ($info['infected']) {
            $infectedCount++;
            $bak = file_exists($path . '.lr4bak') ? 'YES' : 'NO';
            echo "[INFECTED] " . relPath($baseDir, $path)
                . " | php=" . $info['php_blocks']
                . " | html=" . $info['html_blocks']
                . " | lr4bak=" . $bak . "\n";
        }
    }

    if ($infectedCount === 0) {
        echo "[OK] Зараженных файлов не найдено.\n";
    } else {
        echo "[TOTAL] Зараженных файлов: {$infectedCount}\n";
    }

    return $infectedCount;
}

function healProject(string $baseDir, array $allowedExtensions): int
{
    $files = iterProjectFiles($baseDir, $allowedExtensions);
    $healed = 0;

    foreach ($files as $path) {
        $content = readFileSafe($path);
        $info = detectMarkers($content);

        if (!$info['infected']) {
            continue;
        }

        ensureHealBackup($path);

        $result = healContent($content);
        writeFileSafe($path, $result['content']);
        $healed++;

        echo "[HEALED] " . relPath($baseDir, $path)
            . " | removed_blocks=" . $result['removed'] . "\n";
    }

    if ($healed === 0) {
        echo "[OK] Нечего лечить, зараженных файлов не найдено.\n";
    } else {
        echo "[TOTAL] Вылечено файлов: {$healed}\n";
    }

    return $healed;
}

function restoreFromLr4Backup(string $baseDir): int
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
    );

    $restored = 0;

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        if (!str_ends_with($path, '.lr4bak')) {
            continue;
        }

        $target = substr($path, 0, -7);
        if (!copy($path, $target)) {
            throw new RuntimeException("Не удалось восстановить файл из backup: {$target}");
        }

        $restored++;
        echo "[RESTORED] " . relPath($baseDir, $target) . "\n";
    }

    if ($restored === 0) {
        echo "[OK] Резервные копии .lr4bak не найдены.\n";
    } else {
        echo "[TOTAL] Восстановлено файлов: {$restored}\n";
    }

    return $restored;
}

function verifyClean(string $baseDir, array $allowedExtensions): void
{
    $infected = scanProject($baseDir, $allowedExtensions);
    if ($infected > 0) {
        exit(2);
    }
    exit(0);
}

try {
    switch ($mode) {
        case 'scan':
            scanProject($baseDir, $allowedExtensions);
            break;

        case 'heal':
            healProject($baseDir, $allowedExtensions);
            break;

        case 'restore':
            restoreFromLr4Backup($baseDir);
            break;

        case 'verify':
            verifyClean($baseDir, $allowedExtensions);
            break;

        default:
            fwrite(STDERR, "Неизвестный режим: {$mode}\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}