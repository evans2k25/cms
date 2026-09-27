<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));

$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(a.title LIKE :search OR a.excerpt LIKE :search)";
    $params[':search'] = '%' . $q . '%';
}

if ($status !== '') {
    $where[] = "a.status = :status";
    $params[':status'] = $status;
}

if ($category !== '') {
    $where[] = "a.category_id = :category";
    $params[':category'] = (int)$category;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
|--------------------------------------------------------------------------
| Nombre total d'articles
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM articles a
    $whereSql
";

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);

$totalArticles = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalArticles / $perPage));

/*
|--------------------------------------------------------------------------
| Articles
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.*,
        c.name AS category_name,
        u.username AS author_username,
        CONCAT(u.first_name, ' ', u.last_name) AS author_name
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    INNER JOIN users u ON u.id = a.user_id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {
    if ($key === ':category') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$articles = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Catégories
|--------------------------------------------------------------------------
*/

$categoryStmt = $db->query("
    SELECT id, name
    FROM categories
    WHERE status = 'active'
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll();

function statusBadge(string $status): string
{
    $badges = [
        'draft' => '<span class="badge bg-secondary">Brouillon</span>',
        'pending' => '<span class="badge bg-warning text-dark">En attente</span>',
        'published' => '<span class="badge bg-success">Publié</span>',
        'scheduled' => '<span class="badge bg-info text-dark">Programmé</span>',
        'archived' => '<span class="badge bg-dark">Archivé</span>',
    ];

    return $badges[$status] ?? '<span class="badge bg-secondary">Inconnu</span>';
}

function articleImage(?string $image): string
{
    if (!$image) {
        return '<div class="article-placeholder">
                    <i class="bi bi-image"></i>
                </div>';
    }

    return '<img src="' . BASE_URL . '/public/' . htmlspecialchars($image) . '"
                 class="article-thumb"
                 alt="Image">';
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Articles - <?= htmlspecialchars(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    body {
        background: #f5f7fb;
    }

    .page-header {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .article-card {
        background: white;
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
    }

    .article-thumb,
    .article-placeholder {
        width: 90px;
        height: 70px;
        object-fit: cover;
        border-radius: 10px;
    }

    .article-placeholder {
        background: #eef1f5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 24px;
    }

    .article-title {
        font-weight: 600;
        color: #212529;
        text-decoration: none;
    }

    .article-title:hover {
        color: #0d6efd;
    }

    .table> :not(caption)>*>* {
        padding: 16px;
        vertical-align: middle;
    }

    .filter-box {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }
    </style>

</head>

<body>

    <div class="container-fluid py-4">

        <!-- HEADER -->

        <div class="page-header">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <div>

                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-newspaper"></i>
                        Articles
                    </h2>

                    <p class="text-muted mb-0">
                        Gérez les articles de votre journal.
                    </p>

                </div>

                <a href="create.php" class="btn btn-primary">

                    <i class="bi bi-plus-lg"></i>

                    Nouvel article

                </a>

            </div>

        </div>


        <!-- FILTRES -->

        <div class="filter-box">

            <form method="GET">

                <div class="row g-3">

                    <div class="col-lg-5">

                        <label class="form-label">
                            Rechercher
                        </label>

                        <input type="search" name="q" class="form-control" placeholder="Titre ou extrait..."
                            value="<?= htmlspecialchars($q) ?>">

                    </div>


                    <div class="col-lg-3">

                        <label class="form-label">
                            Statut
                        </label>

                        <select name="status" class="form-select">

                            <option value="">
                                Tous les statuts
                            </option>

                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>
                                Brouillons
                            </option>

                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>
                                En attente
                            </option>

                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>
                                Publiés
                            </option>

                            <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>
                                Programmés
                            </option>

                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>
                                Archivés
                            </option>

                        </select>

                    </div>


                    <div class="col-lg-3">

                        <label class="form-label">
                            Catégorie
                        </label>

                        <select name="category" class="form-select">

                            <option value="">
                                Toutes les catégories
                            </option>

                            <?php foreach ($categories as $cat): ?>

                            <option value="<?= $cat['id'] ?>"
                                <?= (string)$category === (string)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-1 d-flex align-items-end">

                        <button class="btn btn-dark w-100">

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </div>

            </form>

        </div>


        <!-- TABLE -->

        <div class="article-card">

            <div class="table-responsive">

                <table class="table table-hover mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Article</th>

                            <th>Catégorie</th>

                            <th>Auteur</th>

                            <th>Statut</th>

                            <th>Vues</th>

                            <th>Date</th>

                            <th class="text-end">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!$articles): ?>

                        <tr>

                            <td colspan="7" class="text-center py-5">

                                <i class="bi bi-newspaper fs-1 text-muted"></i>

                                <p class="mt-3 mb-0 text-muted">
                                    Aucun article trouvé.
                                </p>

                            </td>

                        </tr>

                        <?php else: ?>

                        <?php foreach ($articles as $article): ?>

                        <tr>

                            <td>

                                <div class="d-flex align-items-center gap-3">

                                    <?= articleImage($article['featured_image']) ?>

                                    <div>

                                        <a href="edit.php?id=<?= $article['id'] ?>" class="article-title">
                                            <?= htmlspecialchars($article['title']) ?>
                                        </a>

                                        <?php if ((int)$article['featured'] === 1): ?>

                                        <span class="badge bg-warning text-dark ms-2">
                                            <i class="bi bi-star-fill"></i>
                                        </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $article['category_name'] ?? 'Sans catégorie'
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    trim($article['author_name'])
                                    ?: $article['author_username']
                                ) ?>

                            </td>


                            <td>
                                <?= statusBadge($article['status']) ?>
                            </td>


                            <td>

                                <i class="bi bi-eye"></i>

                                <?= number_format(
                                    (int)$article['views'],
                                    0,
                                    ',',
                                    ' '
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($article['created_at'])
                                ) ?>

                            </td>


                            <td class="text-end">

                                <div class="btn-group">

                                    <a href="edit.php?id=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary"
                                        title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <a href="preview.php?id=<?= $article['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary" title="Aperçu">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <a href="delete.php?id=<?= $article['id'] ?>" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Supprimer définitivement cet article ?');"
                                        title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </div>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->

            <?php if ($totalPages > 1): ?>

            <div class="p-3">

                <nav>

                    <ul class="pagination justify-content-center mb-0">

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <li class="page-item
                                <?= $i === $page ? 'active' : '' ?>">

                            <a class="page-link" href="?<?= http_build_query([
                                        'q' => $q,
                                        'status' => $status,
                                        'category' => $category,
                                        'page' => $i
                                    ]) ?>">
                                <?= $i ?>
                            </a>

                        </li>

                        <?php endfor; ?>

                    </ul>

                </nav>

            </div>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>