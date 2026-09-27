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
| Traitement
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['media']) ||
        $_FILES['media']['error'] !== UPLOAD_ERR_OK
    ) {

        $error = "Veuillez sélectionner une image.";

    } else {

        $file = $_FILES['media'];

        /*
        |--------------------------------------------------------------------------
        | Taille maximale : 5 Mo
        |--------------------------------------------------------------------------
        */

        if ($file['size'] > 5 * 1024 * 1024) {

            $error = "L'image ne doit pas dépasser 5 Mo.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Vérification MIME
            |--------------------------------------------------------------------------
            */

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mimeType = $finfo->file(
                $file['tmp_name']
            );

            if (!isset($allowedTypes[$mimeType])) {

                $error = "Format d'image non autorisé.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Dossier de destination
                |--------------------------------------------------------------------------
                */

                $uploadDirectory =
                    __DIR__ .
                    '/../../public/uploads/media/';

                if (!is_dir($uploadDirectory)) {

                    mkdir(
                        $uploadDirectory,
                        0755,
                        true
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Nom sécurisé et aléatoire
                |--------------------------------------------------------------------------
                */

                $extension =
                    $allowedTypes[$mimeType];

                $filename =
                    bin2hex(random_bytes(16)) .
                    '.' .
                    $extension;

                $destination =
                    $uploadDirectory .
                    $filename;


                /*
                |--------------------------------------------------------------------------
                | Déplacement
                |--------------------------------------------------------------------------
                */

                if (
                    !move_uploaded_file(
                        $file['tmp_name'],
                        $destination
                    )
                ) {

                    $error =
                        "Impossible d'enregistrer l'image.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Enregistrement DB
                    |--------------------------------------------------------------------------
                    */

                    $relativePath =
                        'uploads/media/' .
                        $filename;

                    $stmt = $db->prepare("
                        INSERT INTO media
                        (
                            user_id,
                            filename,
                            original_name,
                            file_path,
                            mime_type,
                            file_size,
                            alt_text,
                            caption
                        )
                        VALUES
                        (
                            :user_id,
                            :filename,
                            :original_name,
                            :file_path,
                            :mime_type,
                            :file_size,
                            :alt_text,
                            :caption
                        )
                    ");

                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'],
                        ':filename' => $filename,
                        ':original_name' => $file['name'],
                        ':file_path' => $relativePath,
                        ':mime_type' => $mimeType,
                        ':file_size' => $file['size'],
                        ':alt_text' => trim(
                            $_POST['alt_text'] ?? ''
                        ),
                        ':caption' => trim(
                            $_POST['caption'] ?? ''
                        )
                    ]);


                    header(
                        'Location: index.php?uploaded=1'
                    );

                    exit;
                }
            }
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
        Ajouter un média — <?= htmlspecialchars(APP_NAME) ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark">

        <div class="container">

            <a href="index.php" class="navbar-brand fw-bold">
                <i class="bi bi-images"></i>
                Médiathèque
            </a>

            <a href="../dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>

        </div>

    </nav>


    <main class="container py-5">

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white py-3">

                        <h1 class="h4 mb-0">

                            <i class="bi bi-cloud-arrow-up text-danger"></i>

                            Ajouter une image

                        </h1>

                    </div>


                    <div class="card-body p-4">

                        <?php if ($error): ?>

                        <div class="alert alert-danger">

                            <i class="bi bi-exclamation-circle"></i>

                            <?= htmlspecialchars($error) ?>

                        </div>

                        <?php endif; ?>


                        <form method="POST" enctype="multipart/form-data">

                            <div class="mb-4">

                                <label for="media" class="form-label fw-semibold">
                                    Image
                                </label>

                                <input type="file" name="media" id="media" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,.gif" required>

                                <div class="form-text">

                                    Formats autorisés :
                                    JPG, PNG, WEBP, GIF.
                                    Taille maximale : 5 Mo.

                                </div>

                            </div>


                            <div class="mb-3">

                                <label for="alt_text" class="form-label">
                                    Texte alternatif
                                </label>

                                <input type="text" name="alt_text" id="alt_text" class="form-control" maxlength="255"
                                    placeholder="Description de l'image">

                            </div>


                            <div class="mb-4">

                                <label for="caption" class="form-label">
                                    Légende
                                </label>

                                <textarea name="caption" id="caption" class="form-control" rows="3"
                                    placeholder="Légende affichée avec l'image..."></textarea>

                            </div>


                            <div class="d-flex justify-content-between">

                                <a href="index.php" class="btn btn-outline-secondary">
                                    Annuler
                                </a>

                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-upload"></i>
                                    Envoyer l'image
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </main>

</body>

</html>