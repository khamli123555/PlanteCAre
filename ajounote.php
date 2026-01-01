<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$plant_id = isset($_GET['plant_id']) ? (int)$_GET['plant_id'] : 0;

// Vérifier que la plante appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM plantes WHERE id = ? AND id_utilisateur = ?");
$stmt->execute([$plant_id, $user_id]);
$plant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plant) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Traitement du formulaire
if ($_POST) {
    $commentaire = trim($_POST['commentaire']);
    $photo = null;

    // Validation
    if (empty($commentaire)) {
        $error = 'Le commentaire est obligatoire.';
    } else {
        // Gestion de l'upload de photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $max_size = 2 * 1024 * 1024; // 2 Mo
            
            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['photo']['size'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error = 'Seuls les fichiers JPG, JPEG et PNG sont autorisés.';
            } elseif ($file_size > $max_size) {
                $error = 'La taille du fichier ne doit pas dépasser 2 Mo.';
            } else {
                // Générer un nom unique pour le fichier
                $filename = uniqid() . '.' . $file_extension;
                $upload_dir = 'uploads/notes/';
                
                // Créer le dossier s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    $photo = $filename;
                } else {
                    $error = 'Erreur lors du téléchargement de la photo.';
                }
            }
        }
        
        // Insérer la note si pas d'erreur
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO notes (id_plante, commentaire, photo) VALUES (?, ?, ?)");
                $stmt->execute([$plant_id, $commentaire, $photo]);
                
                $message = 'Note ajoutée avec succès !';
                
                // Redirection après 2 secondes
                header("refresh:2;url=history.php?plant_id=" . $plant_id);
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'ajout de la note : ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une note - <?php echo htmlspecialchars($plant['nom']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Ajouter une note de suivi</h1>
            <p>Pour la plante : <strong><?php echo htmlspecialchars($plant['nom']); ?></strong></p>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="noteForm">
            <div class="form-group">
                <label for="commentaire">Commentaire *</label>
                <textarea name="commentaire" id="commentaire" rows="5" required 
                          placeholder="Décrivez l'évolution de votre plante..."></textarea>
                <span class="error-message" id="commentaire-error"></span>
            </div>

            <div class="form-group">
                <label for="photo">Photo (optionnelle)</label>
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/jpg,image/png">
                <small>Formats acceptés : JPG, JPEG, PNG (max 2 Mo)</small>
                <div id="photo-preview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Ajouter la note</button>
                <a href="history.php?plant_id=<?php echo $plant_id; ?>" class="btn btn-secondary">Retour à l'historique</a>
            </div>
        </form>
    </div>

    <script src="js/add_note.js"></script>
</body>
</html>