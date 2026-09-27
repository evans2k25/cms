<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

$database = new Database();
$db = $database->connect();

/*
|--------------------------------------------------------------------------
| Fonctions
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function articleImage(?string $image): string
{
    if (!$image) {
        return BASE_URL . '/public/assets/images/article-placeholder.jpg';
    }

    return BASE_URL . '/public/uploads/articles/' . rawurlencode($image);
}

function formatDateArticle(?string $date): string
{
    if (!$date) {
        return '';
    }

    return date('d/m/Y à H:i', strtotime($date));
}

/*
|--------------------------------------------------------------------------
| Paramètres de recherche
|--------------------------------------------------------------------------
*/

$query = trim($_GET['q'] ?? '');
$tagSlug = trim($_GET['tag'] ?? '');

$perPage = 10;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

/*
|--------------------------------------------------------------------------
| Catégories pour la navigation
|--------------------------------------------------------------------------
*/

$categoriesStmt = $db->query("
    SELECT
        c.id,
        c.name,
        c.slug,
        COUNT(a.id) AS articles_count
    FROM categories c
    LEFT JOIN articles a
        ON a.category_id = c.id
        AND a.status = 'published'
        AND a.published_at IS NOT NULL
        AND a.published_at <= NOW()
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.name ASC
");

$categories = $categoriesStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Tag recherché
|--------------------------------------------------------------------------
*/

$currentTag = null;

if ($tagSlug !== '') {

    $tagStmt = $db->prepare("
        SELECT
            id,
            name,
            slug
        FROM tags
        WHERE slug = :slug
        LIMIT 1
    ");

    $tagStmt->execute([
        ':slug' => $tagSlug
    ]);

    $currentTag = $tagStmt->fetch();

    if (!$currentTag) {
        $tagSlug = '';
    }
}

/*
|--------------------------------------------------------------------------
| Construction de la requête
|--------------------------------------------------------------------------
*/

$where = [
    "a.status = 'published'",
    "a.published_at IS NOT NULL",
    "a.published_at <= NOW()"
];

$params = [];

/*
|--------------------------------------------------------------------------
| Recherche textuelle
|--------------------------------------------------------------------------
*/

if ($query !== '') {

    $where[] = "
        (
            a.title LIKE :search_title
            OR a.excerpt LIKE :search_excerpt
            OR a.content LIKE :search_content
        )
    ";

    $searchValue = '%' . $query . '%';

    $params[':search_title'] = $searchValue;
    $params[':search_excerpt'] = $searchValue;
    $params[':search_content'] = $searchValue;
}

/*
|--------------------------------------------------------------------------
| Filtre par tag
|--------------------------------------------------------------------------
*/

if ($currentTag) {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM article_tags search_at
            WHERE search_at.article_id = a.id
              AND search_at.tag_id = :tag_id
        )
    ";

    $params[':tag_id'] = $currentTag['id'];
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| Nombre total de résultats
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM articles a
    WHERE {$whereSql}
";

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);

$totalArticles = (int) $countStmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalArticles / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| Résultats
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.title,
        a.slug,
        a.excerpt,
        a.content,
        a.featured_image,
        a.published_at,
        a.views,
        a.featured,

        c.name AS category_name,
        c.slug AS category_slug,

        u.username,
        u.first_name,
        u.last_name

    FROM articles a

    LEFT JOIN categories c
        ON c.id = a.category_id

    INNER JOIN users u
        ON u.id = a.user_id

    WHERE {$whereSql}

    ORDER BY
        a.featured DESC,
        a.published_at DESC

    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue(
        $key,
        $value,
        is_int($value)
            ? PDO::PARAM_INT
            : PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$articles = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Articles populaires
|--------------------------------------------------------------------------
*/

$popularStmt = $db->query("
    SELECT
        a.id,
        a.title,
        a.slug,
        a.views
    FROM articles a
    WHERE a.status = 'published'
      AND a.published_at IS NOT NULL
      AND a.published_at <= NOW()
    ORDER BY a.views DESC
    LIMIT 5
");

$popularArticles = $popularStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| URL pagination
|--------------------------------------------------------------------------
*/

function paginationUrl(
    string $query,
    string $tagSlug,
    int $page
): string {

    $params = [];

    if ($query !== '') {
        $params['q'] = $query;
    }

    if ($tagSlug !== '') {
        $params['tag'] = $tagSlug;
    }

    $params['page'] = $page;

    return BASE_URL .
        '/public/search.php?' .
        http_build_query($params);
}

/*
|--------------------------------------------------------------------------
| Titre
|--------------------------------------------------------------------------
*/

if ($currentTag) {

    $pageTitle =
        'Tag : #' .
        $currentTag['name'];

} elseif ($query !== '') {

    $pageTitle =
        'Résultats pour : "' .
        $query .
        '"';

} else {

    $pageTitle = 'Recherche';

}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($pageTitle) ?> — <?= e(APP_NAME) ?>
    </title>

    <meta name="description" content="Recherchez les articles de <?= e(APP_NAME) ?>.">

    <link rel="canonical" href="<?= e(BASE_URL . '/public/search.php') ?>">

    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
    :root {
        --primary: #b71c1c;
        --primary-dark: #7f0000;
        --light-red: #ffebee;
        --dark: #151515;
    }

    body {
        background: #f5f5f5;
        color: #222;
        font-family: Arial, Helvetica, sans-serif;
    }

    /* HEADER */

    .site-header {
        background: #fff;
        border-bottom: 1px solid #ddd;
    }

    .brand {
        font-size: 2rem;
        font-weight: 900;
        letter-spacing: -1px;
        color: var(--primary);
        text-decoration: none;
    }

    .brand span {
        color: #222;
    }

    .top-nav {
        background: var(--dark);
    }

    .top-nav a {
        color: #fff;
        text-decoration: none;
        font-size: .85rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .top-nav a:hover {
        color: #ffb3b3;
    }

    /* SEARCH */

    .search-header {
        background: #fff;
        padding: 35px;
        border-bottom: 5px solid var(--primary);
        margin-bottom: 30px;
    }

    .search-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2.2rem, 5vw, 3.5rem);
        font-weight: 900;
    }

    .search-form {
        max-width: 800px;
    }

    .search-form .form-control {
        height: 55px;
        border-radius: 0;
        border: 2px solid #ddd;
    }

    .search-form .btn {
        border-radius: 0;
        min-width: 120px;
    }

    /* RESULT */

    .result-card {
        background: #fff;
        margin-bottom: 20px;
        transition: .2s ease;
        overflow: hidden;
    }

    .result-card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, .08);
        transform: translateY(-2px);
    }

    .result-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
    }

    .result-body {
        padding: 25px;
    }

    .result-category {
        display: inline-block;
        color: var(--primary);
        font-size: .75rem;
        font-weight: 800;
        text-transform: uppercase;
        text-decoration: none;
        margin-bottom: 8px;
    }

    .result-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.6rem;
        line-height: 1.15;
        font-weight: 800;
    }

    .result-title a {
        color: #222;
        text-decoration: none;
    }

    .result-title a:hover {
        color: var(--primary);
    }

    .result-excerpt {
        color: #666;
        line-height: 1.65;
    }

    .result-meta {
        font-size: .8rem;
        color: #777;
    }

    .highlight {
        background: #fff1a8;
        padding: 0 2px;
    }

    /* SIDEBAR */

    .sidebar-box {
        background: #fff;
        padding: 25px;
        margin-bottom: 25px;
    }

    .sidebar-title {
        font-family: Georgia, "Times New Roman", serif;
        font-weight: 900;
        border-bottom: 3px solid var(--primary);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .popular-item {
        display: flex;
        gap: 12px;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }

    .popular-number {
        color: var(--primary);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.7rem;
        font-weight: 900;
        min-width: 30px;
    }

    .popular-item h4 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1rem;
        line-height: 1.25;
        margin: 0;
    }

    .popular-item a {
        color: #222;
        text-decoration: none;
    }

    .popular-item a:hover {
        color: var(--primary);
    }

    /* TAG */

    .active-tag {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 7px 13px;
        border-radius: 20px;
        font-size: .85rem;
    }

    /* PAGINATION */

    .page-link {
        color: var(--primary);
    }

    .page-item.active .page-link {
        background: var(--primary);
        border-color: var(--primary);
    }

    .page-link:hover {
        background: var(--primary-dark);
        color: #fff;
    }

    /* EMPTY */

    .empty-state {
        background: #fff;
        padding: 80px 30px;
        text-align: center;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ccc;
    }

    /* FOOTER */

    footer {
        background: #151515;
        color: #aaa;
        margin-top: 70px;
    }

    footer a {
        color: #fff;
        text-decoration: none;
    }

    @media (max-width: 768px) {

        .search-header {
            padding: 25px;
        }

        .result-image {
            height: 200px;
        }

        .result-title {
            font-size: 1.4rem;
        }

    }
    </style>

