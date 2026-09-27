<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$stmt = $db->query("
    SELECT 
        c.*,
        COUNT(a.id) AS articles_count
    FROM categories c
    LEFT JOIN articles a ON a.category_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

$categories = $stmt->fetchAll();

$user = currentUser();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Catégories — <?= htmlspecialchars(APP_NAME) ?></title>

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
                    Catégories
                </h1>

                <p class="text-muted mb-0">
                    Organisez les articles de votre journal.
                </p>
            </div>

            <a href="create.php" class="btn btn-dark">
                <i class="bi bi-plus-lg"></i>
                Nouvelle catégorie
            </a>

        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>#</th>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Articles</th>
                                <th>Statut</th>
                                <th>Créée le</th>
                                <th class="text-end">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!$categories): ?>

                            <tr>

                                <td colspan="7" class="text-center py-5 text-muted">
                                    Aucune catégorie disponible.
                                </td>

                            </tr>

                            <?php else: ?>

                            <?php foreach ($categories as $category): ?>

                            <tr>

                                <td>
                                    <?= (int) $category['id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <code>
                                        <?= htmlspecialchars($category['slug']) ?>
                                    </code>
                                </td>

                                <td>

                                    <span class="badge text-bg-secondary">
                                        <?= (int) $category['articles_count'] ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($category['status'] === 'active'): ?>

                                    <span class="badge text-bg-success">
                                        Active
                                    </span>

                                    <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        Inactive
                                    </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= date(
                                        'd/m/Y',
                                        strtotime($category['created_at'])
                                    ) ?>
                                </td>

                                <td class="text-end">

                                    <a href="edit.php?id=<?= (int) $category['id'] ?>"
                                        class="btn btn-sm btn-outline-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a href="delete.php?id=<?= (int) $category['id'] ?>"
                                        class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirm(
                                            'Voulez-vous vraiment supprimer cette catégorie ?'
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