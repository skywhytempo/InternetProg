
<?php

header("Content-Type: application/json; charset=utf-8");

define("DB_FILE", __DIR__ . "/maillist.json");

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

/**
 * Роутинг
 */
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