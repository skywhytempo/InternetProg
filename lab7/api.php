<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        return; // если нет файла - ничего не делаем
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // пропускаем комментарии
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // убираем кавычки если есть
        $value = trim($value, '"\'');

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/../.env');

header('Content-Type: text/html; charset=utf-8');

const DB_FILE = __DIR__ . '/maillist.json';
const DBLIST_FILE = __DIR__ . '/dblist.json';

// Берём ключ из переменной окружения
define('YANDEX_API_KEY', getenv('YANDEX_API_KEY') ?: '');
define('YANDEX_FOLDER_ID', getenv('YANDEX_FOLDER_ID') ?: '');
define('YANDEX_SEARCH_URL', 'https://searchapi.api.cloud.yandex.net/v2/web/search');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    route($method, $uri);
} catch (Throwable $e) {
    sendError(
        $e->getMessage(),
        $e->getCode() ?: 500
    );
}

// Переключение между функциями
function route($method, $uri){

    if (
        ($uri === '/' || $uri === '/api.php') &&
        ($method === 'GET' || $method === 'POST')
    ) {
        renderHomePage();
        return;
    }


    if ($uri === '/' && ($method === 'GET' || $method === 'POST')) {
        renderHomePage();
        return;
    }

    if ($method === 'GET' && $uri === '/getMailList') {
        getMailList();
        return;
    }

    if ($method === 'PUT' && $uri === '/addMail') {
        addMail();
        return;
    }

    if (
        $method === 'DELETE' &&
        preg_match('#^/deleteMail/(mail_\d+)$#', $uri, $matches)
    ) {
        deleteMail($matches[1]);
        return;
    }

    if (($method === 'PUT' || $method === 'POST') && $uri === '/addMails') {
        addMails();
        return;
    }

    // Работа с Яндексом
    if ($method === 'PUT' && $uri === '/setDB') {
        setDB();
        return;
    }

    if ($method === 'DELETE' && preg_match('#^/delDB/(.+)$#', $uri, $matches)) {
        delDB(urldecode($matches[1]));
        return;
    }

    if ($method === 'GET' && $uri === '/viewDB') {
        viewDB();
        return;
    }

    if ($method === 'GET' && $uri === '/getDBStatistic') {
        getDBStatistic();
        return;
    }

    sendError("Route not found", 404);
}

