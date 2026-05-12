<?php
$user = $_COOKIE['user'] ?? 'Гость';
$isAuth = isset($_COOKIE['user']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль</title>
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
        <h2>Профиль пользователя</h2>

        <?php if ($isAuth): ?>
            <div class="message success">Вы вошли как: <?php echo htmlspecialchars($user); ?></div>
            <p class="info-text">Это простая страница профиля. Здесь можно хранить имя пользователя и статус входа.</p>
            <a class="button-link" href="../account/gallery.php">Перейти в галерею</a>
        <?php else: ?>
            <div class="message error">Пользователь не авторизован.</div>
            <p class="info-text">Сначала войдите в аккаунт через страницу авторизации.</p>
            <a class="button-link" href="../account/auth.php">Перейти ко входу</a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>