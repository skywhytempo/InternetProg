<?php
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name !== '' && $message !== '') {
        $success = 'Сообщение успешно отправлено (демо-режим).';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Контакты</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-brand">Лаба №4</div>
    <a href="../index.php">Главная</a>
    <a href="../account/register.php">Регистрация</a>
    <a href="../account/auth.php">Вход</a>
    <a href="../account/gallery.php">Галерея</a>
    <a href="../account/profile.php">Профиль</a>
    <a href="../fun/jokes.php">Анекдоты</a>
    <a href="../fun/quotes.php">Цитаты</a>
    <a href="../fun/facts.php">Факты</a>
    <a href="../info/about.php">О сайте</a>
    <a href="../info/contacts.php">Контакты</a>
    <a href="../info/faq.php">FAQ</a>
</nav>

<main>
    <div class="form-card">
        <h2>Обратная связь</h2>

        <?php if ($success !== ''): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="name">Ваше имя</label>
                <input type="text" name="name" id="name" required>
            </div>

            <div class="form-group">
                <label for="message">Сообщение</label>
                <textarea name="message" id="message" required></textarea>
            </div>

            <button type="submit">Отправить</button>
        </form>
    </div>
</main>
</body>
</html>