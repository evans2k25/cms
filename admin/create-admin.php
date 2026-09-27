<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

$database = new Database();
$db = $database->connect();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {

        $message = 'Tous les champs sont obligatoires.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Adresse email invalide.';

    } elseif (strlen($password) < 8) {

        $message = 'Le mot de passe doit contenir au moins 8 caractères.';

    } else {

        $check = $db->prepare(
            "SELECT id FROM users
             WHERE username = :username
             OR email = :email
             LIMIT 1"
        );

        $check->execute([
            ':username' => $username,
            ':email' => $email
        ]);

        if ($check->fetch()) {

            $message = 'Ce nom d’utilisateur ou cet email existe déjà.';

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $db->prepare(
                "INSERT INTO users
                (
                    username,
                    email,
                    password,
                    role,
                    status
                )
                VALUES
                (
                    :username,
                    :email,
                    :password,
                    'super_admin',
                    'active'
                )"
            );

            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashedPassword
            ]);

            $message = 'Super Admin créé avec succès.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Créer le Super Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-md-6 col-lg-5">

                <div class="card shadow-sm border-0">

                    <div class="card-body p-4">

                        <h2 class="text-center mb-4">
                            Créer le Super Admin
                        </h2>

                        <?php if ($message): ?>

                        <div class="alert alert-info">
                            <?= htmlspecialchars($message) ?>
                        </div>

                        <?php endif; ?>

                        <form method="POST">

                            <div class="mb-3">

                                <label class="form-label">
                                    Nom d'utilisateur
                                </label>

                                <input type="text" name="username" class="form-control" required>

                            </div>

                            <div class="mb-3">

                                <label class="form-label">
                                    Email
                                </label>

                                <input type="email" name="email" class="form-control" required>

                            </div>

                            <div class="mb-3">

                                <label class="form-label">
                                    Mot de passe
                                </label>

                                <input type="password" name="password" class="form-control" minlength="8" required>

                            </div>

                            <button type="submit" class="btn btn-dark w-100">
                                Créer le compte
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>