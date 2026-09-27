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
| Récupération de l'article
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT featured_image
    FROM articles
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id
]);

$article = $stmt->fetch();

if (!$article) {

    header('Location: index.php');
    exit;
}

try {

    $db->beginTransaction();

    /*
     * Les relations article_tags,
     * commentaires et vues sont supprimées
     * automatiquement grâce aux FK ON DELETE CASCADE.
     */

    $delete = $db->prepare("
        DELETE FROM articles
        WHERE id = :id
    ");

    $delete->execute([
        ':id' => $id
    ]);

    $db->commit();


    /*
    |--------------------------------------------------------------------------
    | Suppression de l'image
    |--------------------------------------------------------------------------
    */

    if (!empty($article['featured_image'])) {

        $uploadDir =
            realpath(
                __DIR__
                . '/../../public/uploads/articles'
            );

        $imagePath =
            realpath(
                __DIR__
                . '/../../public/'
                . $article['featured_image']
            );

        if (
            $uploadDir
            && $imagePath
            && is_file($imagePath)
            && str_starts_with(
                $imagePath,
                $uploadDir
            )
        ) {

            unlink($imagePath);
        }
    }

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    exit(
        'Impossible de supprimer l’article.'
    );
}

header('Location: index.php?deleted=1');

exit;