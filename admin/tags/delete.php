<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id) {

    $stmt = $db->prepare("
        DELETE FROM tags
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);
}

header('Location: index.php');
exit;