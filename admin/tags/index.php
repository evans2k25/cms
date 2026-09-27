<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$stmt = $db->query("
    SELECT
        t.*,
        COUNT(at.article_id) AS articles_count
    FROM tags t
    LEFT JOIN article_tags at
        ON at.tag_id = t.id
    GROUP BY t.id
    ORDER BY t.created_at DESC
");

$tags = $stmt->fetchAll();

$user = currentUser();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tags — <?= htmlspecialchars(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark">

        <div class="container-fluid">

            <a class="navbar-brand" href="../dashboard.php">
                <?= htmlspecialchars(APP_NAME) ?>
            </a>

            <div class="d-flex align-items-center gap-3">

                <span class="text-white">
                    <?= htmlspecialchars($user['username']) ?>
                </span>

                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    Déconnexion
                </a>

            </div>

        </div>

    </nav>

    <div class="container py-5">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h1 class="mb-1">
                    Tags
                </h1>

                <p class="text-muted mb-0">
                    Organisez les sujets et thèmes de vos articles.
                </p>

            </div>

            <a href="create.php" class="btn btn-dark">
                <i class="bi bi-plus-lg"></i>
                Nouveau tag
            </a>

        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>
                                <th>Tag</th>
                                <th>Slug</th>
                                <th>Articles</th>
                                <th>Créé le</th>
                                <th class="text-end">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!$tags): ?>

                            <tr>

                                <td colspan="6" class="text-center text-muted py-5">
                                    Aucun tag disponible.
                                </td>

                            </tr>

                            <?php else: ?>

                            <?php foreach ($tags as $tag): ?>

                            <tr>

                                <td>
                                    <?= (int) $tag['id'] ?>
                                </td>

                                <td>

                                    <span class="badge rounded-pill text-bg-dark">
                                        #<?= htmlspecialchars($tag['name']) ?>
                                    </span>

                                </td>

                                <td>

                                    <code>
                                        <?= htmlspecialchars($tag['slug']) ?>
                                    </code>

                                </td>

                                <td>

                                    <span class="badge text-bg-secondary">
                                        <?= (int) $tag['articles_count'] ?>
                                    </span>

                                </td>

                                <td>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime($tag['created_at'])
                                    ) ?>

                                </td>

                                <td class="text-end">

                                    <a href="edit.php?id=<?= (int) $tag['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a href="delete.php?id=<?= (int) $tag['id'] ?>"
                                        class="btn btn-sm btn-outline-danger" onclick="return confirm(
                                            'Voulez-vous supprimer ce tag ?'
                                        );">
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </td>

                            </tr>

                            <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</body>

</html>