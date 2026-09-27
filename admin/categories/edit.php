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
    FROM categories
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$error = '';

function generateSlug(string $text): string
{
    $text = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        trim($text)
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

        $error = 'Le nom est obligatoire.';

    } else {

        $slug = generateSlug($name);

        $check = $db->prepare("
            SELECT id
            FROM categories
            WHERE slug = :slug
            AND id != :id
            LIMIT 1
        ");

        $check->execute([
            ':slug' => $slug,
            ':id' => $id
        ]);

        if ($check->fetch()) {

            $error = 'Une autre catégorie utilise déjà ce nom.';

        } else {

            $update = $db->prepare("
                UPDATE categories
                SET
                    name = :name,
                    slug = :slug,
                    description = :description,
                    status = :status
                WHERE id = :id
            ");

            $update->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description ?: null,
                ':status' => $status,
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

    <title>Modifier une catégorie</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h1>
                        Modifier la catégorie
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
                                    Nom
                                </label>

                                <input type="text" name="name" class="form-control"
                                    value="<?= htmlspecialchars($category['name']) ?>" required>

                            </div>

                            <div class="mb-3">

                                <label class="form-label">
                                    Description
                                </label>

                                <textarea name="description" class="form-control"
                                    rows="4"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>

                            </div>

                            <div class="mb-4">

                                <label class="form-label">
                                    Statut
                                </label>

                                <select name="status" class="form-select">

                                    <option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>>
                                        Active
                                    </option>

                                    <option value="inactive"
                                        <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <button type="submit" class="btn btn-dark">
                                Enregistrer les modifications
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>