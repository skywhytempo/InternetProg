<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лаба 4 — Главная</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-brand">Лаба №4</div>
    <a href="index.php">Главная</a>
    <a href="account/register.php">Регистрация</a>
    <a href="account/auth.php">Вход</a>
    <a href="account/gallery.php">Галерея</a>
    <a href="account/profile.php">Профиль</a>
    <a href="fun/jokes.php">Анекдоты</a>
    <a href="fun/quotes.php">Цитаты</a>
    <a href="fun/facts.php">Факты</a>
    <a href="info/about.php">О сайте</a>
    <a href="info/contacts.php">Контакты</a>
    <a href="info/faq.php">FAQ</a>
</nav>

<main>
    <section class="gallery-page">
        <h2>Главная страница проекта</h2>
        <p class="info-text">
            Это расширенная версия проекта для ЛР4. Здесь собраны старые страницы
            авторизации и галереи, а также несколько новых простых страниц.
        </p>

        <div class="page-grid">
            <div class="page-card">
                <h3>Аккаунт</h3>
                <p>Регистрация, вход, профиль и галерея изображений.</p>
                <a href="account/register.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>Анекдоты</h3>
                <p>Нажми кнопку и получи случайный анекдот.</p>
                <a href="fun/jokes.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>Цитаты</h3>
                <p>Простая страница со случайной цитатой для настроения.</p>
                <a href="fun/quotes.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>Факты</h3>
                <p>Несколько коротких фактов по вебу и программированию.</p>
                <a href="fun/facts.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>О сайте</h3>
                <p>Краткое описание проекта и его структуры.</p>
                <a href="info/about.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>Контакты</h3>
                <p>Форма обратной связи как простая PHP-болванка.</p>
                <a href="info/contacts.php">Открыть</a>
            </div>

            <div class="page-card">
                <h3>FAQ</h3>
                <p>Небольшой список популярных вопросов и ответов.</p>
                <a href="info/faq.php">Открыть</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>