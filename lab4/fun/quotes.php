<?php
$quote = '';
$quotes = [
    'Маленький рабочий шаг лучше большого идеального плана.',
    'Код становится понятнее тогда, когда его не стыдно показать завтра.',
    'Сильный проект строится не из магии, а из аккуратных мелочей.',
    'Если задачу можно упростить, обычно это и есть правильное решение.',
    'Хороший разработчик не избегает ошибок, а быстро их находит и чинит.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quote = $quotes[array_rand($quotes)];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Цитаты</title>
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
        <h2>Случайная цитата</h2>
        <form method="post">
            <button type="submit">Показать цитату</button>
        </form>

        <?php if ($quote !== ''): ?>
            <div class="result-box"><?php echo htmlspecialchars($quote); ?></div>
        <?php else: ?>
            <p class="center-note">Нажмите кнопку, чтобы получить случайную цитату.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>