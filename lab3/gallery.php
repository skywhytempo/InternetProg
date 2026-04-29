<?php

if ($_COOKIE["user"] == "") {
    header('Location: auth.php');
    exit;
}

$error = "";
$success = "";

// папки для картинок
$imagesDir = "img/";
$thumbsDir = "img/thumbs/";

if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir);
}

$forbidden = ['script','http','SELECT','UNION','UPDATE','exe','exec','INSERT','tmp'];

// ---------------- УДАЛЕНИЕ КАРТИНОК ----------------
if (isset($_POST["delete"])) {

    $delName = basename($_POST["delete"]); // защита от ../

    if (file_exists($imagesDir.$delName)) {
        unlink($imagesDir.$delName);
    }
    if (file_exists($thumbsDir.$delName)) {
        unlink($thumbsDir.$delName);
    }
}

// ---------------- ЗАГРУЗКА НОВЫХ КАРТИНОК ----------------
if (isset($_FILES["image"])) {

    $title = strip_tags(trim($_POST["title"]));

    // проверка названия
    if ($title == "") {
        $error = "ФСБ не одобрило ваше изображение. Название не может быть пустым";
    } else {
        foreach ($forbidden as $forbiddenWord) {
            if (stripos($title, $forbiddenWord) !== false) {
                $error = "ФСБ не одобрило ваше изображение. Название содержит запрещенные слова";
                break;
            }
        }
    }

    // проверка файла
    if ($error == "") {

        $file = $_FILES["image"];

        if ($file["error"] != 0) {
            $error = "ФСБ не одобрило ваше изображение. Ошибка загрузки файла";
        } else if ($file["size"] > 2*1024*1024) {
            $error = "ФСБ не одобрило ваше изображение. Файл должен быть меньше 2 МБ";
        } else {

            $tmpName  = $file["tmp_name"];
            $origName = $file["name"];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // ВАЖНО: здесь &&, а не ||
            if ($ext != "jpg" && $ext != "jpeg" && $ext != "png" && $ext != "jfif" && $ext != "gif" && $ext != "webp") {
                $error = "ФСБ не одобрило ваше изображение. Разрешены только jpg, png, jfif, webp и gif";
            } else {

                $info = getimagesize($tmpName);
                if ($info === false) {
                    $error = "ФСБ не одобрило ваше изображение. Файл не является изображением";
                } else {

                    $width  = $info[0];
                    $height = $info[1];

                    $maxSide = 800;
                    if (max($height, $width) > $maxSide) {
                        $error = "ФСБ не одобрило ваше изображение. Изображение слишком большое (макс. 800 пикселей по большей стороне)";
                    } else {

                        $safeName = preg_replace('/[^a-zA-Z0-9_\-а-яА-ЯёЁ]/u', '_', $title);
                        $fileName = $_COOKIE["user"]."_".$safeName.".".$ext;
                        $fullPath = $imagesDir.$fileName;

                        // если файл с таким именем уже существует — добавляем timestamp
                        if (file_exists($fullPath)) {
                            $fileName = $safeName."_".time().".".$ext;
                            $fullPath  = $imagesDir.$fileName;
                            $thumbPath = $thumbsDir.$fileName;
                        } else {
                            $thumbPath = $thumbsDir.$fileName;
                        }

                        // Просто сохраняем оригинал
                        if (!move_uploaded_file($tmpName, $fullPath)) {
                            $error = "ФСБ не одобрило ваше изображение. Не удалось сохранить файл";
                        }

                        // создаём миниатюру, только если нет ошибок
                        if ($error == "") {

                            if ($ext == "jpg" || $ext == "jpeg" || $ext == "jfif") {
                                $src = imagecreatefromjpeg($fullPath);
                            } else if ($ext == "png"){ // png
                                $src = imagecreatefrompng($fullPath);
                                                       
                            } else if ($ext == "webp"){
                                $src = imagecreatefromwebp($fullPath);
                            } // webp
                            else if ($ext == "gif"){
                                $src = imagecreatefromgif($fullPath);
                            }
                                // gif

                            $width  = imagesx($src);
                            $height = imagesy($src);

                            $thumbW = 150;
                            $scale  = $thumbW / $width;
                            $thumbH = (int)($height * $scale);

                            $thumb = imagecreatetruecolor($thumbW, $thumbH);
                            imagecopyresampled($thumb, $src, 0, 0, 0, 0,
                                $thumbW, $thumbH, $width, $height); 

                            if ($ext == "jpg" || $ext == "jpeg" || $ext == "jfif") {
                                imagejpeg($thumb, $thumbPath);
                            } else if ($ext == "png"){
                                imagepng($thumb, $thumbPath);
                            } else if ($ext == "webp"){
                                imagewebp($thumb, $thumbPath);
                            } else if ($ext == "gif"){
                                imagegif($thumb, $thumbPath);
                            }

                            $success = "Ваше изображение было одобрено ФСБ";
                        }
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Галерея</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <span class="nav-brand">Лаба №3</span>
    <a href="gallery.php">Галерея</a>
    <a href="auth.php">Выйти</a>
</nav>

<div class="gallery-page">

    <div class="upload-card">
    <h2 class = "hello">Добро пожаловать, <?= htmlspecialchars($_COOKIE["user"]) ?>!</h2>
        <h3>Загрузить изображение</h3>

        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="gallery.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Название изображения</label>
                <input type="text" id="title" name="title"
                       placeholder="Введите название" required>
                <small>Без HTML-тегов и запрещённых слов</small>
            </div>
            <div class="form-group">
                <label for="image">Файл изображения</label>
                <input type="file" id="image" name="image" accept="image/*" required>
                <small>Макс. 2 МБ, не более 800px по большей стороне</small>
            </div>
            <button type="submit">Загрузить</button>
        </form>
    </div>

    <h2>Мои изображения</h2>

    <div class="gallery-grid">
    <?php
    $currentUser = $_COOKIE["user"];
    $files = glob($imagesDir."*.{jpg,jpeg,png,jfif,webp,gif}", GLOB_BRACE);
    foreach ($files as $file) {
        $name  = basename($file);
        if (strpos($name, $currentUser."_") !== 0) {
            continue;
        }
    ?>
        <div class="gallery-item">
            <a href="img/<?= htmlspecialchars($name) ?>" target="_blank">
                <img src="img/thumbs<?= '/' . htmlspecialchars($name) ?>" alt="">
            </a>
            <p><?= htmlspecialchars(explode('_', $name, 2)[1] ?? $name) ?></p>
            <form method="POST" action="gallery.php">
                <input type="hidden" name="delete" value="<?= htmlspecialchars($name) ?>">
                <button type="submit">Удалить</button>
            </form>
        </div>
    <?php } ?>
</div>
</div>

</body>
</html>