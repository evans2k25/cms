<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Récupérer le média
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM media
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$media = $stmt->fetch();

if (!$media) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Supprimer le fichier
|--------------------------------------------------------------------------
*/

$filePath =
    __DIR__ .
    '/../../public/' .
    ltrim($media['file_path'], '/');

$publicDirectory = realpath(
    __DIR__ . '/../../public/uploads/media'
);

$realFilePath = realpath($filePath);

if (
    $realFilePath !== false &&
    $publicDirectory !== false &&
    str_starts_with(
        $realFilePath,
        $publicDirectory . DIRECTORY_SEPARATOR
    ) &&
    is_file($realFilePath)
) {

    unlink($realFilePath);
}

/*
|--------------------------------------------------------------------------
| Supprimer de la base
|--------------------------------------------------------------------------
*/

$deleteStmt = $db->prepare("
    DELETE FROM media
    WHERE id = :id
");

$deleteStmt->execute([
    ':id' => $id
]);

header('Location: index.php?deleted=1');
exit;