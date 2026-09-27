<?php

session_start();

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

$database = new Database();
$db = $database->connect();

/*
|--------------------------------------------------------------------------
| Fonctions utilitaires
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDateArticle(?string $date): string
{
    if (!$date) {
        return '';
    }

    return date('d/m/Y à H:i', strtotime($date));
}

function articleImage(?string $image): string
{
    if (!$image) {
        return BASE_URL . '/public/assets/images/article-placeholder.jpg';
    }

    return BASE_URL . '/public/uploads/articles/' . rawurlencode($image);
}

/*
|--------------------------------------------------------------------------
| Récupération du slug
|--------------------------------------------------------------------------
*/

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    http_response_code(404);
    exit('Article introuvable.');
}

/*
|--------------------------------------------------------------------------
| Récupération de l'article
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        a.*,
        c.name AS category_name,
        c.slug AS category_slug,
        u.username,
        u.first_name,
        u.last_name,
        u.avatar
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    INNER JOIN users u ON u.id = a.user_id
    WHERE a.slug = :slug
      AND a.status = 'published'
      AND a.published_at IS NOT NULL
      AND a.published_at <= NOW()
    LIMIT 1
");

$stmt->execute([
    ':slug' => $slug
]);

$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    exit('Article introuvable.');
}

/*
|--------------------------------------------------------------------------
| Compteur de vues
|--------------------------------------------------------------------------
|
| On évite de compter plusieurs fois le même article
| pendant la même session.
|
*/

if (!isset($_SESSION['viewed_articles'])) {
    $_SESSION['viewed_articles'] = [];
}

