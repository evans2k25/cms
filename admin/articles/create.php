<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$error = '';

/*
|--------------------------------------------------------------------------
| Génération du slug
|--------------------------------------------------------------------------
*/

function generateArticleSlug(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    $converted = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $text
    );

    if ($converted !== false) {
        $text = $converted;
    }

    $text = strtolower($text);

    $text = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $text
    );

    return trim($text, '-');
}


/*
|--------------------------------------------------------------------------
| Vérifier l'unicité du slug
|--------------------------------------------------------------------------
*/

function uniqueArticleSlug(PDO $db, string $slug): string
{
    $baseSlug = $slug;
    $counter = 1;

    while (true) {

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM articles
            WHERE slug = :slug
        ");

        $stmt->execute([
            ':slug' => $slug
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }

        $counter++;

        $slug = $baseSlug . '-' . $counter;
    }
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
| Médiathèque
|--------------------------------------------------------------------------
*/

$mediaStmt = $db->query("
    SELECT
        id,
        original_name,
        file_path,
        mime_type,
        file_size,
        created_at
    FROM media
    WHERE mime_type LIKE 'image/%'
    ORDER BY created_at DESC
");

$mediaItems = $mediaStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Valeurs par défaut
|--------------------------------------------------------------------------
*/

$selectedMediaId = !empty($_POST['featured_media_id'])
    ? (int) $_POST['featured_media_id']
    : null;


/*
|--------------------------------------------------------------------------
| Création
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');

    $excerpt = trim($_POST['excerpt'] ?? '');

    $content = trim($_POST['content'] ?? '');

    $categoryId = !empty($_POST['category_id'])
        ? (int) $_POST['category_id']
        : null;

    $status = $_POST['status'] ?? 'draft';

    $featured = isset($_POST['featured'])
        ? 1
        : 0;

    $scheduledAt = !empty($_POST['scheduled_at'])
        ? $_POST['scheduled_at']
        : null;

    $metaTitle = trim($_POST['meta_title'] ?? '');

    $metaDescription = trim(
        $_POST['meta_description'] ?? ''
    );

    $metaKeywords = trim(
        $_POST['meta_keywords'] ?? ''
    );

    $selectedTags = $_POST['tags'] ?? [];

    $selectedMediaId = !empty($_POST['featured_media_id'])
        ? (int) $_POST['featured_media_id']
        : null;


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($title === '') {

        $error = 'Le titre de l’article est obligatoire.';

    } elseif ($content === '') {

        $error = 'Le contenu de l’article est obligatoire.';

    } elseif (!in_array(
        $status,
        [
            'draft',
            'pending',
            'published',
            'scheduled',
            'archived'
        ],
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
    | Image principale
    |--------------------------------------------------------------------------
    */

    $featuredImage = null;

    $uploadedNewImage = false;


    if (
        $error === ''
        && isset($_FILES['featured_image'])
    ) {

        $file = $_FILES['featured_image'];


        if (
            $file['error']
            !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $file['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Erreur lors de l’envoi de l’image.';

            } elseif (
                $file['size']
                > 5 * 1024 * 1024
            ) {

                $error =
                    'L’image ne doit pas dépasser 5 Mo.';

            } else {

                $allowedMimeTypes = [

                    'image/jpeg' => 'jpg',

                    'image/png' => 'png',

                    'image/webp' => 'webp',

                    'image/gif' => 'gif'

                ];


                $finfo = new finfo(
                    FILEINFO_MIME_TYPE
                );

                $mime = $finfo->file(
                    $file['tmp_name']
                );


                if (
                    !isset(
                        $allowedMimeTypes[$mime]
                    )
                ) {

                    $error =
                        'Format d’image non autorisé.';

                } else {

                    $extension =
                        $allowedMimeTypes[$mime];


                    $uploadDir = __DIR__
                        . '/../../public/uploads/articles/';


                    if (!is_dir($uploadDir)) {

                        if (
                            !mkdir(
                                $uploadDir,
                                0755,
                                true
                            )
                            && !is_dir($uploadDir)
                        ) {

                            $error =
                                'Impossible de créer le dossier d’upload.';
                        }
                    }


                    if ($error === '') {

                        $filename =
                            bin2hex(
                                random_bytes(16)
                            )
                            . '.'
                            . $extension;


                        $destination =
                            $uploadDir
                            . $filename;


                        if (
                            !move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )
                        ) {

                            $error =
                                'Impossible d’enregistrer l’image.';

                        } else {

                            $featuredImage =
                                'uploads/articles/'
                                . $filename;

                            $uploadedNewImage = true;

                            /*
                            | Si une nouvelle image est envoyée,
                            | elle devient prioritaire sur la médiathèque.
                            */
                            $selectedMediaId = null;
                        }
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Vérification de l'image de la médiathèque
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        && !$uploadedNewImage
        && $selectedMediaId !== null
    ) {

        $mediaCheck = $db->prepare("
            SELECT id
            FROM media
            WHERE id = :id
            AND mime_type LIKE 'image/%'
            LIMIT 1
        ");

        $mediaCheck->execute([
            ':id' => $selectedMediaId
        ]);

        if (!$mediaCheck->fetch()) {

            $error =
                'L’image sélectionnée dans la médiathèque est invalide.';

            $selectedMediaId = null;
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
            |--------------------------------------------------------------------------
            | Slug
            |--------------------------------------------------------------------------
            */

            $slug = generateArticleSlug(
                $title
            );


            if ($slug === '') {

                throw new Exception(
                    'Impossible de générer le slug.'
                );
            }


            $slug = uniqueArticleSlug(
                $db,
                $slug
            );


            /*
            |--------------------------------------------------------------------------
            | Utilisateur
            |--------------------------------------------------------------------------
            */

            $user = currentUser();

            if (!$user) {

                throw new Exception(
                    'Utilisateur non connecté.'
                );
            }

            $userId = (int) $user['id'];


            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */

            $publishedAt = null;


            if ($status === 'published') {

                $publishedAt =
                    date('Y-m-d H:i:s');
            }


            if ($status !== 'scheduled') {

                $scheduledAt = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Insertion article
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                INSERT INTO articles (
                    user_id,
                    category_id,
                    title,
                    slug,
                    excerpt,
                    content,
                    featured_image,
                    featured_media_id,
                    status,
                    featured,
                    published_at,
                    scheduled_at,
                    meta_title,
                    meta_description,
                    meta_keywords
                )
                VALUES (
                    :user_id,
                    :category_id,
                    :title,
                    :slug,
                    :excerpt,
                    :content,
                    :featured_image,
                    :featured_media_id,
                    :status,
                    :featured,
                    :published_at,
                    :scheduled_at,
                    :meta_title,
                    :meta_description,
                    :meta_keywords
                )
            ");


            $stmt->execute([

                ':user_id'
                    => $userId,

                ':category_id'
                    => $categoryId ?: null,

                ':title'
                    => $title,

                ':slug'
                    => $slug,

                ':excerpt'
                    => $excerpt ?: null,

                ':content'
                    => $content,

                ':featured_image'
                    => $featuredImage,

                ':featured_media_id'
                    => $selectedMediaId,

                ':status'
                    => $status,

                ':featured'
                    => $featured,

                ':published_at'
                    => $publishedAt,

                ':scheduled_at'
                    => $scheduledAt,

                ':meta_title'
                    => $metaTitle ?: null,

                ':meta_description'
                    => $metaDescription ?: null,

                ':meta_keywords'
                    => $metaKeywords ?: null
            ]);


            $articleId =
                (int) $db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Tags
            |--------------------------------------------------------------------------
            */

            if (!empty($selectedTags)) {

                $tagStmt = $db->prepare("
                    INSERT INTO article_tags (
                        article_id,
                        tag_id
                    )
                    VALUES (
                        :article_id,
                        :tag_id
                    )
                ");


                foreach (
                    $selectedTags
                    as $tagId
                ) {

                    $tagId = (int) $tagId;


                    if ($tagId > 0) {

                        $tagStmt->execute([

                            ':article_id'
                                => $articleId,

                            ':tag_id'
                                => $tagId
                        ]);
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Validation de la transaction
            |--------------------------------------------------------------------------
            */

            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirection
            |--------------------------------------------------------------------------
            */

            header(
                'Location: edit.php?id='
                . $articleId
                . '&created=1'
            );

            exit;


        } catch (Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }


            $error =
                'Erreur lors de la création de l’article : '
                . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        Nouvel article - <?= htmlspecialchars(APP_NAME) ?>
    </title>


    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">


    <!-- Quill -->

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">


    <style>
    body {
        background: #f5f7fb;
    }


    .editor-card {

        background: #fff;

        border-radius: 16px;

        padding: 25px;

        box-shadow:
            0 4px 20px rgba(0, 0, 0, .05);
    }


    .side-card {

        background: #fff;

        border-radius: 16px;

        padding: 20px;

        margin-bottom: 20px;

        box-shadow:
            0 4px 20px rgba(0, 0, 0, .05);
    }


    #editor {

        min-height: 400px;
    }


    .tag-box {

        max-height: 250px;

        overflow-y: auto;
    }


    /* Médiathèque */

    .media-picker-item {

        transition:
            transform .2s ease,
            box-shadow .2s ease,
            border-color .2s ease;

        border: 2px solid transparent;
    }


    .media-picker-item:hover {

        transform: translateY(-3px);

        box-shadow:
            0 8px 25px rgba(0, 0, 0, .12);

        border-color: #0d6efd;
    }


    .media-picker-item.selected {

        border-color: #198754;

        box-shadow:
            0 0 0 3px rgba(25,
                135,
                84,
                .15);
    }


    .media-thumbnail {

        width: 100%;

        height: 160px;

        object-fit: cover;

        display: block;
    }


    .selected-media-preview img {

        max-height: 220px;

        width: 100%;

        object-fit: cover;

        border-radius: 10px;
    }


    .image-choice {

        border: 1px solid #e5e7eb;

        border-radius: 12px;

        padding: 15px;

        height: 100%;

        background: #fafafa;
    }
    </style>

</head>


<body>


    <div class="container-fluid py-4">


        <!-- HEADER -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="bi bi-pencil-square"></i>

                    Nouvel article

                </h2>


                <p class="text-muted mb-0">

                    Rédigez et publiez un nouvel article.

                </p>

            </div>


            <a href="index.php" class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left"></i>

                Retour

            </a>

        </div>


        <!-- ERREUR -->

        <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

        <?php endif; ?>


        <form method="POST" enctype="multipart/form-data" id="articleForm">

            <div class="row g-4">


                <!-- ==================================================
                 COLONNE PRINCIPALE
            =================================================== -->

                <div class="col-lg-8">

                    <div class="editor-card">


                        <!-- TITRE -->

                        <div class="mb-4">

                            <label class="form-label fw-semibold">

                                Titre de l’article *

                            </label>


                            <input type="text" name="title" id="title" class="form-control form-control-lg"
                                placeholder="Titre de votre article..." value="<?= htmlspecialchars(
                                $_POST['title'] ?? ''
                            ) ?>" required>

                        </div>


                        <!-- EXTRAIT -->

                        <div class="mb-4">

                            <label class="form-label fw-semibold">

                                Extrait

                            </label>


                            <textarea name="excerpt" class="form-control" rows="3" maxlength="1000"
                                placeholder="Courte présentation de l'article..."><?= htmlspecialchars(
                            $_POST['excerpt'] ?? ''
                        ) ?></textarea>


                            <small class="text-muted">

                                Cet extrait sera utilisé sur la page
                                d'accueil.

                            </small>

                        </div>


                        <!-- CONTENU -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Contenu *

                            </label>


                            <div id="editor"></div>


                            <textarea name="content" id="content" hidden><?= htmlspecialchars(
                            $_POST['content'] ?? ''
                        ) ?></textarea>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                 COLONNE LATERALE
            =================================================== -->

                <div class="col-lg-4">


                    <!-- PUBLICATION -->

                    <div class="side-card">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-send"></i>

                            Publication

                        </h5>


                        <div class="mb-3">

                            <label class="form-label">

                                Statut

                            </label>


                            <select name="status" id="status" class="form-select">

                                <option value="draft" <?= (
                                    ($_POST['status'] ?? 'draft')
                                    === 'draft'
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                    Brouillon

                                </option>


                                <option value="pending" <?= (
                                    ($_POST['status'] ?? '')
                                    === 'pending'
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                    En attente

                                </option>


                                <option value="published" <?= (
                                    ($_POST['status'] ?? '')
                                    === 'published'
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                    Publié

                                </option>


                                <option value="scheduled" <?= (
                                    ($_POST['status'] ?? '')
                                    === 'scheduled'
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                    Programmé

                                </option>


                                <option value="archived" <?= (
                                    ($_POST['status'] ?? '')
                                    === 'archived'
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                    Archivé

                                </option>

                            </select>

                        </div>


                        <div class="mb-3" id="scheduleBox" style="display:none;">

                            <label class="form-label">

                                Date de publication

                            </label>


                            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= htmlspecialchars(
                                $_POST['scheduled_at'] ?? ''
                            ) ?>">

                        </div>


                        <div class="form-check">

                            <input type="checkbox" name="featured" value="1" class="form-check-input" id="featured" <?= isset($_POST['featured'])
                                ? 'checked'
                                : ''
                            ?>>


                            <label class="form-check-label" for="featured">

                                <i class="bi bi-star-fill text-warning"></i>

                                Article à la une

                            </label>

                        </div>

                    </div>


                    <!-- CATÉGORIE -->

                    <div class="side-card">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-folder"></i>

                            Catégorie

                        </h5>


                        <select name="category_id" class="form-select">

                            <option value="">

                                Sans catégorie

                            </option>


                            <?php foreach (
                            $categories
                            as $categoryItem
                        ): ?>

                            <option value="<?= $categoryItem['id'] ?>" <?= (
                                    (string) (
                                        $_POST['category_id']
                                        ?? ''
                                    )
                                    ===
                                    (string) $categoryItem['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>>

                                <?= htmlspecialchars(
                                    $categoryItem['name']
                                ) ?>

                            </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TAGS -->

                    <div class="side-card">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-tags"></i>

                            Tags

                        </h5>


                        <div class="tag-box">

                            <?php foreach (
                            $tags
                            as $tag
                        ): ?>

                            <?php
                            $isSelected =
                                !empty(
                                    $_POST['tags']
                                )
                                && in_array(
                                    (string) $tag['id'],
                                    array_map(
                                        'strval',
                                        $_POST['tags']
                                    ),
                                    true
                                );
                            ?>


                            <div class="form-check mb-2">

                                <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" class="form-check-input"
                                    id="tag<?= $tag['id'] ?>" <?= $isSelected
                                        ? 'checked'
                                        : ''
                                    ?>>


                                <label class="form-check-label" for="tag<?= $tag['id'] ?>">

                                    <?= htmlspecialchars(
                                        $tag['name']
                                    ) ?>

                                </label>

                            </div>

                            <?php endforeach; ?>

                        </div>


                        <?php if (!$tags): ?>

                        <p class="text-muted small mb-0">

                            Aucun tag disponible.

                        </p>

                        <?php endif; ?>

                    </div>


                    <!-- ==================================================
                     IMAGE PRINCIPALE
                =================================================== -->

                    <div class="side-card">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-image"></i>

                            Image principale

                        </h5>


                        <div class="row g-3">


                            <!-- NOUVELLE IMAGE -->

                            <div class="col-12">

                                <div class="image-choice">

                                    <label class="form-label fw-semibold">

                                        <i class="bi bi-cloud-upload me-1"></i>

                                        Téléverser une nouvelle image

                                    </label>


                                    <input type="file" name="featured_image" id="featuredImage" class="form-control"
                                        accept="image/jpeg,image/png,image/webp,image/gif">


                                    <small class="text-muted">

                                        JPG, PNG, WEBP ou GIF —
                                        maximum 5 Mo.

                                    </small>


                                    <div id="newImagePreview" class="mt-3"></div>

                                </div>

                            </div>


                            <!-- MÉDIATHÈQUE -->

                            <div class="col-12">

                                <div class="image-choice">

                                    <label class="form-label fw-semibold">

                                        <i class="bi bi-images me-1"></i>

                                        Utiliser la médiathèque

                                    </label>


                                    <input type="hidden" name="featured_media_id" id="featuredMediaId" value="<?= (int) (
                                        $selectedMediaId ?? 0
                                    ) ?>">


                                    <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal"
                                        data-bs-target="#mediaPickerModal">

                                        <i class="bi bi-collection me-2"></i>

                                        Choisir une image

                                    </button>


                                    <div id="selectedMediaPreview" class="selected-media-preview mt-3">

                                        <?php
                                    if ($selectedMediaId) {

                                        foreach (
                                            $mediaItems
                                            as $media
                                        ) {

                                            if (
                                                (int) $media['id']
                                                ===
                                                (int) $selectedMediaId
                                            ) {

                                                $previewUrl =
                                                    BASE_URL
                                                    . '/public/'
                                                    . ltrim(
                                                        $media['file_path'],
                                                        '/'
                                                    );
                                                ?>

                                        <div>

                                            <img src="<?= htmlspecialchars(
                                                            $previewUrl
                                                        ) ?>" alt="<?= htmlspecialchars(
                                                            $media['original_name']
                                                            ?? 'Image'
                                                        ) ?>">

                                            <div class="mt-2">

                                                <span class="badge bg-success">

                                                    <i class="bi bi-check-circle me-1"></i>

                                                    Image sélectionnée

                                                </span>

                                            </div>

                                        </div>

                                        <?php
                                                break;
                                            }
                                        }
                                    }
                                    ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- SEO -->

                    <div class="side-card">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-google"></i>

                            SEO

                        </h5>


                        <div class="mb-3">

                            <label class="form-label">

                                Meta titre

                            </label>


                            <input type="text" name="meta_title" class="form-control" maxlength="255" value="<?= htmlspecialchars(
                                $_POST['meta_title'] ?? ''
                            ) ?>">

                        </div>


                        <div class="mb-3">

                            <label class="form-label">

                                Meta description

                            </label>


                            <textarea name="meta_description" class="form-control" rows="3" maxlength="500"><?= htmlspecialchars(
                            $_POST['meta_description'] ?? ''
                        ) ?></textarea>

                        </div>


                        <div>

                            <label class="form-label">

                                Mots-clés

                            </label>


                            <input type="text" name="meta_keywords" class="form-control"
                                placeholder="actualité, Côte d'Ivoire..." value="<?= htmlspecialchars(
                                $_POST['meta_keywords'] ?? ''
                            ) ?>">

                        </div>

                    </div>


                    <!-- BOUTON -->

                    <button type="submit" class="btn btn-primary btn-lg w-100">

                        <i class="bi bi-check-lg"></i>

                        Enregistrer l’article

                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- ==========================================================
     MODAL MÉDIATHÈQUE
=========================================================== -->

    <div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

            <div class="modal-content">


                <div class="modal-header">

                    <h5 class="modal-title">

                        <i class="bi bi-images me-2"></i>

                        Choisir une image

                    </h5>


                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>


                <div class="modal-body">


                    <?php if (empty($mediaItems)): ?>

                    <div class="text-center py-5">

                        <i class="bi bi-images fs-1 text-muted"></i>


                        <h5 class="mt-3">

                            Votre médiathèque est vide

                        </h5>


                        <p class="text-muted">

                            Ajoutez d’abord une image
                            dans la médiathèque.

                        </p>


                        <a href="../media/upload.php" target="_blank" class="btn btn-primary">

                            <i class="bi bi-cloud-upload me-2"></i>

                            Ajouter une image

                        </a>

                    </div>

                    <?php else: ?>


                    <div class="row g-3">

                        <?php foreach (
                            $mediaItems
                            as $media
                        ): ?>

                        <?php
                            $mediaUrl =
                                BASE_URL
                                . '/public/'
                                . ltrim(
                                    $media['file_path'],
                                    '/'
                                );

                            $isSelected =
                                $selectedMediaId
                                &&
                                (int) $selectedMediaId
                                ===
                                (int) $media['id'];
                            ?>


                        <div class="col-6 col-md-4 col-lg-3">

                            <div class="media-picker-item card h-100 <?= $isSelected ? 'selected' : '' ?>"
                                data-id="<?= (int) $media['id'] ?>" data-url="<?= htmlspecialchars(
                                        $mediaUrl
                                    ) ?>" data-name="<?= htmlspecialchars(
                                        $media['original_name']
                                        ?? 'Image'
                                    ) ?>" style="cursor:pointer;">


                                <img src="<?= htmlspecialchars(
                                            $mediaUrl
                                        ) ?>" class="media-thumbnail" alt="<?= htmlspecialchars(
                                            $media['original_name']
                                            ?? 'Image'
                                        ) ?>">


                                <div class="card-body p-2">

                                    <small class="d-block text-truncate" title="<?= htmlspecialchars(
                                                $media['original_name']
                                                ?? 'Image'
                                            ) ?>">

                                        <?= htmlspecialchars(
                                                $media['original_name']
                                                ?? 'Image'
                                            ) ?>

                                    </small>


                                    <?php if ($isSelected): ?>

                                    <span class="badge bg-success mt-1">

                                        Sélectionnée

                                    </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                        <?php endforeach; ?>

                    </div>

                    <?php endif; ?>

                </div>


                <div class="modal-footer">

                    <a href="../media/upload.php" target="_blank" class="btn btn-outline-secondary">

                        <i class="bi bi-plus-lg me-1"></i>

                        Ajouter une image

                    </a>


                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                        Fermer

                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- Bootstrap -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Quill -->

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>


    <script>
    /*
|--------------------------------------------------------------------------
| Éditeur Quill
|--------------------------------------------------------------------------
*/

    const quill = new Quill(
        '#editor', {

            theme: 'snow',

            placeholder: 'Commencez à rédiger votre article...',

            modules: {

                toolbar: [

                    [{
                        header: [1, 2, 3, false]
                    }],

                    [
                        'bold',
                        'italic',
                        'underline',
                        'strike'
                    ],

                    [{
                            list: 'ordered'
                        },
                        {
                            list: 'bullet'
                        }
                    ],

                    [{
                        align: []
                    }],

                    [
                        'blockquote',
                        'code-block'
                    ],

                    [
                        'link'
                    ],

                    [
                        'clean'
                    ]
                ]
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Restaurer le contenu
    |--------------------------------------------------------------------------
    */

    const oldContent =
        document.getElementById('content').value;


    if (oldContent) {

        quill.root.innerHTML =
            oldContent;
    }


    /*
    |--------------------------------------------------------------------------
    | Avant envoi
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('articleForm')
        .addEventListener(
            'submit',
            function() {

                document
                    .getElementById('content')
                    .value =
                    quill.root.innerHTML;

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Statut / programmation
    |--------------------------------------------------------------------------
    */

    const status =
        document.getElementById('status');

    const scheduleBox =
        document.getElementById('scheduleBox');


    function updateScheduleBox() {

        if (
            status.value === 'scheduled'
        ) {

            scheduleBox.style.display =
                'block';

        } else {

            scheduleBox.style.display =
                'none';
        }
    }


    status.addEventListener(
        'change',
        updateScheduleBox
    );

    updateScheduleBox();


    /*
    |--------------------------------------------------------------------------
    | Aperçu nouvelle image
    |--------------------------------------------------------------------------
    */

    const featuredImage =
        document.getElementById(
            'featuredImage'
        );

    const newImagePreview =
        document.getElementById(
            'newImagePreview'
        );


    featuredImage.addEventListener(
        'change',
        function() {

            const file =
                this.files[0];


            if (!file) {

                newImagePreview.innerHTML =
                    '';

                return;
            }


            const reader =
                new FileReader();


            reader.onload =
                function(event) {

                    newImagePreview.innerHTML = `

                    <img
                        src="${event.target.result}"
                        class="img-fluid rounded"
                        style="
                            max-height:220px;
                            width:100%;
                            object-fit:cover;
                        "
                        alt="Aperçu"
                    >

                    <div class="mt-2">

                        <span class="badge bg-primary">

                            Nouvelle image

                        </span>

                    </div>

                `;
                };


            reader.readAsDataURL(file);


            /*
            | Une nouvelle image remplace
            | automatiquement celle de la médiathèque.
            */

            document
                .getElementById('featuredMediaId')
                .value = '';


            document
                .querySelectorAll(
                    '.media-picker-item.selected'
                )
                .forEach(
                    element =>
                    element.classList.remove(
                        'selected'
                    )
                );


            document
                .getElementById(
                    'selectedMediaPreview'
                )
                .innerHTML = '';
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Sélection médiathèque
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.media-picker-item'
        )
        .forEach(
            function(item) {

                item.addEventListener(
                    'click',
                    function() {

                        const id =
                            this.dataset.id;

                        const url =
                            this.dataset.url;

                        const name =
                            this.dataset.name;


                        /*
                        | Enregistrer l'ID
                        */

                        document
                            .getElementById(
                                'featuredMediaId'
                            )
                            .value = id;


                        /*
                        | Supprimer une éventuelle
                        | nouvelle image
                        */

                        document
                            .getElementById(
                                'featuredImage'
                            )
                            .value = '';


                        document
                            .getElementById(
                                'newImagePreview'
                            )
                            .innerHTML = '';


                        /*
                        | Marquer la sélection
                        */

                        document
                            .querySelectorAll(
                                '.media-picker-item'
                            )
                            .forEach(
                                function(element) {

                                    element
                                        .classList
                                        .remove(
                                            'selected'
                                        );
                                }
                            );


                        this.classList.add(
                            'selected'
                        );


                        /*
                        | Aperçu
                        */

                        document
                            .getElementById(
                                'selectedMediaPreview'
                            )
                            .innerHTML = `

                            <div>

                                <img
                                    src="${url}"
                                    alt="${name}"
                                >

                                <div class="mt-2">

                                    <span
                                        class="badge bg-success"
                                    >

                                        <i
                                            class="bi bi-check-circle me-1"
                                        ></i>

                                        Image sélectionnée

                                    </span>

                                </div>

                            </div>

                        `;


                        /*
                        | Fermer la fenêtre
                        */

                        const modalElement =
                            document.getElementById(
                                'mediaPickerModal'
                            );


                        const modal =
                            bootstrap.Modal
                            .getInstance(
                                modalElement
                            );


                        if (modal) {

                            modal.hide();
                        }
                    }
                );
            }
        );
    </script>


</body>

</html>