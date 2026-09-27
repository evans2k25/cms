<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

$database = new Database();
$db = $database->connect();

/*
|--------------------------------------------------------------------------
| Paramètres
|--------------------------------------------------------------------------
*/

$perPage = 9;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| Article à la une
|--------------------------------------------------------------------------
*/

$featuredStmt = $db->query("
    SELECT
        a.*,
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

    WHERE a.status = 'published'
      AND a.featured = 1
      AND (
          a.published_at IS NULL
          OR a.published_at <= NOW()
      )

    ORDER BY a.published_at DESC, a.created_at DESC

    LIMIT 1
");

$featuredArticle = $featuredStmt->fetch();

/*
|--------------------------------------------------------------------------
| Derniers articles
|--------------------------------------------------------------------------
*/

$latestStmt = $db->prepare("
    SELECT
        a.*,
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

    WHERE a.status = 'published'
      AND (
          a.published_at IS NULL
          OR a.published_at <= NOW()
      )

    ORDER BY
        a.published_at DESC,
        a.created_at DESC

    LIMIT :limit OFFSET :offset
");

$latestStmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$latestStmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$latestStmt->execute();

$articles = $latestStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Total articles
|--------------------------------------------------------------------------
*/

$countStmt = $db->query("
    SELECT COUNT(*)
    FROM articles
    WHERE status = 'published'
      AND (
          published_at IS NULL
          OR published_at <= NOW()
      )
");

$totalArticles = (int)$countStmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil($totalArticles / $perPage)
);

/*
|--------------------------------------------------------------------------
| Catégories
|--------------------------------------------------------------------------
*/

$categoryStmt = $db->query("
    SELECT
        c.*,
        COUNT(a.id) AS articles_count
    FROM categories c

    LEFT JOIN articles a
        ON a.category_id = c.id
        AND a.status = 'published'

    WHERE c.status = 'active'

    GROUP BY c.id

    ORDER BY c.name ASC
");

$categories = $categoryStmt->fetchAll();

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
        a.featured_image,
        a.views,
        c.name AS category_name

    FROM articles a

    LEFT JOIN categories c
        ON c.id = a.category_id

    WHERE a.status = 'published'

    ORDER BY a.views DESC

    LIMIT 5
");

$popularArticles = $popularStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Fonctions
|--------------------------------------------------------------------------
*/

function articleAuthor(array $article): string
{
    $name = trim(
        ($article['first_name'] ?? '')
        . ' '
        . ($article['last_name'] ?? '')
    );

    return $name !== ''
        ? $name
        : ($article['username'] ?? 'Rédaction');
}


function articleDate(array $article): string
{
    $date = $article['published_at']
        ?: $article['created_at'];

    return date(
        'd/m/Y à H:i',
        strtotime($date)
    );
}


function articleImage(
    ?string $image,
    string $class = ''
): string {

    if (!$image) {

        return '
        <div class="article-placeholder ' . $class . '">
            <i class="bi bi-newspaper"></i>
        </div>';

    }

    return '
    <img
        src="' . BASE_URL . '/public/'
        . htmlspecialchars($image)
        . '"
        class="' . $class . '"
        alt="Article"
        loading="lazy"
    >';
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="<?= htmlspecialchars(
        'Actualités, informations et analyses - '
        . APP_NAME
    ) ?>">

    <title>
        <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    /*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

    :root {
        --primary: #c62828;
        --primary-dark: #8e0000;
        --dark: #171717;
        --light: #f6f6f6;
        --border: #e8e8e8;
    }

    body {
        margin: 0;
        background: #f7f7f7;
        color: #202020;
        font-family:
            Arial,
            Helvetica,
            sans-serif;
    }

    a {
        text-decoration: none;
    }


    /*
|--------------------------------------------------------------------------
| TOP BAR
|--------------------------------------------------------------------------
*/

    .top-bar {
        background: var(--dark);
        color: #fff;
        font-size: .85rem;
    }

    .top-bar a {
        color: #fff;
        opacity: .9;
    }


    /*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

    .main-header {
        background: #fff;
        border-bottom: 1px solid var(--border);
    }

    .logo {
        font-size: 2rem;
        font-weight: 900;
        color: var(--dark);
        letter-spacing: -1px;
    }

    .logo span {
        color: var(--primary);
    }

    .logo small {
        display: block;
        font-size: .65rem;
        color: #777;
        letter-spacing: 2px;
        text-transform: uppercase;
    }


    /*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

    .main-nav {
        background: #fff;
        border-bottom: 3px solid var(--primary);
    }

    .main-nav .nav-link {
        color: #222;
        font-weight: 600;
        padding: 15px 16px;
    }

    .main-nav .nav-link:hover {
        color: var(--primary);
    }


    /*
|--------------------------------------------------------------------------
| BREAKING
|--------------------------------------------------------------------------
*/

    .breaking {
        background: var(--primary);
        color: #fff;
    }

    .breaking-label {
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
    }


    /*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        border-left: 5px solid var(--primary);
        padding-left: 12px;
        margin-bottom: 25px;
    }


    /*
|--------------------------------------------------------------------------
| FEATURED
|--------------------------------------------------------------------------
*/

    .featured-card {
        position: relative;
        min-height: 500px;
        border-radius: 18px;
        overflow: hidden;
        background: #222;
    }

    .featured-card img,
    .featured-card .article-placeholder {
        width: 100%;
        height: 500px;
        object-fit: cover;
    }

    .featured-card::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(to top,
                rgba(0, 0, 0, .85),
                rgba(0, 0, 0, .05));
    }

    .featured-content {
        position: absolute;
        z-index: 2;
        left: 35px;
        right: 35px;
        bottom: 30px;
        color: #fff;
    }

    .featured-content h1 {
        font-size: clamp(2rem, 4vw, 3.4rem);
        font-weight: 900;
        line-height: 1.05;
    }


    /*
|--------------------------------------------------------------------------
| ARTICLE CARD
|--------------------------------------------------------------------------
*/

    .article-card {
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--border);
        height: 100%;
        transition: transform .2s, box-shadow .2s;
    }

    .article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
    }

    .article-card-image {
        width: 100%;
        height: 210px;
        object-fit: cover;
    }

    .article-placeholder {
        background:
            linear-gradient(135deg,
                #eee,
                #ddd);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #aaa;
        font-size: 3rem;
    }

    .article-card-body {
        padding: 20px;
    }

    .article-card-title {
        color: #202020;
        font-size: 1.2rem;
        font-weight: 800;
        line-height: 1.3;
    }

    .article-card-title:hover {
        color: var(--primary);
    }


    /*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

    .sidebar-box {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 25px;
    }

    .popular-item {
        display: flex;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
    }

    .popular-item:last-child {
        border-bottom: 0;
    }

    .popular-number {
        font-size: 1.5rem;
        font-weight: 900;
        color: var(--primary);
    }


    /*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

    footer {
        background: #171717;
        color: #ddd;
        margin-top: 60px;
    }

    footer a {
        color: #bbb;
    }

    footer a:hover {
        color: #fff;
    }


    /*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

    @media (max-width: 768px) {

        .featured-card,
        .featured-card img,
        .featured-card .article-placeholder {
            min-height: 400px;
            height: 400px;
        }

        .featured-content {
            left: 20px;
            right: 20px;
        }

        .featured-content h1 {
            font-size: 2rem;
        }

        .logo {
            font-size: 1.6rem;
        }

    }
    </style>

</head>


<body>


    <!-- =========================================================
     TOP BAR
========================================================= -->

    <div class="top-bar">

        <div class="container">

            <div class="d-flex justify-content-between py-2">

                <div>
                    <i class="bi bi-calendar3"></i>
                    <?= date('l d F Y') ?>
                </div>

                <div>
                    <a href="<?= BASE_URL ?>/admin/login.php">
                        <i class="bi bi-person"></i>
                        Espace rédaction
                    </a>
                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
     HEADER
========================================================= -->

    <header class="main-header">

        <div class="container">

            <div class="d-flex
            justify-content-between
            align-items-center
            py-4">

                <a href="<?= BASE_URL ?>/public/index.php" class="logo">

                    <?= htmlspecialchars(APP_NAME) ?>

                    <span>.</span>

                    <small>
                        L'information au cœur de l'actualité
                    </small>

                </a>


                <form action="<?= BASE_URL ?>/public/search.php" method="GET" class="d-none d-md-flex">

                    <div class="input-group">

                        <input type="search" name="q" class="form-control" placeholder="Rechercher...">

                        <button class="btn btn-dark" type="submit">

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </header>


    <!-- =========================================================
     NAVIGATION
========================================================= -->

    <nav class="main-nav">

        <div class="container">

            <div class="d-flex overflow-auto">

                <a class="nav-link" href="<?= BASE_URL ?>/public/index.php">
                    Accueil
                </a>

                <?php foreach ($categories as $category): ?>

                <a class="nav-link" href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>">

                    <?= htmlspecialchars($category['name']) ?>

                </a>

                <?php endforeach; ?>

            </div>

        </div>

    </nav>


    <!-- =========================================================
     BREAKING NEWS
========================================================= -->

    <div class="breaking">

        <div class="container">

            <div class="d-flex align-items-center gap-3 py-2">

                <span class="breaking-label">
                    <i class="bi bi-lightning-fill"></i>
                    À la une
                </span>

                <span class="small">
                    Les dernières informations de votre journal
                </span>

            </div>

        </div>

    </div>


    <!-- =========================================================
     MAIN
========================================================= -->

    <main class="container py-5">


        <?php if ($featuredArticle): ?>

        <section class="mb-5">

            <div class="section-title">
                Article à la une
            </div>

            <a href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($featuredArticle['slug']) ?>">

                <div class="featured-card">

                    <?= articleImage(
    $featuredArticle['featured_image'],
    ''
) ?>


                    <div class="featured-content">

                        <?php if ($featuredArticle['category_name']): ?>

                        <span class="badge bg-danger mb-3">

                            <?= htmlspecialchars(
    $featuredArticle['category_name']
) ?>

                        </span>

                        <?php endif; ?>


                        <h1>

                            <?= htmlspecialchars(
    $featuredArticle['title']
) ?>

                        </h1>


                        <?php if ($featuredArticle['excerpt']): ?>

                        <p class="lead mb-3">

                            <?= htmlspecialchars(
    $featuredArticle['excerpt']
) ?>

                        </p>

                        <?php endif; ?>


                        <div class="small">

                            <i class="bi bi-person"></i>

                            <?= htmlspecialchars(
    articleAuthor($featuredArticle)
) ?>

                            &nbsp;&nbsp;

                            <i class="bi bi-clock"></i>

                            <?= articleDate($featuredArticle) ?>

                        </div>

                    </div>

                </div>

            </a>

        </section>

        <?php endif; ?>


        <!-- =========================================================
     DERNIERS ARTICLES
========================================================= -->

        <div class="row g-4">

            <div class="col-lg-8">

                <div class="section-title">
                    Dernières actualités
                </div>


                <?php if (!$articles): ?>

                <div class="alert alert-light border">

                    <i class="bi bi-info-circle"></i>

                    Aucun article publié pour le moment.

                </div>

                <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($articles as $article): ?>

                    <div class="col-md-6">

                        <article class="article-card">

                            <a href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($article['slug']) ?>">

                                <?= articleImage(
    $article['featured_image'],
    'article-card-image'
) ?>

                            </a>


                            <div class="article-card-body">

                                <?php if ($article['category_name']): ?>

                                <div class="mb-2">

                                    <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($article['category_slug']) ?>"
                                        class="badge text-bg-danger">

                                        <?= htmlspecialchars(
    $article['category_name']
) ?>

                                    </a>

                                </div>

                                <?php endif; ?>


                                <a href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($article['slug']) ?>"
                                    class="article-card-title d-block mb-2">

                                    <?= htmlspecialchars(
    $article['title']
) ?>

                                </a>


                                <?php if ($article['excerpt']): ?>

                                <p class="text-muted small mb-3">

                                    <?= htmlspecialchars(
    mb_strimwidth(
        $article['excerpt'],
        0,
        130,
        '...'
    )
) ?>

                                </p>

                                <?php endif; ?>


                                <div class="small text-muted">

                                    <i class="bi bi-person"></i>

                                    <?= htmlspecialchars(
    articleAuthor($article)
) ?>

                                    <br>

                                    <i class="bi bi-clock"></i>

                                    <?= articleDate($article) ?>

                                </div>

                            </div>

                        </article>

                    </div>

                    <?php endforeach; ?>

                </div>


                <!-- PAGINATION -->

                <?php if ($totalPages > 1): ?>

                <nav class="mt-5">

                    <ul class="pagination justify-content-center">

                        <?php for (
    $i = 1;
    $i <= $totalPages;
    $i++
): ?>

                        <li class="page-item
<?= $i === $page ? 'active' : '' ?>">

                            <a class="page-link" href="?page=<?= $i ?>">

                                <?= $i ?>

                            </a>

                        </li>

                        <?php endfor; ?>

                    </ul>

                </nav>

                <?php endif; ?>

                <?php endif; ?>

            </div>


            <!-- =========================================================
     SIDEBAR
========================================================= -->

            <div class="col-lg-4">


                <!-- POPULAIRES -->

                <div class="sidebar-box">

                    <div class="section-title mb-3">
                        Les plus lus
                    </div>


                    <?php foreach (
    $popularArticles as $index => $popular
): ?>

                    <div class="popular-item">

                        <div class="popular-number">

                            <?= str_pad(
    $index + 1,
    2,
    '0',
    STR_PAD_LEFT
) ?>

                        </div>


                        <div>

                            <?php if ($popular['category_name']): ?>

                            <div class="small text-danger fw-semibold">

                                <?= htmlspecialchars(
    $popular['category_name']
) ?>

                            </div>

                            <?php endif; ?>


                            <a href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($popular['slug']) ?>"
                                class="fw-bold text-dark">

                                <?= htmlspecialchars(
    $popular['title']
) ?>

                            </a>


                            <div class="small text-muted mt-1">

                                <i class="bi bi-eye"></i>

                                <?= number_format(
    (int)$popular['views'],
    0,
    ',',
    ' '
) ?>

                                vues

                            </div>

                        </div>

                    </div>

                    <?php endforeach; ?>

                </div>


                <!-- CATÉGORIES -->

                <div class="sidebar-box">

                    <div class="section-title mb-3">
                        Rubriques
                    </div>

                    <?php foreach ($categories as $category): ?>

                    <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>" class="d-flex
           justify-content-between
           align-items-center
           py-2
           border-bottom
           text-dark">

                        <span>

                            <?= htmlspecialchars(
    $category['name']
) ?>

                        </span>

                        <span class="badge bg-light text-dark">

                            <?= (int)$category['articles_count'] ?>

                        </span>

                    </a>

                    <?php endforeach; ?>

                </div>


                <!-- NEWSLETTER -->

                <div class="sidebar-box">

                    <div class="section-title mb-3">
                        Restez informé
                    </div>

                    <p class="text-muted small">

                        Retrouvez les dernières actualités
                        et analyses de notre rédaction.

                    </p>

                    <a href="#" class="btn btn-dark w-100">

                        <i class="bi bi-envelope"></i>

                        Suivre l'actualité

                    </a>

                </div>

            </div>

        </div>

    </main>


    <!-- =========================================================
     FOOTER
========================================================= -->

    <footer>

        <div class="container py-5">

            <div class="row g-4">

                <div class="col-md-5">

                    <h4 class="fw-bold text-white">

                        <?= htmlspecialchars(APP_NAME) ?>

                        <span class="text-danger">.</span>

                    </h4>

                    <p class="text-secondary">

                        Votre source d'information pour suivre
                        l'actualité, comprendre les événements
                        et découvrir les sujets qui façonnent
                        notre quotidien.

                    </p>

                </div>


                <div class="col-md-3">

                    <h6 class="text-white fw-bold">
                        Rubriques
                    </h6>

                    <?php foreach (
    array_slice($categories, 0, 5)
    as $category
): ?>

                    <a class="d-block py-1"
                        href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($category['slug']) ?>">

                        <?= htmlspecialchars(
    $category['name']
) ?>

                    </a>

                    <?php endforeach; ?>

                </div>


                <div class="col-md-4">

                    <h6 class="text-white fw-bold">
                        Navigation
                    </h6>

                    <a class="d-block py-1" href="<?= BASE_URL ?>/public/index.php">
                        Accueil
                    </a>

                    <a class="d-block py-1" href="<?= BASE_URL ?>/public/search.php">
                        Recherche
                    </a>

                    <a class="d-block py-1" href="<?= BASE_URL ?>/admin/login.php">
                        Espace rédaction
                    </a>

                </div>

            </div>


            <hr class="border-secondary my-4">


            <div class="text-center text-secondary small">

                © <?= date('Y') ?>

                <?= htmlspecialchars(APP_NAME) ?>.

                Tous droits réservés.

            </div>

        </div>

    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>