if (!in_array($article['id'], $_SESSION['viewed_articles'], true)) {

    try {

        $db->beginTransaction();

        // Enregistrement détaillé de la vue
        $viewStmt = $db->prepare("
            INSERT INTO article_views
            (
                article_id,
                ip_address,
                user_agent
            )
            VALUES
            (
                :article_id,
                :ip_address,
                :user_agent
            )
        ");

        $viewStmt->execute([
            ':article_id' => $article['id'],
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Incrémentation du compteur
        $updateViews = $db->prepare("
            UPDATE articles
            SET views = views + 1
            WHERE id = :id
        ");

        $updateViews->execute([
            ':id' => $article['id']
        ]);

        $db->commit();

        $_SESSION['viewed_articles'][] = $article['id'];

        $article['views']++;

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Auteur
|--------------------------------------------------------------------------
*/

$author = trim(
    ($article['first_name'] ?? '') . ' ' .
    ($article['last_name'] ?? '')
);

if ($author === '') {
    $author = $article['username'];
}

/*
|--------------------------------------------------------------------------
| Tags
|--------------------------------------------------------------------------
*/

$tagStmt = $db->prepare("
    SELECT t.*
    FROM tags t
    INNER JOIN article_tags at
        ON at.tag_id = t.id
    WHERE at.article_id = :article_id
    ORDER BY t.name ASC
");

$tagStmt->execute([
    ':article_id' => $article['id']
]);

$tags = $tagStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Articles similaires
|--------------------------------------------------------------------------
*/

$relatedStmt = $db->prepare("
    SELECT
        a.id,
        a.title,
        a.slug,
        a.excerpt,
        a.featured_image,
        a.published_at,
        a.views,
        c.name AS category_name
    FROM articles a
    LEFT JOIN categories c
        ON c.id = a.category_id
    WHERE a.status = 'published'
      AND a.published_at IS NOT NULL
      AND a.published_at <= NOW()
      AND a.id != :article_id
      AND (
          a.category_id = :category_id
          OR a.category_id IS NULL
      )
    ORDER BY a.published_at DESC
    LIMIT 4
");

$relatedStmt->execute([
    ':article_id' => $article['id'],
    ':category_id' => $article['category_id']
]);

$relatedArticles = $relatedStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Commentaires
|--------------------------------------------------------------------------
*/

$commentMessage = '';
$commentError = '';

$settingsStmt = $db->query("
    SELECT setting_key, setting_value
    FROM settings
");

$settings = [];

foreach ($settingsStmt->fetchAll() as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$allowComments = ($settings['allow_comments'] ?? '1') === '1';

/*
|--------------------------------------------------------------------------
| Envoi d'un commentaire
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['submit_comment'])
) {

    if (!$allowComments) {

        $commentError = "Les commentaires sont actuellement désactivés.";

    } else {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($name === '' || $email === '' || $content === '') {

            $commentError = "Veuillez remplir tous les champs.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $commentError = "Veuillez saisir une adresse email valide.";

        } elseif (mb_strlen($name) > 150) {

            $commentError = "Le nom est trop long.";

        } elseif (mb_strlen($content) < 3) {

            $commentError = "Votre commentaire est trop court.";

        } else {

            $commentStmt = $db->prepare("
                INSERT INTO comments
                (
                    article_id,
                    name,
                    email,
                    content,
                    status
                )
                VALUES
                (
                    :article_id,
                    :name,
                    :email,
                    :content,
                    'pending'
                )
            ");

            $commentStmt->execute([
                ':article_id' => $article['id'],
                ':name' => $name,
                ':email' => $email,
                ':content' => $content
            ]);

            $commentMessage =
                "Votre commentaire a bien été envoyé. " .
                "Il sera publié après modération.";

            $_POST = [];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Commentaires approuvés
|--------------------------------------------------------------------------
*/

$comments = [];

if ($allowComments) {

    $commentsStmt = $db->prepare("
        SELECT
            id,
            name,
            content,
            created_at
        FROM comments
        WHERE article_id = :article_id
          AND status = 'approved'
        ORDER BY created_at DESC
    ");

    $commentsStmt->execute([
        ':article_id' => $article['id']
    ]);

    $comments = $commentsStmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

$metaTitle = $article['meta_title'] ?: $article['title'];

$metaDescription = $article['meta_description']
    ?: ($article['excerpt'] ?: 'Découvrez cet article sur ' . APP_NAME);

$canonicalUrl =
    BASE_URL . '/public/article.php?slug=' .
    rawurlencode($article['slug']);

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($metaTitle) ?> — <?= e(APP_NAME) ?></title>

    <meta name="description" content="<?= e($metaDescription) ?>">

    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="article">

    <meta property="og:title" content="<?= e($article['title']) ?>">

    <meta property="og:description" content="<?= e($metaDescription) ?>">

    <meta property="og:url" content="<?= e($canonicalUrl) ?>">

    <?php if ($article['featured_image']): ?>

    <meta property="og:image" content="<?= e(articleImage($article['featured_image'])) ?>">

    <?php endif; ?>

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
        background: #f7f7f7;
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
        font-size: .9rem;
        font-weight: 600;
    }

    .top-nav a:hover {
        color: #ffb3b3;
    }

    /* ARTICLE */

    .article-container {
        max-width: 1100px;
        margin: auto;
    }

    .article-card {
        background: #fff;
        padding: 40px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, .05);
    }

    .category-label {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 7px 14px;
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        text-decoration: none;
    }

    .article-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: clamp(2.2rem, 5vw, 4rem);
        line-height: 1.05;
        font-weight: 900;
        margin: 20px 0;
    }

    .article-excerpt {
        color: #666;
        font-size: 1.2rem;
        line-height: 1.7;
        border-left: 4px solid var(--primary);
        padding-left: 20px;
        margin-bottom: 30px;
    }

    .article-meta {
        color: #777;
        font-size: .9rem;
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
        padding: 15px 0;
    }

    .article-image {
        width: 100%;
        max-height: 600px;
        object-fit: cover;
        margin: 30px 0;
    }

    .article-content {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.15rem;
        line-height: 1.9;
    }

    .article-content h2,
    .article-content h3 {
        margin-top: 35px;
        margin-bottom: 15px;
        font-weight: 700;
    }

    .article-content img {
        max-width: 100%;
        height: auto;
    }

    .article-content blockquote {
        border-left: 5px solid var(--primary);
        background: var(--light-red);
        padding: 20px;
        margin: 25px 0;
        font-style: italic;
    }

    /* SHARE */

    .share-box {
        border-top: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        padding: 20px 0;
        margin-top: 40px;
    }

    .share-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        text-decoration: none;
        margin-right: 6px;
    }

    .share-facebook {
        background: #1877f2;
    }

    .share-whatsapp {
        background: #25d366;
    }

    .share-x {
        background: #000;
    }

    .share-copy {
        background: var(--primary);
        border: 0;
    }

    /* TAGS */

    .tag {
        display: inline-block;
        background: #f1f1f1;
        color: #444;
        padding: 6px 12px;
        margin: 4px;
        text-decoration: none;
        border-radius: 20px;
        font-size: .85rem;
    }

    .tag:hover {
        background: var(--primary);
        color: #fff;
    }

    /* RELATED */

    .related-card {
        background: #fff;
        height: 100%;
        transition: .2s;
    }

    .related-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .1);
    }

    .related-card img {
        width: 100%;
        height: 180px;
        object-fit: cover;
    }

    .related-card-body {
        padding: 18px;
    }

    .related-card h3 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .related-card a {
        color: #222;
        text-decoration: none;
    }

    /* COMMENTS */

    .comment-box {
        background: #fff;
        padding: 30px;
    }

    .comment-item {
        border-bottom: 1px solid #eee;
        padding: 20px 0;
    }

    .comment-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--light-red);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }

    /* FOOTER */

    footer {
        background: #151515;
        color: #aaa;
        margin-top: 60px;
    }

    footer a {
        color: #fff;
        text-decoration: none;
    }

    @media (max-width: 768px) {

        .article-card {
            padding: 22px;
        }

        .article-title {
            font-size: 2.3rem;
        }

        .article-content {
            font-size: 1.05rem;
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

                    <?php
                $navCategories = $db->query("
                    SELECT id, name, slug
                    FROM categories
                    WHERE status = 'active'
                    ORDER BY name
                    LIMIT 8
                ")->fetchAll();
                ?>

                    <?php foreach ($navCategories as $navCategory): ?>

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

        <div class="container article-container">

            <article class="article-card">

                <!-- Catégorie -->

                <?php if ($article['category_name']): ?>

                <a href="<?= BASE_URL ?>/public/category.php?slug=<?= urlencode($article['category_slug']) ?>"
                    class="category-label">
                    <?= e($article['category_name']) ?>
                </a>

                <?php endif; ?>


                <!-- Titre -->

                <h1 class="article-title">
                    <?= e($article['title']) ?>
                </h1>


                <!-- Extrait -->

                <?php if ($article['excerpt']): ?>

                <div class="article-excerpt">
                    <?= nl2br(e($article['excerpt'])) ?>
                </div>

                <?php endif; ?>


                <!-- Métadonnées -->

                <div class="article-meta">

                    <div class="d-flex flex-wrap gap-3">

                        <span>
                            <i class="bi bi-person"></i>
                            Par <strong><?= e($author) ?></strong>
                        </span>

                        <span>
                            <i class="bi bi-calendar3"></i>
                            <?= e(formatDateArticle($article['published_at'])) ?>
                        </span>

                        <span>
                            <i class="bi bi-eye"></i>
                            <?= number_format((int)$article['views'], 0, ',', ' ') ?>
                            vue<?= $article['views'] > 1 ? 's' : '' ?>
                        </span>

                    </div>

                </div>


                <!-- Image principale -->

                <?php if ($article['featured_image']): ?>

                <img src="<?= e(articleImage($article['featured_image'])) ?>" alt="<?= e($article['title']) ?>"
                    class="article-image">

                <?php endif; ?>


                <!-- Contenu -->

                <div class="article-content">

                    <?= $article['content'] ?>

                </div>


                <!-- Tags -->

                <?php if (!empty($tags)): ?>

                <div class="mt-5">

                    <h5 class="fw-bold mb-2">
                        <i class="bi bi-tags"></i>
                        Tags
                    </h5>

                    <?php foreach ($tags as $tag): ?>

                    <a href="<?= BASE_URL ?>/public/search.php?tag=<?= urlencode($tag['slug']) ?>" class="tag">
                        #<?= e($tag['name']) ?>
                    </a>

                    <?php endforeach; ?>

                </div>

                <?php endif; ?>


                <!-- Partage -->

                <div class="share-box">

                    <strong class="me-3">
                        Partager :
                    </strong>

                    <a class="share-btn share-facebook"
                        href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>"
                        target="_blank" rel="noopener noreferrer" aria-label="Partager sur Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>

                    <a class="share-btn share-whatsapp"
                        href="https://wa.me/?text=<?= urlencode($article['title'] . ' ' . $canonicalUrl) ?>"
                        target="_blank" rel="noopener noreferrer" aria-label="Partager sur WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>

                    <a class="share-btn share-x"
                        href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($canonicalUrl) ?>"
                        target="_blank" rel="noopener noreferrer" aria-label="Partager sur X">
                        <i class="bi bi-twitter-x"></i>
                    </a>

                    <button type="button" class="share-btn share-copy" onclick="copyArticleLink()"
                        aria-label="Copier le lien">
                        <i class="bi bi-link-45deg"></i>
                    </button>

                    <span id="copyMessage" class="text-success ms-2" style="display:none;">
                        Lien copié !
                    </span>

                </div>

            </article>


            <!-- ARTICLES SIMILAIRES -->

            <?php if (!empty($relatedArticles)): ?>

            <section class="mt-5">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h2 class="fw-bold mb-0">
                        À lire également
                    </h2>

                    <span class="text-muted">
                        <i class="bi bi-newspaper"></i>
                        Articles similaires
                    </span>

                </div>

                <div class="row g-4">

                    <?php foreach ($relatedArticles as $related): ?>

                    <div class="col-md-6 col-lg-3">

                        <article class="related-card">

                            <?php if ($related['featured_image']): ?>

                            <img src="<?= e(articleImage($related['featured_image'])) ?>"
                                alt="<?= e($related['title']) ?>">

                            <?php endif; ?>

                            <div class="related-card-body">

                                <?php if ($related['category_name']): ?>

                                <small class="text-danger fw-bold">
                                    <?= e($related['category_name']) ?>
                                </small>

                                <?php endif; ?>

                                <h3 class="mt-2">

                                    <a
                                        href="<?= BASE_URL ?>/public/article.php?slug=<?= urlencode($related['slug']) ?>">
                                        <?= e($related['title']) ?>
                                    </a>

                                </h3>

                                <small class="text-muted">
                                    <i class="bi bi-eye"></i>
                                    <?= number_format((int)$related['views'], 0, ',', ' ') ?>
                                </small>

                            </div>

                        </article>

                    </div>

                    <?php endforeach; ?>

                </div>

            </section>

            <?php endif; ?>


            <!-- COMMENTAIRES -->

            <?php if ($allowComments): ?>

            <section class="mt-5">

                <div class="comment-box">

                    <h2 class="fw-bold mb-4">
                        <i class="bi bi-chat-left-text"></i>
                        Commentaires
                    </h2>


                    <?php if ($commentMessage): ?>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <?= e($commentMessage) ?>
                    </div>

                    <?php endif; ?>


                    <?php if ($commentError): ?>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= e($commentError) ?>
                    </div>

                    <?php endif; ?>


                    <!-- Formulaire -->

                    <form method="POST" class="mb-5">

                        <div class="row g-3">

                            <div class="col-md-6">

                                <label class="form-label">
                                    Nom
                                </label>

                                <input type="text" name="name" class="form-control" maxlength="150" required
                                    value="<?= e($_POST['name'] ?? '') ?>">

                            </div>

                            <div class="col-md-6">

                                <label class="form-label">
                                    Email
                                </label>

                                <input type="email" name="email" class="form-control" maxlength="150" required
                                    value="<?= e($_POST['email'] ?? '') ?>">

                            </div>

                            <div class="col-12">

                                <label class="form-label">
                                    Votre commentaire
                                </label>

                                <textarea name="content" class="form-control" rows="5"
                                    required><?= e($_POST['content'] ?? '') ?></textarea>

                            </div>

                            <div class="col-12">

                                <button type="submit" name="submit_comment" class="btn btn-danger">
                                    <i class="bi bi-send"></i>
                                    Envoyer le commentaire
                                </button>

                            </div>

                        </div>

                    </form>


                    <!-- Liste -->

                    <?php if (!empty($comments)): ?>

                    <?php foreach ($comments as $comment): ?>

                    <div class="comment-item">

                        <div class="d-flex gap-3">

                            <div class="comment-avatar">

                                <?= e(
                                            strtoupper(
                                                mb_substr(
                                                    trim($comment['name']),
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>

                            </div>

                            <div class="flex-grow-1">

                                <div class="d-flex justify-content-between">

                                    <strong>
                                        <?= e($comment['name']) ?>
                                    </strong>

                                    <small class="text-muted">
                                        <?= e(
                                                    formatDateArticle(
                                                        $comment['created_at']
                                                    )
                                                ) ?>
                                    </small>

                                </div>

                                <p class="mt-2 mb-0">
                                    <?= nl2br(e($comment['content'])) ?>
                                </p>

                            </div>

                        </div>

                    </div>

                    <?php endforeach; ?>

                    <?php else: ?>

                    <div class="text-center text-muted py-4">

                        <i class="bi bi-chat-square-text fs-1"></i>

                        <p class="mt-2 mb-0">
                            Aucun commentaire pour le moment.
                        </p>

                    </div>

                    <?php endif; ?>

                </div>

            </section>

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
                © <?= date('Y') ?> <?= e(APP_NAME) ?> —
                Tous droits réservés.
            </div>

        </div>

    </footer>


    <script>
    function copyArticleLink() {

        const url = <?= json_encode($canonicalUrl) ?>;

        navigator.clipboard.writeText(url)
            .then(function() {

                const message =
                    document.getElementById('copyMessage');

                message.style.display = 'inline';

                setTimeout(function() {
                    message.style.display = 'none';
                }, 2500);

            })
            .catch(function() {

                alert("Impossible de copier le lien.");

            });

    }
    </script>

</body>

</html>