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
| Récupération de la catégorie
|--------------------------------------------------------------------------
*/

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(404);
    exit('Catégorie introuvable.');
}

$categoryStmt = $db->prepare("
    SELECT
        id,
        name,
        slug,
        description,
        image
    FROM categories
    WHERE slug = :slug
      AND status = 'active'
    LIMIT 1
");

$categoryStmt->execute([
    ':slug' => $slug
]);

$category = $categoryStmt->fetch();

if (!$category) {
    http_response_code(404);
    exit('Catégorie introuvable.');
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 9;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

/*
|--------------------------------------------------------------------------
| Nombre total d'articles
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM articles
    WHERE category_id = :category_id
      AND status = 'published'
      AND published_at IS NOT NULL
      AND published_at <= NOW()
");

$countStmt->execute([
    ':category_id' => $category['id']
]);

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
| Articles de la catégorie
|--------------------------------------------------------------------------
*/

$articlesStmt = $db->prepare("
    SELECT
        a.id,
        a.title,
        a.slug,
        a.excerpt,
        a.featured_image,
        a.published_at,
        a.views,
        a.featured,
        u.username,
        u.first_name,
        u.last_name
    FROM articles a
    INNER JOIN users u
        ON u.id = a.user_id
    WHERE a.category_id = :category_id
      AND a.status = 'published'
      AND a.published_at IS NOT NULL
      AND a.published_at <= NOW()
    ORDER BY
        a.featured DESC,
        a.published_at DESC
    LIMIT :limit OFFSET :offset
");

$articlesStmt->bindValue(
    ':category_id',
    $category['id'],
    PDO::PARAM_INT
);

$articlesStmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$articlesStmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$articlesStmt->execute();

$articles = $articlesStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Article principal
|--------------------------------------------------------------------------
|
| Le premier article est présenté comme "une".
|--------------------------------------------------------------------------
*/

$leadArticle = $articles[0] ?? null;

$gridArticles = array_slice($articles, 1);

/*
|--------------------------------------------------------------------------
| Articles les plus lus
|--------------------------------------------------------------------------
*/

$popularStmt = $db->prepare("
    SELECT
        a.id,
        a.title,
        a.slug,
        a.featured_image,
        a.views,
        a.published_at
    FROM articles a
    WHERE a.category_id = :category_id
      AND a.status = 'published'
      AND a.published_at IS NOT NULL
      AND a.published_at <= NOW()
    ORDER BY a.views DESC, a.published_at DESC
    LIMIT 5
");

$popularStmt->execute([
    ':category_id' => $category['id']
]);

$popularArticles = $popularStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Autres catégories
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
| SEO
|--------------------------------------------------------------------------
*/

$metaTitle = $category['name'] . ' — ' . APP_NAME;

$metaDescription = $category['description']
    ?: 'Découvrez toutes les actualités de la rubrique ' .
       $category['name'] . '.';

$canonicalUrl =
    BASE_URL .
    '/public/category.php?slug=' .
    rawurlencode($category['slug']);

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($metaTitle) ?>
    </title>

    <meta name="description" content="<?= e($metaDescription) ?>">

    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- Open Graph -->

    <meta property="og:type" content="website">

    <meta property="og:title" content="<?= e($metaTitle) ?>">

    <meta property="og:description" content="<?= e($metaDescription) ?>">

    <meta property="og:url" content="<?= e($canonicalUrl) ?>">

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
        --gray: #6c757d;
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

    /* MAIN */

    .page-container {
        max-width: 1250px;
        margin: auto;
    }

    /* CATEGORY HEADER */

    .category-header {
        background: #fff;
        padding: 35px;
        border-bottom: 5px solid var(--primary);
        margin-bottom: 30px;
    }

    .category-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 900;
        margin: 0;
    }

    .category-description {
        color: #666;
        max-width: 800px;
        margin-top: 12px;
        font-size: 1.05rem;
    }

    .category-count {
        color: var(--primary);
        font-weight: 700;
    }

    /* LEAD ARTICLE */

    .lead-article {
        background: #fff;
        margin-bottom: 30px;
    }

    .lead-image-wrapper {
        position: relative;
        height: 440px;
        overflow: hidden;
    }

    .lead-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .lead-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top,
                rgba(0, 0, 0, .85),
                rgba(0, 0, 0, .05));
        display: flex;
        align-items: flex-end;
    }

    .lead-content {
        color: #fff;
        padding: 35px;
        width: 100%;
    }

    .lead-content h2 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2rem, 4vw, 3.5rem);
        line-height: 1.05;
        font-weight: 900;
        max-width: 900px;
    }

    .lead-content h2 a {
        color: #fff;
        text-decoration: none;
    }

    .lead-content h2 a:hover {
        text-decoration: underline;
    }

    .lead-excerpt {
        max-width: 850px;
        color: #eee;
        margin-top: 15px;
    }

    /* ARTICLE GRID */

    .article-card {
        background: #fff;
        height: 100%;
        transition: .2s ease;
        overflow: hidden;
    }

    .article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, .1);
    }

    .article-card-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
    }

    .article-card-body {
        padding: 20px;
    }

    .article-card h3 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.35rem;
        line-height: 1.2;
        font-weight: 800;
    }

    .article-card h3 a {
        color: #222;
        text-decoration: none;
    }

    .article-card h3 a:hover {
        color: var(--primary);
    }

    .article-excerpt {
        color: #666;
        line-height: 1.6;
    }

    .article-meta {
        font-size: .8rem;
        color: #777;
    }

    .featured-label {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 5px 9px;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
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
        font-family: Georgia, "Times New Roman", serif;
        color: var(--primary);
        font-size: 1.8rem;
        font-weight: 900;
        min-width: 30px;
    }

    .popular-item h4 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1rem;
        line-height: 1.25;
        margin: 0;
    }

    .popular-item h4 a {
        color: #222;
        text-decoration: none;
    }

    .popular-item h4 a:hover {
        color: var(--primary);
    }

    /* RUBRIQUES */

    .category-link {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 11px 0;
        border-bottom: 1px solid #eee;
        color: #333;
        text-decoration: none;
    }

    .category-link:hover {
        color: var(--primary);
    }

    .category-link.active {
        color: var(--primary);
        font-weight: 800;
    }

    .category-badge {
        background: #eee;
        border-radius: 20px;
        padding: 3px 9px;
        font-size: .75rem;
    }

    /* PAGINATION */

    .pagination {
        margin-top: 40px;
    }

    .page-link {
        color: var(--primary);
        border-color: #ddd;
    }

    .page-item.active .page-link {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    .page-link:hover {
        color: #fff;
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    /* EMPTY */

    .empty-state {
        background: #fff;
        padding: 70px 30px;
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

        .category-header {
            padding: 25px;
        }

        .lead-image-wrapper {
            height: 350px;
        }

        .lead-content {
            padding: 22px;
        }

        .lead-content h2 {
            font-size: 2rem;
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

                <div class="d-flex gap-2">

                    <a href="<?= BASE_URL ?>/public/search.php" class="btn btn-light" title="Rechercher">
                        <i class="bi bi-search"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/login.php" class="btn btn-outline-danger">
                        <i class="bi bi-person"></i>
                        Administration
                    </a>

                </div>

            </div>

        </div>

        <!-- NAVIGATION -->

        <nav class="top-nav">

            <div class="container">

                <div class="d-flex gap-4 py-2 overflow-auto">

                    <a href="<?= BASE_URL ?>/public/index.php">
                        ACCUEIL
                    </a>

                    <?php foreach ($categories as $navCategory): ?>

                    <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($navCategory['slug']) ?>">
                        <?= e(mb_strtoupper($navCategory['name'])) ?>
                    </a>

                    <?php endforeach; ?>

                </div>

            </div>

        </nav>

    </header>


    <!-- CONTENU -->

    <main class="py-5">

        <div class="container page-container">

            <!-- EN-TÊTE RUBRIQUE -->

            <section class="category-header">

                <div class="d-flex flex-wrap justify-content-between gap-3">

                    <div>

                        <div class="text-danger fw-bold mb-2">
                            RUBRIQUE
                        </div>

                        <h1 class="category-title">
                            <?= e($category['name']) ?>
                        </h1>

                        <?php if ($category['description']): ?>

                        <p class="category-description mb-0">
                            <?= e($category['description']) ?>
                        </p>

                        <?php endif; ?>

                    </div>

                    <div class="align-self-center">

                        <span class="category-count">

                            <?= number_format(
                            $totalArticles,
                            0,
                            ',',
                            ' '
                        ) ?>

                            article<?= $totalArticles > 1 ? 's' : '' ?>

                        </span>

                    </div>

                </div>

            </section>


            <?php if ($leadArticle): ?>

            <div class="row g-4">

                <!-- COLONNE PRINCIPALE -->

                <div class="col-lg-8">

                    <!-- UNE -->

                    <article class="lead-article">

                        <div class="lead-image-wrapper">

                            <?php if ($leadArticle['featured_image']): ?>

                            <img src="<?= e(
                                        articleImage(
                                            $leadArticle['featured_image']
                                        )
                                    ) ?>" alt="<?= e($leadArticle['title']) ?>" class="lead-image">

                            <?php else: ?>

                            <div class="lead-image d-flex align-items-center justify-content-center bg-dark text-white">
                                <i class="bi bi-newspaper fs-1"></i>
                            </div>

                            <?php endif; ?>


                            <div class="lead-overlay">

                                <div class="lead-content">

                                    <?php if ($leadArticle['featured']): ?>

                                    <span class="featured-label mb-3">
                                        <i class="bi bi-star-fill"></i>
                                        À la une
                                    </span>

                                    <?php endif; ?>

                                    <h2>

                                        <a
                                            href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($leadArticle['slug']) ?>">
                                            <?= e($leadArticle['title']) ?>
                                        </a>

                                    </h2>

                                    <?php if ($leadArticle['excerpt']): ?>

                                    <p class="lead-excerpt mb-2">
                                        <?= e($leadArticle['excerpt']) ?>
                                    </p>

                                    <?php endif; ?>

                                    <div class="small">

                                        <i class="bi bi-calendar3"></i>

                                        <?= e(
                                            formatDateArticle(
                                                $leadArticle['published_at']
                                            )
                                        ) ?>

                                        <span class="mx-2">•</span>

                                        <i class="bi bi-eye"></i>

                                        <?= number_format(
                                            (int) $leadArticle['views'],
                                            0,
                                            ',',
                                            ' '
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </article>


                    <!-- GRILLE ARTICLES -->

                    <?php if (!empty($gridArticles)): ?>

                    <div class="row g-4">

                        <?php foreach ($gridArticles as $article): ?>

                        <div class="col-md-6">

                            <article class="article-card">

                                <?php if ($article['featured_image']): ?>

                                <img src="<?= e(
                                                    articleImage(
                                                        $article['featured_image']
                                                    )
                                                ) ?>" alt="<?= e($article['title']) ?>" class="article-card-image">

                                <?php else: ?>

                                <div
                                    class="article-card-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="bi bi-image fs-1 text-secondary"></i>
                                </div>

                                <?php endif; ?>


                                <div class="article-card-body">

                                    <?php if ($article['featured']): ?>

                                    <span class="featured-label mb-2">
                                        À la une
                                    </span>

                                    <?php endif; ?>


                                    <h3>

                                        <a
                                            href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($article['slug']) ?>">
                                            <?= e($article['title']) ?>
                                        </a>

                                    </h3>


                                    <?php if ($article['excerpt']): ?>

                                    <p class="article-excerpt">
                                        <?= e(
                                                        mb_strimwidth(
                                                            $article['excerpt'],
                                                            0,
                                                            150,
                                                            '...'
                                                        )
                                                    ) ?>
                                    </p>

                                    <?php endif; ?>


                                    <div class="article-meta">

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

                            </article>

                        </div>

                        <?php endforeach; ?>

                    </div>

                    <?php endif; ?>


                    <!-- PAGINATION -->

                    <?php if ($totalPages > 1): ?>

                    <nav aria-label="Pagination des articles">

                        <ul class="pagination justify-content-center">

                            <!-- Précédent -->

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                                <a class="page-link"
                                    href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>&page=<?= $page - 1 ?>"
                                    aria-label="Précédent">
                                    <i class="bi bi-chevron-left"></i>
                                </a>

                            </li>


                            <?php

                                /*
                                |--------------------------------------------------------------------------
                                | Pages visibles
                                |--------------------------------------------------------------------------
                                */

                                $startPage = max(1, $page - 2);
                                $endPage = min(
                                    $totalPages,
                                    $page + 2
                                );

                                ?>

                            <?php if ($startPage > 1): ?>

                            <li class="page-item">

                                <a class="page-link"
                                    href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>&page=1">
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

                                <a class="page-link"
                                    href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>&page=<?= $i ?>">
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

                                <a class="page-link"
                                    href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>&page=<?= $totalPages ?>">
                                    <?= $totalPages ?>
                                </a>

                            </li>

                            <?php endif; ?>


                            <!-- Suivant -->

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                                <a class="page-link"
                                    href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>&page=<?= $page + 1 ?>"
                                    aria-label="Suivant">
                                    <i class="bi bi-chevron-right"></i>
                                </a>

                            </li>

                        </ul>

                    </nav>

                    <?php endif; ?>

                </div>


                <!-- SIDEBAR -->

                <aside class="col-lg-4">


                    <!-- PLUS LUS -->

                    <div class="sidebar-box">

                        <h3 class="sidebar-title">
                            <i class="bi bi-fire text-danger"></i>
                            Plus lus
                        </h3>


                        <?php if (!empty($popularArticles)): ?>

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

                        <?php else: ?>

                        <p class="text-muted mb-0">
                            Aucun article populaire.
                        </p>

                        <?php endif; ?>

                    </div>


                    <!-- RUBRIQUES -->

                    <div class="sidebar-box">

                        <h3 class="sidebar-title">
                            Rubriques
                        </h3>


                        <?php foreach ($categories as $otherCategory): ?>

                        <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($otherCategory['slug']) ?>"
                            class="category-link <?= $otherCategory['id'] == $category['id'] ? 'active' : '' ?>">

                            <span>

                                <?= e($otherCategory['name']) ?>

                            </span>

                            <span class="category-badge">

                                <?= (int) $otherCategory['articles_count'] ?>

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
                            Recevez les principales actualités
                            directement dans votre boîte mail.
                        </p>

                        <form>

                            <input type="email" class="form-control mb-2" placeholder="Votre adresse email">

                            <button type="submit" class="btn btn-dark w-100">
                                S'inscrire
                            </button>

                        </form>

                    </div>

                </aside>

            </div>

            <?php else: ?>

            <!-- AUCUN ARTICLE -->

            <div class="empty-state">

                <i class="bi bi-newspaper"></i>

                <h2 class="mt-3">
                    Aucun article disponible
                </h2>

                <p class="text-muted">
                    Cette rubrique ne contient actuellement
                    aucun article publié.
                </p>

                <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-danger">
                    <i class="bi bi-house"></i>
                    Retour à l'accueil
                </a>

            </div>

            <?php endif; ?>

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