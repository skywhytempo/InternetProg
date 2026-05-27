<?php
$fact = '';
$facts = [
    'PHP-код выполняется на сервере, а браузер получает уже готовый HTML.',
    'Cookie позволяют хранить маленькие данные о пользователе между запросами.',
    'Форма с method="post" отправляет данные не в URL, а в теле запроса.',
    'Относительные пути зависят от того, в какой папке лежит текущий файл.',
    'Даже простая страница может стать целью для внедрения вредоносного кода.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fact = $facts[array_rand($facts)];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Факты</title>
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
        <h2>Случайный факт</h2>
        <form method="post">
            <button type="submit">Показать факт</button>
        </form>

        <?php if ($fact !== ''): ?>
            <div class="result-box"><?php echo htmlspecialchars($fact); ?></div>
        <?php else: ?>
            <p class="center-note">Нажмите кнопку, чтобы вывести случайный факт.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>