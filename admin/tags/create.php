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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'Le nom du tag est obligatoire.';

    } else {

        $slug = generateSlug($name);

        $check = $db->prepare("
            SELECT id
            FROM tags
            WHERE slug = :slug
            LIMIT 1
        ");

        $check->execute([
            ':slug' => $slug
        ]);

        if ($check->fetch()) {

            $error = 'Ce tag existe déjà.';

        } else {

            $stmt = $db->prepare("
                INSERT INTO tags
                (
                    name,
                    slug
                )
                VALUES
                (
                    :name,
                    :slug
                )
            ");

            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug
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

    <title>Nouveau tag</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-lg-6">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h1>
                        Nouveau tag
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

                            <div class="mb-4">

                                <label class="form-label">
                                    Nom du tag
                                </label>

                                <input type="text" name="name" class="form-control"
                                    placeholder="Ex : Intelligence artificielle" required>

                                <div class="form-text">
                                    Le slug sera généré automatiquement.
                                </div>

                            </div>

                            <button type="submit" class="btn btn-dark">
                                Créer le tag
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>