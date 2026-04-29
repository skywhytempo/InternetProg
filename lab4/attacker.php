<?php
declare(strict_types=1);

/*
 * LR4 attacker.php
 * Учебный скрипт для локальной копии проекта.
 *
 * Режимы:
 *   php attacker.php status
 *   php attacker.php f1
 *   php attacker.php f2
 *   php attacker.php f3
 *   php attacker.php r1
 *   php attacker.php r2
 *   php attacker.php r3
 *   php attacker.php all
 *   php attacker.php restore
 *
 * Если проект лежит не в текущей папке, передай путь вторым аргументом:
 *   php attacker.php all ../project_infected
 */

$mode = $argv[1] ?? 'status';
$baseDir = isset($argv[2]) ? realpath($argv[2]) : __DIR__;

if ($baseDir === false || !is_dir($baseDir)) {
    fwrite(STDERR, "Базовая папка проекта не найдена.\n");
    exit(1);
}

$targets = [
    'f1' => 'account/auth.php',
    'f2' => 'account/register.php',
    'f3' => 'account/gallery.php',
    'r1' => 'account/auth.php',
    'r2' => 'account/register.php',
    'r3' => 'account/gallery.php',
];

function fullPath(string $baseDir, string $relative): string
{
    return rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function ensureFileExists(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Файл не найден: {$path}");
    }
}

function backupPath(string $path): string
{
    return $path . '.lr4bak';
}

function ensureBackup(string $path): void
{
    $backup = backupPath($path);
    if (!file_exists($backup)) {
        if (!copy($path, $backup)) {
            throw new RuntimeException("Не удалось создать резервную копию: {$backup}");
        }
    }
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

function markerStart(string $mode): string
{
    return "/* LR4_ATTACK_START: {$mode} */";
}

function markerEnd(string $mode): string
{
    return "/* LR4_ATTACK_END: {$mode} */";
}

function alreadyInjected(string $content, string $mode): bool
{
    return strpos($content, markerStart($mode)) !== false;
}

function injectPhpAtTop(string $path, string $mode, string $phpCode): void
{
    ensureFileExists($path);
    ensureBackup($path);

    $content = readFileSafe($path);
    if (alreadyInjected($content, $mode)) {
        echo "[SKIP] {$mode} уже внедрен в {$path}\n";
        return;
    }

    $block =
        "<?php\n" .
        markerStart($mode) . "\n" .
        $phpCode . "\n" .
        markerEnd($mode) . "\n" .
        "?>\n";

    writeFileSafe($path, $block . $content);
    echo "[OK] {$mode} внедрен в {$path}\n";
}

function injectBeforeBody(string $path, string $mode, string $htmlCode): void
{
    ensureFileExists($path);
    ensureBackup($path);

    $content = readFileSafe($path);
    if (alreadyInjected($content, $mode)) {
        echo "[SKIP] {$mode} уже внедрен в {$path}\n";
        return;
    }

    $block =
        "\n<!-- " . markerStart($mode) . " -->\n" .
        $htmlCode . "\n" .
        "<!-- " . markerEnd($mode) . " -->\n";

    if (stripos($content, '</body>') !== false) {
        $content = preg_replace('~</body>~i', $block . '</body>', $content, 1);
    } else {
        $content .= $block;
    }

    writeFileSafe($path, $content);
    echo "[OK] {$mode} внедрен в {$path}\n";
}

function restoreAll(array $targets, string $baseDir): void
{
    $seen = [];
    foreach ($targets as $relative) {
        $path = fullPath($baseDir, $relative);
        if (isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;

        $backup = backupPath($path);
        if (file_exists($backup)) {
            if (!copy($backup, $path)) {
                throw new RuntimeException("Не удалось восстановить {$path}");
            }
            echo "[RESTORE] {$path}\n";
        } else {
            echo "[NO BACKUP] {$path}\n";
        }
    }
}

function printStatus(array $targets, string $baseDir): void
{
    $seen = [];
    foreach ($targets as $mode => $relative) {
        $path = fullPath($baseDir, $relative);
        if (!is_file($path)) {
            echo "[MISS] {$mode} => {$relative}\n";
            continue;
        }

        if (!isset($seen[$path])) {
            $seen[$path] = readFileSafe($path);
        }

        $has = alreadyInjected($seen[$path], $mode) ? 'YES' : 'NO';
        $bak = file_exists(backupPath($path)) ? 'YES' : 'NO';
        echo "[STATUS] {$mode} | file={$relative} | injected={$has} | backup={$bak}\n";
    }
}

function applyMode(string $mode, array $targets, string $baseDir): void
{
    if (!isset($targets[$mode])) {
        throw new RuntimeException("Неизвестный режим: {$mode}");
    }

    $path = fullPath($baseDir, $targets[$mode]);

    switch ($mode) {
        case 'f1':
            injectPhpAtTop($path, $mode, <<<'PHP'
$__lr4_sig = '5uIo_py_' . substr(md5(__FILE__), 0, 6);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && strpos($__lr4_sig, '5uIo_py_') === 0) {
    http_response_code(503);
    echo '<h2 style="font-family:sans-serif;padding:24px">Сервис авторизации временно недоступен</h2>';
    exit;
}
PHP);
            break;

        case 'f2':
            injectPhpAtTop($path, $mode, <<<'PHP'
$__lr4_gate = substr(sha1(__FILE__), 0, 8);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && strlen($__lr4_gate) === 8) {
    echo '<h2 style="font-family:sans-serif;padding:24px">Регистрация принята в обработку. Повторите позже.</h2>';
    exit;
}
PHP);
            break;

        case 'f3':
            injectPhpAtTop($path, $mode, <<<'PHP'
$__lr4_mask = 'img_' . substr(hash('crc32b', __FILE__), 0, 5);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_FILES) && strpos($__lr4_mask, 'img_') === 0) {
    echo '<h2 style="font-family:sans-serif;padding:24px">Загрузка изображения временно заблокирована</h2>';
    exit;
}
PHP);
            break;

        case 'r1':
            injectPhpAtTop($path, $mode, <<<'PHP'
$__lr4_route = 'jump_' . substr(md5(__FILE__), 0, 4);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && strpos($__lr4_route, 'jump_') === 0) {
    header('Location: ../fun/jokes.php?from=auth');
    exit;
}
PHP);
            break;

        case 'r2':
            injectPhpAtTop($path, $mode, <<<'PHP'
$__lr4_route = 'nav_' . substr(sha1(__FILE__), 0, 4);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && strpos($__lr4_route, 'nav_') === 0) {
    header('Location: ../info/about.php?from=register');
    exit;
}
PHP);
            break;

        case 'r3':
            injectBeforeBody($path, $mode, <<<'HTML'
<script>
(function () {
    var lr4Tag = 'js_route_gallery';
    if (lr4Tag.length > 5) {
        window.location.href = '../info/faq.php?from=gallery';
    }
})();
</script>
HTML);
            break;
    }
}

try {
    switch ($mode) {
        case 'status':
            printStatus($targets, $baseDir);
            break;

        case 'restore':
            restoreAll($targets, $baseDir);
            break;

        case 'all':
            foreach (['f1', 'f2', 'f3', 'r1', 'r2', 'r3'] as $m) {
                applyMode($m, $targets, $baseDir);
            }
            break;

        default:
            applyMode($mode, $targets, $baseDir);
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}