</head>

<body>

    <!-- HEADER -->

    <header class="site-header">

        <div class="container py-3">

            <div class="d-flex justify-content-between align-items-center">

                <a href="<?= BASE_URL ?>/public/index.php" class="brand">
                    MON<span>JOURNAL</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/login.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-person"></i>
                    Administration
                </a>

            </div>

        </div>


        <nav class="top-nav">

            <div class="container">

                <div class="d-flex gap-4 py-2 overflow-auto">

                    <a href="<?= BASE_URL ?>/public/index.php">
                        ACCUEIL
                    </a>

                    <?php foreach ($categories as $category): ?>

                    <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>">
                        <?= e(mb_strtoupper($category['name'])) ?>
                    </a>

                    <?php endforeach; ?>

                </div>

            </div>

        </nav>

    </header>


    <!-- CONTENU -->

    <main class="py-5">

        <div class="container">

            <!-- EN-TÊTE -->

            <section class="search-header">

                <div class="text-danger fw-bold mb-2">
                    RECHERCHE
                </div>

                <h1 class="search-title mb-4">
                    <?= e($pageTitle) ?>
                </h1>


                <!-- FORMULAIRE -->

                <form method="GET" action="<?= BASE_URL ?>/public/search.php" class="search-form">

                    <div class="input-group">

                        <input type="search" name="q" class="form-control" placeholder="Rechercher un article..."
                            value="<?= e($query) ?>" autocomplete="off">

                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-search"></i>
                            Rechercher
                        </button>

                    </div>

                </form>


                <?php if ($currentTag): ?>

                <div class="mt-4">

                    <span class="me-2">
                        Filtre actif :
                    </span>

                    <span class="active-tag">

                        <i class="bi bi-tag"></i>

                        #<?= e($currentTag['name']) ?>

                    </span>

                    <a href="<?= BASE_URL ?>/public/search.php" class="btn btn-sm btn-outline-secondary ms-2">
                        Retirer
                    </a>

                </div>

                <?php endif; ?>


                <?php if ($query !== '' || $currentTag): ?>

                <div class="mt-3 text-muted">

                    <strong>
                        <?= number_format(
                            $totalArticles,
                            0,
                            ',',
                            ' '
                        ) ?>
                    </strong>

                    résultat<?= $totalArticles > 1 ? 's' : '' ?>

                </div>

                <?php endif; ?>

            </section>


            <div class="row g-4">

                <!-- RÉSULTATS -->

                <div class="col-lg-8">

                    <?php if (!empty($articles)): ?>

                    <?php foreach ($articles as $article): ?>

                    <article class="result-card">

                        <div class="row g-0">

                            <div class="col-md-4">

                                <?php if ($article['featured_image']): ?>

                                <img src="<?= e(
                                                articleImage(
                                                    $article['featured_image']
                                                )
                                            ) ?>" alt="<?= e($article['title']) ?>" class="result-image">

                                <?php else: ?>

                                <div class="result-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="bi bi-newspaper fs-1 text-secondary"></i>
                                </div>

                                <?php endif; ?>

                            </div>


                            <div class="col-md-8">

                                <div class="result-body">

                                    <?php if ($article['category_name']): ?>

                                    <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($article['category_slug']) ?>"
                                        class="result-category">
                                        <?= e($article['category_name']) ?>
                                    </a>

                                    <?php endif; ?>


                                    <h2 class="result-title">

                                        <a
                                            href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($article['slug']) ?>">
                                            <?= e($article['title']) ?>
                                        </a>

                                    </h2>


                                    <?php if ($article['excerpt']): ?>

                                    <p class="result-excerpt">

                                        <?= e(
                                                    mb_strimwidth(
                                                        $article['excerpt'],
                                                        0,
                                                        230,
                                                        '...'
                                                    )
                                                ) ?>

                                    </p>

                                    <?php else: ?>

                                    <p class="result-excerpt">

                                        <?= e(
                                                    mb_strimwidth(
                                                        strip_tags(
                                                            $article['content']
                                                        ),
                                                        0,
                                                        230,
                                                        '...'
                                                    )
                                                ) ?>

                                    </p>

                                    <?php endif; ?>


                                    <div class="result-meta">

                                        <span>

                                            <i class="bi bi-calendar3"></i>

                                            <?= e(
                                                    formatDateArticle(
                                                        $article['published_at']
                                                    )
                                                ) ?>

                                        </span>


                                        <span class="ms-3">

                                            <i class="bi bi-eye"></i>

                                            <?= number_format(
                                                    (int) $article['views'],
                                                    0,
                                                    ',',
                                                    ' '
                                                ) ?>

                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </article>

                    <?php endforeach; ?>


                    <!-- PAGINATION -->

                    <?php if ($totalPages > 1): ?>

                    <nav aria-label="Pagination des résultats" class="mt-4">

                        <ul class="pagination justify-content-center">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                                <a class="page-link" href="<?= e(
                                            paginationUrl(
                                                $query,
                                                $tagSlug,
                                                $page - 1
                                            )
                                        ) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>

                            </li>


                            <?php

                                $startPage = max(
                                    1,
                                    $page - 2
                                );

                                $endPage = min(
                                    $totalPages,
                                    $page + 2
                                );

                                ?>


                            <?php if ($startPage > 1): ?>

                            <li class="page-item">

                                <a class="page-link" href="<?= e(
                                                paginationUrl(
                                                    $query,
                                                    $tagSlug,
                                                    1
                                                )
                                            ) ?>">
                                    1
                                </a>

                            </li>

                            <?php if ($startPage > 2): ?>

                            <li class="page-item disabled">

                                <span class="page-link">
                                    …
                                </span>

                            </li>

                            <?php endif; ?>

                            <?php endif; ?>


                            <?php for (
                                    $i = $startPage;
                                    $i <= $endPage;
                                    $i++
                                ): ?>

                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                                <a class="page-link" href="<?= e(
                                                paginationUrl(
                                                    $query,
                                                    $tagSlug,
                                                    $i
                                                )
                                            ) ?>">
                                    <?= $i ?>
                                </a>

                            </li>

                            <?php endfor; ?>


                            <?php if ($endPage < $totalPages): ?>

                            <?php if ($endPage < $totalPages - 1): ?>

                            <li class="page-item disabled">

                                <span class="page-link">
                                    …
                                </span>

                            </li>

                            <?php endif; ?>


                            <li class="page-item">

                                <a class="page-link" href="<?= e(
                                                paginationUrl(
                                                    $query,
                                                    $tagSlug,
                                                    $totalPages
                                                )
                                            ) ?>">
                                    <?= $totalPages ?>
                                </a>

                            </li>

                            <?php endif; ?>


                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                                <a class="page-link" href="<?= e(
                                            paginationUrl(
                                                $query,
                                                $tagSlug,
                                                $page + 1
                                            )
                                        ) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>

                            </li>

                        </ul>

                    </nav>

                    <?php endif; ?>


                    <?php else: ?>

                    <!-- AUCUN RÉSULTAT -->

                    <div class="empty-state">

                        <i class="bi bi-search"></i>

                        <h2 class="mt-4">
                            Aucun résultat
                        </h2>

                        <?php if ($query !== ''): ?>

                        <p class="text-muted">

                            Aucun article ne correspond à :

                            <strong>
                                <?= e($query) ?>
                            </strong>

                        </p>

                        <?php elseif ($currentTag): ?>

                        <p class="text-muted">

                            Aucun article n'est associé au tag

                            <strong>
                                #<?= e($currentTag['name']) ?>
                            </strong>

                        </p>

                        <?php else: ?>

                        <p class="text-muted">
                            Utilisez le champ ci-dessus
                            pour rechercher un article.
                        </p>

                        <?php endif; ?>


                        <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-danger">
                            <i class="bi bi-house"></i>
                            Retour à l'accueil
                        </a>

                    </div>

                    <?php endif; ?>

                </div>


                <!-- SIDEBAR -->

                <aside class="col-lg-4">

                    <!-- PLUS LUS -->

                    <div class="sidebar-box">

                        <h3 class="sidebar-title">

                            <i class="bi bi-fire text-danger"></i>

                            Articles populaires

                        </h3>


                        <?php foreach (
                        $popularArticles as $index => $popular
                    ): ?>

                        <div class="popular-item">

                            <div class="popular-number">
                                <?= $index + 1 ?>
                            </div>

                            <div>

                                <h4>

                                    <a
                                        href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($popular['slug']) ?>">
                                        <?= e($popular['title']) ?>
                                    </a>

                                </h4>

                                <small class="text-muted">

                                    <i class="bi bi-eye"></i>

                                    <?= number_format(
                                        (int) $popular['views'],
                                        0,
                                        ',',
                                        ' '
                                    ) ?>

                                </small>

                            </div>

                        </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- RUBRIQUES -->

                    <div class="sidebar-box">

                        <h3 class="sidebar-title">
                            Rubriques
                        </h3>


                        <?php foreach ($categories as $category): ?>

                        <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>"
                            class="d-flex justify-content-between align-items-center border-bottom py-2 text-dark text-decoration-none">

                            <span>
                                <?= e($category['name']) ?>
                            </span>

                            <span class="badge bg-light text-dark">

                                <?= (int) $category['articles_count'] ?>

                            </span>

                        </a>

                        <?php endforeach; ?>

                    </div>


                    <!-- NEWSLETTER -->

                    <div class="sidebar-box text-white" style="background: var(--primary);">

                        <h3 class="fw-bold">
                            Newsletter
                        </h3>

                        <p>
                            Recevez les dernières actualités
                            de votre journal.
                        </p>

                        <form>

                            <input type="email" class="form-control mb-2" placeholder="Votre adresse email">

                            <button class="btn btn-dark w-100" type="submit">
                                S'inscrire
                            </button>

                        </form>

                    </div>

                </aside>

            </div>

        </div>

    </main>


    <!-- FOOTER -->

    <footer>

        <div class="container py-5">

            <div class="row g-4">

                <div class="col-md-6">

                    <h4 class="text-white fw-bold">
                        MONJOURNAL
                    </h4>

                    <p>
                        Votre source d'information,
                        d'actualité et d'analyse.
                    </p>

                </div>

                <div class="col-md-3">

                    <h6 class="text-white">
                        Navigation
                    </h6>

                    <div class="d-flex flex-column gap-2">

                        <a href="<?= BASE_URL ?>/public/index.php">
                            Accueil
                        </a>

                        <a href="<?= BASE_URL ?>/public/search.php">
                            Recherche
                        </a>

                    </div>

                </div>

                <div class="col-md-3">

                    <h6 class="text-white">
                        Administration
                    </h6>

                    <a href="<?= BASE_URL ?>/admin/login.php">
                        Connexion
                    </a>

                </div>

            </div>

            <hr>

            <div class="text-center small">

                © <?= date('Y') ?>
                <?= e(APP_NAME) ?>
                — Tous droits réservés.

            </div>

        </div>

    </footer>

</body>

</html>