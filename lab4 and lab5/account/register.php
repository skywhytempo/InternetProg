<?php

$login = $_POST["login"] ?? '';
$pass = $_POST["password"] ?? '';
$file = file_get_contents("users.txt");

$users = explode("\n", trim($file));

$userExists = false;

if ($login !== '' && $pass !== ''){
    foreach($users as $user){

        if ($user === '') continue;

        $user_login = explode(":", $user)[0];

        if ($user_login === $login){
            $userExists = true;
            break;
        }
    }


    if ($userExists){
        $error = "ФСБ не одобряет ваше имя";
    }
    else{
        $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
        $new_user = $login.":".$hashedPass."\n";

        file_put_contents("users.txt", $new_user, FILE_APPEND | LOCK_EX);

        header("Location: auth.php");
        exit;

    }
}


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav class="navbar">
    <span class="nav-brand">Лаба №4</span>
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
        <h2>Регистрация</h2>

        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login"
                       placeholder="Придумайте логин" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password"
                       placeholder="Придумайте пароль" required>
            </div>
            <button type="submit">Зарегистрироваться</button>
        </form>

        <p class="form-link">
            Уже есть аккаунт? <a href="auth.php">Войти</a>
        </p>
    </div>
</main>

</body>
</html>
