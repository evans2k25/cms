<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();

$database = new Database();
$db = $database->connect();

$error = '';

function generateSlug(string $text): string
{
    $text = trim($text);

    $text = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $text
    );

    $text = strtolower($text);

    $text = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $text
    );

    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name === '') {

        $error = 'Le nom de la catégorie est obligatoire.';

    } else {

        $slug = generateSlug($name);

        $check = $db->prepare("
            SELECT id
            FROM categories
            WHERE slug = :slug
            LIMIT 1
        ");

        $check->execute([
            ':slug' => $slug
        ]);

        if ($check->fetch()) {

            $error = 'Cette catégorie existe déjà.';

        } else {

            $stmt = $db->prepare("
                INSERT INTO categories
                (
                    name,
                    slug,
                    description,
                    status
                )
                VALUES
                (
                    :name,
                    :slug,
                    :description,
                    :status
                )
            ");

            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description ?: null,
                ':status' => $status
            ]);

            header('Location: index.php');
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nouvelle catégorie</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h1>
                        Nouvelle catégorie
                    </h1>

                    <a href="index.php" class="btn btn-outline-secondary">
                        Retour
                    </a>

                </div>

                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">

                        <?php if ($error): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>

                        <?php endif; ?>

                        <form method="POST">

                            <div class="mb-3">

                                <label class="form-label">
                                    Nom de la catégorie
                                </label>

                                <input type="text" name="name" class="form-control" placeholder="Ex : Technologie"
                                    required>

                            </div>

                            <div class="mb-3">

                                <label class="form-label">
                                    Description
                                </label>

                                <textarea name="description" class="form-control" rows="4"
                                    placeholder="Description de la catégorie..."></textarea>

                            </div>

                            <div class="mb-4">

                                <label class="form-label">
                                    Statut
                                </label>

                                <select name="status" class="form-select">

                                    <option value="active">
                                        Active
                                    </option>

                                    <option value="inactive">
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <div class="d-flex gap-2">

                                <button type="submit" class="btn btn-dark">
                                    Enregistrer
                                </button>

                                <a href="index.php" class="btn btn-light">
                                    Annuler
                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>