// HTML интерфейс
function renderHomePage(): void
{
    $message = '';
    $messageType = 'success';
    $filterValue = '';
    $dbStats = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['ui_action'] ?? '';

        try {
            if ($action === 'add_mail') {
                $mail = [
                    'datetime' => trim($_POST['datetime'] ?? ''),
                    'subject' => trim($_POST['subject'] ?? ''),
                    'from' => trim($_POST['from'] ?? ''),
                    'message' => trim($_POST['message'] ?? '')
                ];

                validateMailForm($mail);
                $id = addNewMailToDB($mail);

                $message = 'Письмо успешно добавлено: mail_' . $id;
                $messageType = 'success';
            }

            if ($action === 'delete_mail') {
                $mailId = trim($_POST['mail_id'] ?? '');
                deleteMailFromDB($mailId);

                $message = 'Письмо удалено: ' . $mailId;
                $messageType = 'success';
            }

            if ($action === 'filter_mail') {
                $filterValue = trim($_POST['filter_subject'] ?? '');

                if ($filterValue === '') {
                    $message = 'Фильтр пустой, показаны все письма.';
                } else {
                    $message = 'Фильтр применён.';
                }

                $messageType = 'success';
            }

            if ($action === 'add_db') {
                $dbName = trim($_POST['db_name'] ?? '');

                if ($dbName === '') {
                    throw new Exception('Поле названия БД обязательно');
                }

                $data = getDBList();

                foreach ($data['body'] as $name) {
                    if ($name === $dbName) {
                        throw new Exception('База данных уже существует');
                    }
                }

                $increment = $data['header']['increment'] + 1;
                $id = 'db_' . $increment;

                $data['header']['increment'] = $increment;
                $data['body'][$id] = $dbName;

                saveDBList($data);

                $message = 'База данных добавлена: ' . $dbName;
                $messageType = 'success';
            }

            if ($action === 'delete_db') {
                $dbId = trim($_POST['db_id'] ?? '');
                $data = getDBList();

                if (!isset($data['body'][$dbId])) {
                    throw new Exception("БД с id '$dbId' не найдена");
                }

                unset($data['body'][$dbId]);
                saveDBList($data);

                $message = 'База данных удалена: ' . $dbId;
                $messageType = 'success';
            }

            if ($action === 'load_stats') {
                if (YANDEX_API_KEY === '') {
                    throw new Exception('YANDEX_API_KEY не задан');
                }

                $data = getDBList();

                if (empty($data['body'])) {
                    throw new Exception('Список БД пустой');
                }

                foreach ($data['body'] as $id => $dbName) {
                    $dbStats[$id] = [
                        'name' => $dbName,
                        'count' => searchYandex($dbName)
                    ];
                }

                $message = 'Статистика успешно загружена.';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }

    $mailData = getAllData();
    $allMails = $mailData['body'] ?? [];

    if ($filterValue !== '') {
        $mails = makeFilter(['subject' => $filterValue]);
    } else {
        $mails = $allMails;
    }

    $dbData = getDBList();
    $dbList = $dbData['body'] ?? [];

    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Лаба №5</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <nav class="navbar">
            <div class="nav-brand">Лаба №5</div>
            <a href="#mail-form">Добавить письмо</a>
            <a href="#mail-filter">Фильтр</a>
            <a href="#mail-list">Письма</a>
            <a href="#db-section">Базы данных</a>
        </nav>

        <main>
            <div class="gallery-page">
                <h2>Управление письмами и БД</h2>

                <?php if ($message !== ''): ?>
                    <div class="message <?php echo h($messageType); ?>">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <div class="page-grid">
                    <section class="upload-card" id="mail-form">
                        <h3>Добавить письмо</h3>
                        <form method="post">
                            <input type="hidden" name="ui_action" value="add_mail">

                            <div class="form-group">
                                <label for="datetime">Дата и время</label>
                                <input type="text" id="datetime" name="datetime" placeholder="27.05.2026 12:30">
                            </div>

                            <div class="form-group">
                                <label for="subject">Тема</label>
                                <input type="text" id="subject" name="subject" placeholder="Введите тему письма">
                            </div>

                            <div class="form-group">
                                <label for="from">От кого</label>
                                <input type="text" id="from" name="from" placeholder="mail@example.com">
                            </div>

                            <div class="form-group">
                                <label for="message">Сообщение</label>
                                <textarea id="message" name="message" placeholder="Введите текст письма"></textarea>
                            </div>

                            <button type="submit">Добавить письмо</button>
                        </form>
                    </section>

                    <section class="upload-card" id="mail-filter">
                        <h3>Фильтр писем</h3>
                        <form method="post">
                            <input type="hidden" name="ui_action" value="filter_mail">

                            <div class="form-group">
                                <label for="filter_subject">Тема письма</label>
                                <input
                                    type="text"
                                    id="filter_subject"
                                    name="filter_subject"
                                    value="<?php echo h($filterValue); ?>"
                                    placeholder="Например: отчёт"
                                >
                                <small>Поиск выполняется по вхождению в тему письма.</small>
                            </div>

                            <button type="submit">Применить фильтр</button>
                        </form>
                    </section>
                </div>

                <section class="upload-card" id="mail-list">
                    <h3>Список писем</h3>

                    <?php if (empty($mails)): ?>
                        <p class="center-note">Письма не найдены.</p>
                    <?php else: ?>
                        <div class="page-grid">
                            <?php foreach (array_reverse($mails, true) as $mailId => $mail): ?>
                                <article class="page-card">
                                    <h3><?php echo h($mail['subject'] ?? 'Без темы'); ?></h3>

                                    <p class="info-text">
                                        <strong>ID:</strong> <?php echo h($mailId); ?><br>
                                        <strong>От:</strong> <?php echo h($mail['from'] ?? ''); ?><br>
                                        <strong>Дата:</strong> <?php echo h($mail['datetime'] ?? ''); ?>
                                    </p>

                                    <div class="result-box">
                                        <?php echo nl2br(h($mail['message'] ?? '')); ?>
                                    </div>

                                    <form method="post">
                                        <input type="hidden" name="ui_action" value="delete_mail">
                                        <input type="hidden" name="mail_id" value="<?php echo h($mailId); ?>">
                                        <button type="submit">Удалить письмо</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="upload-card" id="db-section">
                    <h3>Базы данных</h3>

                    <form method="post">
                        <input type="hidden" name="ui_action" value="add_db">

                        <div class="form-group">
                            <label for="db_name">Название БД</label>
                            <input type="text" id="db_name" name="db_name" placeholder="Введите название базы">
                        </div>

                        <button type="submit">Добавить БД</button>
                    </form>

                    <?php if (empty($dbList)): ?>
                        <p class="center-note">Список баз данных пуст.</p>
                    <?php else: ?>
                        <div class="page-grid">
                            <?php foreach ($dbList as $dbId => $dbName): ?>
                                <article class="page-card">
                                    <h3><?php echo h($dbName); ?></h3>
                                    <p class="info-text"><strong>ID:</strong> <?php echo h($dbId); ?></p>

                                    <form method="post">
                                        <input type="hidden" name="ui_action" value="delete_db">
                                        <input type="hidden" name="db_id" value="<?php echo h($dbId); ?>">
                                        <button type="submit">Удалить БД</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" style="margin-top: 20px;">
                        <input type="hidden" name="ui_action" value="load_stats">
                        <button type="submit">Получить статистику из Яндекса</button>
                    </form>

                    <?php if (!empty($dbStats)): ?>
                        <div class="page-grid">
                            <?php foreach ($dbStats as $dbId => $info): ?>
                                <article class="page-card">
                                    <h3><?php echo h($info['name']); ?></h3>
                                    <p class="info-text">
                                        <strong>ID:</strong> <?php echo h($dbId); ?><br>
                                        <strong>Найдено результатов:</strong> <?php echo h((string)$info['count']); ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Методы API

// GET /getMailList
function getMailList(){
    header('Content-Type: application/json; charset=utf-8');

    if(isset($_GET['action']) && $_GET['action'] === 'filter'){
        getFilterRequest();
        return;
    }

    $data = getAllData();

    sendJson($data);
}

// GET filter
function getFilterRequest(){
    if (!isset($_GET['filter'])) {
        sendError("Filter parameter missing", 400);
    }

    $filter = json_decode($_GET['filter'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError("Invalid filter JSON", 400);
    }

    $result = makeFilter($filter);
    sendJson($result);
}

// PUT /addMail
function addMail(){
    header('Content-Type: application/json; charset=utf-8');

    $mail = getJsonInput();

    validateMail($mail);

    $id = addNewMailToDB($mail);

    sendJson([
        "status" => "success",
        "id" => "mail_" . $id
    ]);
}

// DELETE /deleteMail/{mail_id}
function deleteMail($mailId){
    header('Content-Type: application/json; charset=utf-8');

    deleteMailFromDB($mailId);

    sendJson([
        "status" => "success",
        "deleted" => $mailId
    ]);
}

// PUT и POST /addMails
function addMails(){
    header('Content-Type: application/json; charset=utf-8');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $mails = getJsonInput();
    } else {
        $mails = isset($_POST['mails']) ? array_values($_POST['mails']) : [];
    }

    if (empty($mails) || !is_array($mails) || !isset($mails[0])){
        sendError("Ожидается массив писем", 400);
    }

    foreach($mails as $mail){
        validateMail($mail);
    }

    $addedMails = addNewMailsToDB($mails);

    sendJson([
        "status" => "success",
        "added" => $addedMails
    ]);
}

// Методы для работы с Яндексом

// PUT /setDB
function setDB(){
    header('Content-Type: application/json; charset=utf-8');

    $input = getJsonInput();

    if (!isset($input['name']) || empty(trim($input['name']))) {
        sendError("Поле name обязательно", 400);
    }

    $db = trim($input['name']);
    $data = getDBList();

    foreach ($data['body'] as $id => $name) {
        if ($name === $db) {
            sendError("База данных уже существует", 409);
        }
    }

    $increment = $data['header']['increment'] + 1;
    $id = 'db_' . $increment;

    $data['header']['increment'] = $increment;
    $data['body'][$id] = $db;

    saveDBList($data);

    sendJson([
        "status" => "success",
        "added" => [
            "id" => $id,
            "name" => $db
        ]
    ]);
}

// DELETE /delDB/{id}
function delDB($dbId){
    header('Content-Type: application/json; charset=utf-8');

    $data = getDBList();

    if (!isset($data['body'][$dbId])) {
        sendError("БД с id '$dbId' не найдена", 404);
    }

    $deletedName = $data['body'][$dbId];
    unset($data['body'][$dbId]);

    saveDBList($data);

    sendJson([
        "status" => "success",
        "deleted" => [
            "id" => $dbId,
            "name" => $deletedName
        ]
    ]);
}

// GET /viewDB
function viewDB(){
    header('Content-Type: application/json; charset=utf-8');

    $data = getDBList();

    sendJson([
        "databases" => $data['body'],
        "count" => count($data['body'])
    ]);
}

// GET /getDBStatistic
function getDBStatistic(){
    header('Content-Type: application/json; charset=utf-8');

    if (YANDEX_API_KEY === '') {
        sendError("YANDEX_API_KEY не задан", 500);
    }

    $data = getDBList();

    if (empty($data['body'])) {
        sendError("Список БД пустой, сначала добавьте через /setDB", 400);
    }

    $results = [];

    foreach ($data['body'] as $id => $dbName) {
        $count = searchYandex($dbName);

        $results[$id] = [
            "name" => $dbName,
            "count" => $count
        ];
    }

    sendJson([
        "date" => date("d.m.Y"),
        "totalResults" => $results
    ]);
}

// Работа с dblist.json
function getDBList(){
    if (!file_exists(DBLIST_FILE)) {
        return [
            "header" => [
                "increment" => 0
            ],
            "body" => []
        ];
    }

    $raw = file_get_contents(DBLIST_FILE);
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return [
            "header" => [
                "increment" => 0
            ],
            "body" => []
        ];
    }

    if (!isset($data['header']) || !is_array($data['header'])) {
        $data['header'] = ["increment" => 0];
    }

    if (!isset($data['header']['increment'])) {
        $data['header']['increment'] = 0;
    }

    if (!isset($data['body']) || !is_array($data['body'])) {
        $data['body'] = [];
    }

    return $data;
}

function saveDBList($data){
    file_put_contents(
        DBLIST_FILE,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function searchYandex($query){
    $params = [
        'query' => [
            'searchType' => 'SEARCH_TYPE_RU',
            'queryText' => $query,
            'page' => 0
        ],
        'folderId' => YANDEX_FOLDER_ID,
        'responseFormat' => 'FORMAT_XML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, YANDEX_SEARCH_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Api-Key ' . YANDEX_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        sendJson([
            "error" => "Network error: " . curl_error($ch),
            "code" => 500
        ]);
    }

    if ($httpCode !== 200) {
        sendJson([
            "error" => "Yandex API error: HTTP $httpCode",
            "code" => $httpCode,
            "response" => $response
        ]);
    }

    $json = json_decode($response, true);

    if (!$json || !isset($json['rawData'])) {
        sendJson([
            "error" => "Invalid Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    $xml = base64_decode($json['rawData']);

    if (!$xml) {
        sendJson([
            "error" => "Failed to decode Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    $xmlObj = simplexml_load_string($xml);

    if (!$xmlObj) {
        sendJson([
            "error" => "Failed to parse Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    $found = $xmlObj->response->found;
    if ($found) {
        foreach ($found as $f) {
            $priority = (string)$f->attributes()->priority;
            if ($priority === 'all') {
                return (int)$f;
            }
        }
    }

    return 0;
}

// Работа с БД
function getAllData(){
    if (!file_exists(DB_FILE)) {
        return [
            "header" => [
                "increment" => 0,
                "date_update" => date("d.m.Y")
            ],
            "body" => []
        ];
    }

    $data = json_decode(file_get_contents(DB_FILE), true);

    if (!is_array($data)) {
        return [
            "header" => [
                "increment" => 0,
                "date_update" => date("d.m.Y")
            ],
            "body" => []
        ];
    }

    if (!isset($data['header']) || !is_array($data['header'])) {
        $data['header'] = [
            "increment" => 0,
            "date_update" => date("d.m.Y")
        ];
    }

    if (!isset($data['header']['increment'])) {
        $data['header']['increment'] = 0;
    }

    if (!isset($data['header']['date_update'])) {
        $data['header']['date_update'] = date("d.m.Y");
    }

    if (!isset($data['body']) || !is_array($data['body'])) {
        $data['body'] = [];
    }

    return $data;
}

function saveAllData($data): void
{
    file_put_contents(
        DB_FILE,
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        )
    );
}

function validateMail($mail){
    $required = [
        'datetime',
        'subject',
        'from',
        'message'
    ];

    foreach ($required as $field) {
        if (!isset($mail[$field])) {
            sendError("Missing field: $field", 500);
        }

        if (!is_string($mail[$field])) {
            sendError("Field must be string: $field", 500);
        }
    }

    if (!filter_var($mail['from'], FILTER_VALIDATE_EMAIL)) {
        sendError("Invalid email", 500);
    }
}

function validateMailForm(array $mail): void
{
    $required = ['datetime', 'subject', 'from', 'message'];

    foreach ($required as $field) {
        if (!isset($mail[$field]) || trim($mail[$field]) === '') {
            throw new Exception("Поле $field обязательно");
        }
    }

    if (!filter_var($mail['from'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Некорректный email");
    }
}

function addNewMailToDB($mail){
    $data = getAllData();

    $increment = $data['header']['increment'] + 1;

    $data['body']['mail_' . $increment] = $mail;

    $data['header']['increment'] = $increment;
    $data['header']['date_update'] = date("d.m.Y");

    saveAllData($data);

    return $increment;
}

function deleteMailFromDB($mailId){
    $data = getAllData();

    if (!isset($data['body'][$mailId])) {
        sendError("Письмо не найдено", 404);
    }

    unset($data['body'][$mailId]);

    $data['header']['date_update'] = date("d.m.Y");

    saveAllData($data);
}

function addNewMailsToDB($mails){
    $data = getAllData();
    $increment = $data['header']['increment'];
    $addedMails = [];

    foreach ($mails as $mail) {
        $increment++;
        $data['body']['mail_' . $increment] = $mail;
        $addedMails[] = "mail_" . $increment;
    }

    $data['header']['increment'] = $increment;
    $data['header']['date_update'] = date("d.m.Y");

    saveAllData($data);
    return $addedMails;
}

function getJsonInput(){
    $input = file_get_contents("php://input");

    $data = json_decode($input, true);

    if (!$data) {
        sendError("Invalid JSON", 400);
    }

    return $data;
}

function makeFilter(array $filter){
    $data = getAllData();
    $result = [];

    if (!isset($filter['subject'])) {
        return $result;
    }

    foreach ($data['body'] as $key => $mail) {
        if (
            isset($mail['subject']) &&
            stripos($mail['subject'], $filter['subject']) !== false
        ) {
            $result[$key] = $mail;
        }
    }

    return $result;
}

function sendJson(array $data){
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    exit;
}

function sendError(string $message, int $code){
    http_response_code($code);

    sendJson([
        "error" => $message,
        "code" => $code
    ]);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>