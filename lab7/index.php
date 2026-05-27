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



header("Content-Type: application/json; charset=utf-8");

define("DB_FILE", __DIR__ . "/maillist.json");
define('DBLIST_FILE', __DIR__ . '/dblist.json');

// Берём ключ из переменной окружения
define('YANDEX_API_KEY', getenv('YANDEX_API_KEY') ?: '');
define('YANDEX_FOLDER_ID', getenv('YANDEX_FOLDER_ID') ?: '');
define('YANDEX_SEARCH_URL', 'https://searchapi.api.cloud.yandex.net/v2/web/search');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
try {

    route($method, $uri);

} catch (Exception $e) {

    sendError(
        $e->getMessage(),
        $e->getCode() ?: 500
    );

}

// Переключение между функциями
function route($method, $uri){
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

    //Работа с Яндексом
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

//Методы API

// GET /getMailList
function getMailList(){
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
    $mail = getJsonInput();

    validateMail($mail);

    $id = addNewMailToDB($mail);

    sendJson([
        "status" => "success",
        "id" => "mail_" . $id
    ]);
}

//DELETE /deleteMail/{mail_id}

function deleteMail($mailId){
    deleteMailFromDB($mailId);

    sendJson([
        "status" => "success",
        "deleted" => $mailId
    ]);
}


//PUT и POST /addMails

function addMails(){

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Получаем данные в зависимости от типа запроса
    if (strpos($contentType, 'application/json') !== false) {
        $mails = getJsonInput();
    } else {
        // Если это форма браузера, берем массив писем из $_POST
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

//Методы для работы с Яндексом

// PUT /setDB
function setDB(){
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
    $data = getDBList();

    sendJson([
        "databases" => $data['body'],
        "count" => count($data['body'])
    ]);
}

// GET /getDBStatistic
function getDBStatistic(){
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
    // небольшой таймаут чтобы не висело вечно
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        // что-то пошло не так с сетью
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

    // Yandex Search API отдаёт XML внутри JSON обёртки
    // поле rawData содержит base64 закодированный XML
    $json = json_decode($response, true);

    if (!$json || !isset($json['rawData'])) {
        sendJson([
            "error" => "Invalid Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    // декодируем base64 -> XML
    $xml = base64_decode($json['rawData']);

    if (!$xml) {
        sendJson([
            "error" => "Failed to decode Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    // парсим XML
    $xmlObj = simplexml_load_string($xml);

    if (!$xmlObj) {
        sendJson([
            "error" => "Failed to parse Yandex API response",
            "code" => 500,
            "response" => $response
        ]);
        return -1;
    }

    // достаём количество результатов из XML
    // путь: response -> found-human-readable или found[@priority="all"]
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

//Работа с БД

function getAllData(){
    if (!file_exists(DB_FILE)) {
        sendError("Database file not found", 500);
    }

    return json_decode(
        file_get_contents(DB_FILE),
        true
    );
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
            stripos(
                $mail['subject'],
                $filter['subject']
            ) !== false
        ) {
            $result[$key] = $mail;
        }
    }

    return $result;
}

function sendJson(array $data){
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
?>