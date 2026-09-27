<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
$db = $database->connect();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {

        $error = 'Veuillez remplir tous les champs.';

    } else {

        $stmt = $db->prepare(
            "SELECT *
             FROM users
             WHERE username = :username
                OR email = :email
             LIMIT 1"
        );

        $stmt->execute([
            ':username' => $login,
            ':email' => $login
        ]);

        $user = $stmt->fetch();

        if (
            $user &&
            $user['status'] === 'active' &&
            password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role']
            ];

            header('Location: dashboard.php');
            exit;

        } else {

            $error = 'Identifiants incorrects.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion — <?= htmlspecialchars(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-dark">

    <div class="container">

        <div class="row justify-content-center align-items-center" style="min-height:100vh">

            <div class="col-md-6 col-lg-4">

                <div class="card shadow-lg border-0">

                    <div class="card-body p-4">

                        <div class="text-center mb-4">

                            <h2>
                                <?= htmlspecialchars(APP_NAME) ?>
                            </h2>

                            <p class="text-muted mb-0">
                                Administration
                            </p>

                        </div>

                        <?php if ($error): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>

                        <?php endif; ?>

                        <form method="POST">

                            <div class="mb-3">

                                <label class="form-label">
                                    Email ou nom d'utilisateur
                                </label>

                                <input type="text" name="login" class="form-control" autocomplete="username" required>

                            </div>

                            <div class="mb-3">

                                <label class="form-label">
                                    Mot de passe
                                </label>

                                <input type="password" name="password" class="form-control"
                                    autocomplete="current-password" required>

                            </div>

                            <button type="submit" class="btn btn-dark w-100">
                                Se connecter
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>