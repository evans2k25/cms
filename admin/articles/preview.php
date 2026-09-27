<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT
        a.*,
        c.name AS category_name,
        u.username,
        u.first_name,
        u.last_name
    FROM articles a
    LEFT JOIN categories c
        ON c.id = a.category_id
    INNER JOIN users u
        ON u.id = a.user_id
    WHERE a.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    exit('Article introuvable.');
}

$authorName = trim(
    ($article['first_name'] ?? '')
    . ' '
    . ($article['last_name'] ?? '')
);

if ($authorName === '') {
    $authorName = $article['username'];
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        Aperçu -
        <?= htmlspecialchars($article['title']) ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    body {
        background: #f5f7fb;
        color: #212529;
    }

    .preview-bar {
        background: #212529;
        color: white;
        padding: 12px 20px;
    }

    .article-container {
        max-width: 900px;
        margin: 50px auto;
    }

    .article-content {
        background: white;
        padding: 45px;
        border-radius: 18px;
        box-shadow: 0 5px 30px rgba(0, 0, 0, .07);
    }

    .article-title {
        font-size: clamp(2rem, 5vw, 3.5rem);
        font-weight: 800;
        line-height: 1.1;
    }

    .article-excerpt {
        font-size: 1.2rem;
        color: #6c757d;
    }

    .article-image {
        width: 100%;
        max-height: 500px;
        object-fit: cover;
        border-radius: 15px;
    }

    .content {
        font-size: 1.1rem;
        line-height: 1.8;
    }

    .content img {
        max-width: 100%;
        height: auto;
    }

    .content blockquote {
        border-left: 4px solid #0d6efd;
        padding-left: 20px;
        color: #666;
    }
    </style>

</head>

<body>

    <div class="preview-bar">

        <div class="container-fluid d-flex justify-content-between align-items-center">

            <div>

                <i class="bi bi-eye"></i>

                Mode aperçu

                <span class="badge bg-warning text-dark ms-2">

                    <?= htmlspecialchars($article['status']) ?>

                </span>

            </div>

            <div>

                <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-light">

                    <i class="bi bi-pencil"></i>

                    Modifier

                </a>

                <a href="index.php" class="btn btn-sm btn-outline-light">

                    Retour

                </a>

            </div>

        </div>

    </div>


    <main class="article-container">

        <article class="article-content">

            <?php if ($article['category_name']): ?>

            <div class="mb-3">

                <span class="badge bg-primary">

                    <?= htmlspecialchars($article['category_name']) ?>

                </span>

            </div>

            <?php endif; ?>


            <h1 class="article-title mb-4">

                <?= htmlspecialchars($article['title']) ?>

            </h1>


            <?php if ($article['excerpt']): ?>

            <p class="article-excerpt mb-4">

                <?= nl2br(htmlspecialchars($article['excerpt'])) ?>

            </p>

            <?php endif; ?>


            <div class="text-muted mb-4">

                <i class="bi bi-person"></i>

                <?= htmlspecialchars($authorName) ?>

                &nbsp;&nbsp;

                <i class="bi bi-calendar"></i>

                <?= date(
    'd/m/Y à H:i',
    strtotime($article['created_at'])
) ?>

            </div>


            <?php if ($article['featured_image']): ?>

            <img src="<?= BASE_URL ?>/public/<?= htmlspecialchars($article['featured_image']) ?>"
                class="article-image mb-5" alt="<?= htmlspecialchars($article['title']) ?>">

            <?php endif; ?>


            <div class="content">

                <?= $article['content'] ?>

            </div>

        </article>

    </main>

</body>

</html>