<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$user = currentUser();

$articlesCount = $db
    ->query("SELECT COUNT(*) FROM articles")
    ->fetchColumn();

$publishedCount = $db
    ->query(
        "SELECT COUNT(*)
         FROM articles
         WHERE status = 'published'"
    )
    ->fetchColumn();

$draftCount = $db
    ->query(
        "SELECT COUNT(*)
         FROM articles
         WHERE status = 'draft'"
    )
    ->fetchColumn();

$usersCount = $db
    ->query("SELECT COUNT(*) FROM users")
    ->fetchColumn();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard — <?= htmlspecialchars(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark">

        <div class="container-fluid">

            <a class="navbar-brand" href="dashboard.php">
                <?= htmlspecialchars(APP_NAME) ?>
            </a>

            <div class="d-flex align-items-center gap-3">

                <span class="text-white">
                    <?= htmlspecialchars($user['username']) ?>
                </span>

                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    Déconnexion
                </a>

            </div>

        </div>

    </nav>

    <div class="container py-5">

        <h1 class="mb-1">
            Tableau de bord
        </h1>

        <p class="text-muted mb-4">
            Bienvenue,
            <?= htmlspecialchars($user['username']) ?>.
        </p>

        <div class="row g-4">

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Articles
                        </h6>

                        <h2>
                            <?= $articlesCount ?>
                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Publiés
                        </h6>

                        <h2>
                            <?= $publishedCount ?>
                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Brouillons
                        </h6>

                        <h2>
                            <?= $draftCount ?>
                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Utilisateurs
                        </h6>

                        <h2>
                            <?= $usersCount ?>
                        </h2>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>