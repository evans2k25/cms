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

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT *
    FROM tags
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$tag = $stmt->fetch();

if (!$tag) {
    header('Location: index.php');
    exit;
}

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
            AND id != :id
            LIMIT 1
        ");

        $check->execute([
            ':slug' => $slug,
            ':id' => $id
        ]);

        if ($check->fetch()) {

            $error = 'Un autre tag utilise déjà ce nom.';

        } else {

            $update = $db->prepare("
                UPDATE tags
                SET
                    name = :name,
                    slug = :slug
                WHERE id = :id
            ");

            $update->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':id' => $id
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

    <title>Modifier le tag</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-lg-6">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h1>
                        Modifier le tag
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
                                    value="<?= htmlspecialchars($tag['name']) ?>" required>

                            </div>

                            <button type="submit" class="btn btn-dark">
                                Enregistrer
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>