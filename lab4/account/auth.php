<?php

$login = $_POST["login"] ?? '';
$pass = $_POST["password"] ?? '';
$file = file_get_contents("users.txt");

$users = explode("\n", trim($file));

$userFound = false;

if ($login !== '' && $pass !== ''){
    
    foreach($users as $user){

        if ($user === '') continue;

        list($user_login, $user_pass) = explode(":", $user, 2);

    


        
        if ($user_login === $login && password_verify($pass, $user_pass)){
            $userFound = true;
            echo "Пользователь найден: $user_login\n";
            break;
        }
    }


    if ($userFound){
        setcookie('user', $login, time() + 60*60*24*30, "/");
        header('Location: gallery.php');
        exit;
    }
    else{
        $error = "ФСБ не пропустит вас на сайт";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Авторизация</title>
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
        <h2>Авторизация</h2>

        <?php if (!empty($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login"
                       placeholder="Введите логин" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password"
                       placeholder="Введите пароль" required>
            </div>
            <button type="submit">Войти</button>
        </form>

        <p class="form-link">
            Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
        </p>
    </div>
</main>

</body>
</html>
`