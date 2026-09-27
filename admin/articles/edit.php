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

/*
|--------------------------------------------------------------------------
| Fonctions
|--------------------------------------------------------------------------
*/

function generateArticleSlugEdit(string $text): string
{
    $text = trim($text);

    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

    if ($converted !== false) {
        $text = $converted;
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}

function uniqueArticleSlugEdit(
    PDO $db,
    string $slug,
    int $articleId
): string {

    $baseSlug = $slug;
    $counter = 1;

    while (true) {

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM articles
            WHERE slug = :slug
            AND id != :id
        ");

        $stmt->execute([
            ':slug' => $slug,
            ':id' => $articleId
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }

        $counter++;

        $slug = $baseSlug . '-' . $counter;
    }
}

/*
|--------------------------------------------------------------------------
| Article
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM articles
    WHERE id = :id
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

/*
|--------------------------------------------------------------------------
| Tags
|--------------------------------------------------------------------------
*/

$tagStmt = $db->query("
    SELECT id, name
    FROM tags
    ORDER BY name ASC
");

$tags = $tagStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Tags de l'article
|--------------------------------------------------------------------------
*/

$articleTagStmt = $db->prepare("
    SELECT tag_id
    FROM article_tags
    WHERE article_id = :article_id
");

$articleTagStmt->execute([
    ':article_id' => $id
]);

$selectedTags = array_map(
    'intval',
    $articleTagStmt->fetchAll(PDO::FETCH_COLUMN)
);

$error = '';

/*
|--------------------------------------------------------------------------
| Mise à jour
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $categoryId = !empty($_POST['category_id'])
        ? (int)$_POST['category_id']
        : null;

    $status = $_POST['status'] ?? 'draft';

    $featured = isset($_POST['featured']) ? 1 : 0;

    $scheduledAt = !empty($_POST['scheduled_at'])
        ? $_POST['scheduled_at']
        : null;

    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $metaKeywords = trim($_POST['meta_keywords'] ?? '');

    $selectedTags = array_map(
        'intval',
        $_POST['tags'] ?? []
    );

    if ($title === '') {

        $error = 'Le titre est obligatoire.';

    } elseif ($content === '') {

        $error = 'Le contenu est obligatoire.';

    } elseif (!in_array(
        $status,
        ['draft', 'pending', 'published', 'scheduled', 'archived'],
        true
    )) {

        $error = 'Statut invalide.';

    } elseif (
        $status === 'scheduled'
        && !$scheduledAt
    ) {

        $error = 'Veuillez choisir une date de programmation.';

    }

    /*
    |--------------------------------------------------------------------------
    | Image
    |--------------------------------------------------------------------------
    */

    $featuredImage = $article['featured_image'];

    if (
        $error === ''
        && isset($_FILES['featured_image'])
        && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES['featured_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {

            $error = 'Erreur lors de l’envoi de l’image.';

        } elseif ($file['size'] > 5 * 1024 * 1024) {

            $error = 'L’image ne doit pas dépasser 5 Mo.';

        } else {

            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            if (!isset($allowed[$mime])) {

                $error = 'Format d’image non autorisé.';

            } else {

                $uploadDir =
                    __DIR__ .
                    '/../../public/uploads/articles/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename =
                    bin2hex(random_bytes(16))
                    . '.'
                    . $allowed[$mime];

                if (!move_uploaded_file(
                    $file['tmp_name'],
                    $uploadDir . $filename
                )) {

                    $error = 'Impossible d’enregistrer l’image.';

                } else {

                    /*
                     * Suppression de l'ancienne image
                     */

                    if (!empty($article['featured_image'])) {

                        $oldImage =
                            __DIR__
                            . '/../../public/'
                            . $article['featured_image'];

                        if (
                            is_file($oldImage)
                            && str_starts_with(
                                realpath($oldImage) ?: '',
                                realpath($uploadDir) ?: ''
                            )
                        ) {
                            unlink($oldImage);
                        }
                    }

                    $featuredImage =
                        'uploads/articles/' . $filename;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Enregistrement
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $db->beginTransaction();

            /*
             * Slug
             */

            $slug = generateArticleSlugEdit($title);

            if ($slug === '') {
                throw new Exception(
                    'Impossible de générer le slug.'
                );
            }

            $slug = uniqueArticleSlugEdit(
                $db,
                $slug,
                $id
            );

            /*
             * Dates
             */

            $publishedAt = $article['published_at'];

            if ($status === 'published') {

                if (!$publishedAt) {
                    $publishedAt = date('Y-m-d H:i:s');
                }

            } elseif ($status !== 'archived') {

                $publishedAt = null;
            }

            if ($status !== 'scheduled') {
                $scheduledAt = null;
            }

            /*
             * Article
             */

            $update = $db->prepare("
                UPDATE articles
                SET
                    category_id = :category_id,
                    title = :title,
                    slug = :slug,
                    excerpt = :excerpt,
                    content = :content,
                    featured_image = :featured_image,
                    status = :status,
                    featured = :featured,
                    published_at = :published_at,
                    scheduled_at = :scheduled_at,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    meta_keywords = :meta_keywords
                WHERE id = :id
            ");

            $update->execute([
                ':category_id' => $categoryId,
                ':title' => $title,
                ':slug' => $slug,
                ':excerpt' => $excerpt ?: null,
                ':content' => $content,
                ':featured_image' => $featuredImage,
                ':status' => $status,
                ':featured' => $featured,
                ':published_at' => $publishedAt,
                ':scheduled_at' => $scheduledAt,
                ':meta_title' => $metaTitle ?: null,
                ':meta_description' => $metaDescription ?: null,
                ':meta_keywords' => $metaKeywords ?: null,
                ':id' => $id
            ]);

            /*
             * Tags
             */

            $deleteTags = $db->prepare("
                DELETE FROM article_tags
                WHERE article_id = :article_id
            ");

            $deleteTags->execute([
                ':article_id' => $id
            ]);

            if (!empty($selectedTags)) {

                $insertTag = $db->prepare("
                    INSERT INTO article_tags
                    (article_id, tag_id)
                    VALUES
                    (:article_id, :tag_id)
                ");

                foreach ($selectedTags as $tagId) {

                    if ($tagId > 0) {

                        $insertTag->execute([
                            ':article_id' => $id,
                            ':tag_id' => $tagId
                        ]);
                    }
                }
            }

            $db->commit();

            header(
                'Location: edit.php?id='
                . $id
                . '&updated=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error =
                'Erreur lors de la modification : '
                . $e->getMessage();
        }
    }

    /*
     * Recharger les valeurs affichées
     */

    $article['title'] = $title;
    $article['excerpt'] = $excerpt;
    $article['content'] = $content;
    $article['category_id'] = $categoryId;
    $article['status'] = $status;
    $article['featured'] = $featured;
    $article['scheduled_at'] = $scheduledAt;
    $article['meta_title'] = $metaTitle;
    $article['meta_description'] = $metaDescription;
    $article['meta_keywords'] = $metaKeywords;
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        Modifier l'article -
        <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">

    <style>
    body {
        background: #f5f7fb;
    }

    .card-editor,
    .side-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .05);
    }

    .side-card {
        margin-bottom: 20px;
    }

    #editor {
        min-height: 400px;
    }

    .current-image {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: 12px;
        margin-bottom: 12px;
    }

    .tag-box {
        max-height: 220px;
        overflow-y: auto;
    }
    </style>

</head>

<body>

    <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">
                    <i class="bi bi-pencil-square"></i>
                    Modifier l'article
                </h2>

                <p class="text-muted mb-0">
                    <?= htmlspecialchars($article['title']) ?>
                </p>

            </div>

            <div class="d-flex gap-2">

                <a href="preview.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                    Aperçu
                </a>

                <a href="index.php" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-left"></i>
                    Retour
                </a>

            </div>

        </div>


        <?php if ($error): ?>

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>

        <?php endif; ?>


        <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            Article mis à jour avec succès.
        </div>

        <?php endif; ?>


        <form method="POST" enctype="multipart/form-data" id="articleForm">

            <div class="row g-4">

                <div class="col-lg-8">

                    <div class="card-editor">

                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Titre *
                            </label>

                            <input type="text" name="title" class="form-control form-control-lg"
                                value="<?= htmlspecialchars($article['title']) ?>" required>

                        </div>


                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Extrait
                            </label>

                            <textarea name="excerpt" class="form-control"
                                rows="3"><?= htmlspecialchars($article['excerpt'] ?? '') ?></textarea>

                        </div>


                        <label class="form-label fw-semibold">
                            Contenu *
                        </label>

                        <div id="editor"></div>

                        <textarea name="content" id="content"
                            hidden><?= htmlspecialchars($article['content']) ?></textarea>

                    </div>

                </div>


                <div class="col-lg-4">


                    <div class="side-card">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-send"></i>
                            Publication
                        </h5>

                        <select name="status" id="status" class="form-select mb-3">

                            <option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>
                                Brouillon
                            </option>

                            <option value="pending" <?= $article['status'] === 'pending' ? 'selected' : '' ?>>
                                En attente
                            </option>

                            <option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>
                                Publié
                            </option>

                            <option value="scheduled" <?= $article['status'] === 'scheduled' ? 'selected' : '' ?>>
                                Programmé
                            </option>

                            <option value="archived" <?= $article['status'] === 'archived' ? 'selected' : '' ?>>
                                Archivé
                            </option>

                        </select>


                        <div id="scheduleBox" style="<?= $article['status'] === 'scheduled'
        ? ''
        : 'display:none;' ?>">

                            <label class="form-label">
                                Date de publication
                            </label>

                            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= $article['scheduled_at']
        ? date('Y-m-d\TH:i', strtotime($article['scheduled_at']))
        : '' ?>">

                        </div>


                        <div class="form-check mt-3">

                            <input type="checkbox" name="featured" value="1" class="form-check-input" id="featured" <?= (int)$article['featured'] === 1
        ? 'checked'
        : '' ?>>

                            <label class="form-check-label" for="featured">
                                <i class="bi bi-star-fill text-warning"></i>
                                Article à la une
                            </label>

                        </div>

                    </div>


                    <div class="side-card">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-folder"></i>
                            Catégorie
                        </h5>

                        <select name="category_id" class="form-select">

                            <option value="">
                                Sans catégorie
                            </option>

                            <?php foreach ($categories as $category): ?>

                            <option value="<?= $category['id'] ?>" <?= (string)$article['category_id']
        === (string)$category['id']
        ? 'selected'
        : '' ?>>

                                <?= htmlspecialchars($category['name']) ?>

                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="side-card">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-tags"></i>
                            Tags
                        </h5>

                        <div class="tag-box">

                            <?php foreach ($tags as $tag): ?>

                            <div class="form-check mb-2">

                                <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="form-check-input"
                                    id="tag<?= $tag['id'] ?>" <?= in_array(
        (int)$tag['id'],
        $selectedTags,
        true
    ) ? 'checked' : '' ?>>

                                <label class="form-check-label" for="tag<?= $tag['id'] ?>">

                                    <?= htmlspecialchars($tag['name']) ?>

                                </label>

                            </div>

                            <?php endforeach; ?>

                        </div>

                    </div>


                    <div class="side-card">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-image"></i>
                            Image principale
                        </h5>

                        <?php if (!empty($article['featured_image'])): ?>

                        <img src="<?= BASE_URL ?>/public/<?= htmlspecialchars($article['featured_image']) ?>"
                            class="current-image" alt="Image actuelle">

                        <?php endif; ?>

                        <input type="file" name="featured_image" class="form-control"
                            accept="image/jpeg,image/png,image/webp,image/gif">

                        <small class="text-muted">
                            Laisser vide pour conserver l'image actuelle.
                        </small>

                    </div>


                    <div class="side-card">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-google"></i>
                            SEO
                        </h5>

                        <label class="form-label">
                            Meta titre
                        </label>

                        <input type="text" name="meta_title" class="form-control mb-3"
                            value="<?= htmlspecialchars($article['meta_title'] ?? '') ?>">

                        <label class="form-label">
                            Meta description
                        </label>

                        <textarea name="meta_description" class="form-control mb-3"
                            rows="3"><?= htmlspecialchars($article['meta_description'] ?? '') ?></textarea>

                        <label class="form-label">
                            Mots-clés
                        </label>

                        <input type="text" name="meta_keywords" class="form-control"
                            value="<?= htmlspecialchars($article['meta_keywords'] ?? '') ?>">

                    </div>


                    <button type="submit" class="btn btn-primary btn-lg w-100">

                        <i class="bi bi-save"></i>

                        Enregistrer les modifications

                    </button>

                </div>

            </div>

        </form>

    </div>


    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

    <script>
    const quill = new Quill('#editor', {

        theme: 'snow',

        modules: {
            toolbar: [
                [{
                    header: [1, 2, 3, false]
                }],
                ['bold', 'italic', 'underline', 'strike'],
                [{
                    list: 'ordered'
                }, {
                    list: 'bullet'
                }],
                [{
                    align: []
                }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        }

    });

    quill.root.innerHTML =
        document.getElementById('content').value;


    document.getElementById('articleForm')
        .addEventListener('submit', function() {

            document.getElementById('content').value =
                quill.root.innerHTML;

        });


    const status =
        document.getElementById('status');

    const scheduleBox =
        document.getElementById('scheduleBox');


    status.addEventListener('change', function() {

        scheduleBox.style.display =
            this.value === 'scheduled' ?
            'block' :
            'none';

    });
    </script>

</body>

</html>