<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

/*
|--------------------------------------------------------------------------
| Récupération des médias
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        m.*,
        u.username
    FROM media m
    INNER JOIN users u
        ON u.id = m.user_id
    ORDER BY m.created_at DESC
");

$media = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function mediaUrl(string $path): string
{
    return BASE_URL . '/public/' . ltrim($path, '/');
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Médiathèque — <?= e(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
    body {
        background: #f5f6f8;
    }

    .media-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
        transition: .2s;
    }

    .media-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .08);
    }

    .media-preview {
        height: 210px;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .media-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .media-info {
        padding: 15px;
    }

    .filename {
        font-size: .9rem;
        font-weight: 600;
        word-break: break-word;
    }

    .meta {
        font-size: .78rem;
        color: #777;
    }
    </style>

</head>

<body>

    <nav class="navbar navbar-dark bg-dark">

        <div class="container-fluid">

            <a href="<?= ADMIN_URL ?>/dashboard.php" class="navbar-brand fw-bold">
                <i class="bi bi-newspaper"></i>
                <?= e(APP_NAME) ?>
            </a>

            <div class="d-flex align-items-center gap-2">

                <a href="<?= BASE_URL ?>/public/index.php" target="_blank" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-globe"></i>
                    Voir le site
                </a>

                <a href="<?= ADMIN_URL ?>/logout.php" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i>
                    Déconnexion
                </a>

            </div>

        </div>

    </nav>


    <main class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h1 class="h3 fw-bold mb-1">
                    Médiathèque
                </h1>

                <p class="text-muted mb-0">
                    Gérez les images utilisées dans vos articles.
                </p>

            </div>

            <a href="upload.php" class="btn btn-danger">
                <i class="bi bi-cloud-arrow-up"></i>
                Ajouter un média
            </a>

        </div>


        <?php if (isset($_GET['uploaded'])): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle"></i>

            Image envoyée avec succès.

        </div>

        <?php endif; ?>


        <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle"></i>

            Média supprimé avec succès.

        </div>

        <?php endif; ?>


        <?php if (isset($_GET['error'])): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-circle"></i>

            <?= e($_GET['error']) ?>

        </div>

        <?php endif; ?>


        <?php if (!empty($media)): ?>

        <div class="row g-4">

            <?php foreach ($media as $item): ?>

            <div class="col-xl-3 col-lg-4 col-md-6">

                <div class="media-card">

                    <div class="media-preview">

                        <?php if (
                                str_starts_with(
                                    $item['mime_type'] ?? '',
                                    'image/'
                                )
                            ): ?>

                        <img src="<?= e(
                                        mediaUrl(
                                            $item['file_path']
                                        )
                                    ) ?>" alt="<?= e($item['alt_text']) ?>">

                        <?php else: ?>

                        <i class="bi bi-file-earmark fs-1 text-secondary"></i>

                        <?php endif; ?>

                    </div>


                    <div class="media-info">

                        <div class="filename mb-2">

                            <?= e(
                                    $item['original_name']
                                    ?: $item['filename']
                                ) ?>

                        </div>


                        <div class="meta mb-3">

                            <div>
                                <i class="bi bi-file-earmark"></i>
                                <?= e($item['mime_type']) ?>
                            </div>

                            <div>
                                <i class="bi bi-person"></i>
                                <?= e($item['username']) ?>
                            </div>

                            <div>
                                <i class="bi bi-calendar3"></i>
                                <?= date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $item['created_at']
                                        )
                                    ) ?>
                            </div>

                        </div>


                        <div class="d-flex gap-2">

                            <a href="<?= e(
                                        mediaUrl(
                                            $item['file_path']
                                        )
                                    ) ?>" target="_blank" class="btn btn-outline-primary btn-sm flex-grow-1">
                                <i class="bi bi-eye"></i>
                                Voir
                            </a>


                            <a href="delete.php?id=<?= (int) $item['id'] ?>" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Supprimer définitivement ce média ?');">
                                <i class="bi bi-trash"></i>
                            </a>

                        </div>

                    </div>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

        <?php else: ?>

        <div class="text-center bg-white rounded p-5">

            <i class="bi bi-images fs-1 text-secondary"></i>

            <h3 class="mt-3">
                Aucun média
            </h3>

            <p class="text-muted">
                Votre bibliothèque multimédia est vide.
            </p>

            <a href="upload.php" class="btn btn-danger">
                <i class="bi bi-cloud-arrow-up"></i>
                Ajouter une image
            </a>

        </div>

        <?php endif; ?>

    </main>

</body>

</html>