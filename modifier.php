<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Récupération de l'ID de la plante à modifier
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listeplante.php');
    exit;
}

$id_plante = $_GET['id'];

// Vérification des droits d'accès
try {
    $stmt = $conn->prepare("SELECT * FROM Plante WHERE id_plante = ? AND id_utilisateur = ?");
    $stmt->execute([$id_plante, $_SESSION['user']['id_utilisateur']]);
    $plante = $stmt->fetch();
    
    if (!$plante) {
        header('Location: listeplante.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Une erreur est survenue lors de la récupération des données de la plante.";
}

// Configuration du dossier d'upload
$upload_dir = 'uploads/plants/';

// Vérifier si le dossier d'upload existe, sinon le créer
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        $error = "Erreur : Impossible de créer le dossier d'upload";
    }
}

// Vérifier les permissions du dossier
if (!is_writable($upload_dir)) {
    chmod($upload_dir, 0777);
    if (!is_writable($upload_dir)) {
        $error = "Erreur : Le dossier d'upload n'a pas les bonnes permissions";
    }
}

// Traitement du formulaire de modification
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validation des champs obligatoires
        if (empty($_POST['nom']) || empty($_POST['type']) || empty($_POST['date'])) {
            throw new Exception("Le nom, le type et la date sont obligatoires");
        }

        $nom = trim($_POST['nom']);
        $type = trim($_POST['type']);
        $besoins_eau = trim($_POST['eau'] ?? '');
        $besoins_lumiere = trim($_POST['lumiere'] ?? '');
        $date_plantation = trim($_POST['date']);
        $remarques = trim($_POST['remarques'] ?? '');
        $photo = $plante['photo']; // Garder l'ancienne photo par défaut

        // Traitement de l'upload de photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            // Vérification du type de fichier
            $allowed_types = ['image/jpeg', 'image/png'];
            if (!in_array($_FILES['photo']['type'], $allowed_types)) {
                throw new Exception("Seuls les fichiers JPG, JPEG et PNG sont autorisés");
            }

            // Vérification de la taille
            if ($_FILES['photo']['size'] > 2097152) { // 2 MB
                throw new Exception("La taille du fichier ne doit pas dépasser 2 MB");
            }

            // Déterminer l'extension basée sur le type MIME
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

            // Générer un nom de fichier unique
            $new_filename = 'plant_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            // Tentative de déplacement du fichier
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                throw new Exception("Erreur lors du téléchargement du fichier");
            }

            // Supprimer l'ancienne photo si elle existe
            if ($photo && file_exists($upload_dir . $photo)) {
                unlink($upload_dir . $photo);
            }

            $photo = $new_filename;
        }

        // Mise à jour dans la base de données
        $stmt = $conn->prepare("UPDATE Plante SET nom = ?, type = ?, besoins_eau = ?, besoins_lumiere = ?, 
                date_plantation = ?, remarques = ?, photo = ? WHERE id_plante = ? AND id_utilisateur = ?");
        
        $stmt->execute([
            $nom,
            $type,
            $besoins_eau,
            $besoins_lumiere,
            $date_plantation,
            $remarques,
            $photo,
            $id_plante,
            $_SESSION['user']['id_utilisateur']
        ]);

        // Redirection vers la liste des plantes
        $_SESSION['success_message'] = "La plante a été modifiée avec succès !";
        header("Location: listeplante.php");
        exit;

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une Plante - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css_styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8B7355;
            --secondary-color: #8B7355;
            --accent-color: #A4BE7B;
            --light-color: #F5F5DC;
            --dark-color: #2C2C2C;
            --text-color: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.7);
            --border-color: rgba(164, 190, 123, 0.2);
            --card-bg: rgba(26, 26, 26, 0.7);
            --input-bg: rgba(26, 26, 26, 0.7);
            --input-bg-focus: rgba(26, 26, 26, 0.9);
            --animation-duration: 0.3s;
            --hover-scale: 1.05;
            --white: #ffffff;
            --white-opacity: rgba(255, 255, 255, 0.9);
            --white-light: rgba(255, 255, 255, 0.8);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #2C2C2C;
            color: var(--text-color);
            min-height: 100vh;
            padding-left: 0;
            position: relative;
            overflow-x: hidden;
        }

        .btn-retour {
            position: fixed;
            top: 20px;
            left: 20px;
            background: none;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all var(--animation-duration) ease;
            cursor: pointer;
            z-index: 1000;
        }

        .btn-retour:hover {
            background: var(--accent-color);
            color: var(--text-color);
            transform: scale(1.05);
        }

        .btn-retour i {
            margin-right: 8px;
        }

        .form-container {
            background: rgba(28, 28, 28, 0.6);
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            transition: all var(--animation-duration) ease;
        }

        .urban-grid {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                linear-gradient(90deg, rgba(139, 115, 85, 0.1) 1px, transparent 1px),
                linear-gradient(0deg, rgba(139, 115, 85, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            transform: perspective(500px) rotateX(60deg);
            transform-origin: center top;
            animation: gridMove 20s linear infinite;
            pointer-events: none;
            z-index: 0;
        }

        .urban-accent {
            position: fixed;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(164, 190, 123, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(60, 42, 33, 0.4) 0%, transparent 50%);
            filter: blur(60px);
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
            animation: float 6s ease-in-out infinite;
        }

        .main-content {
            position: relative;
            z-index: 1;
            padding: 2rem;
            background: transparent;
            min-height: 100vh;
        }

        .form-actions {
            margin-top: 2rem;
            padding: 2rem 0;
            text-align: center;
            background: rgba(28, 28, 28, 0.6);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .form-actions .btn-primary {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            transition: all var(--animation-duration) ease;
        }

        .form-actions .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: all var(--animation-duration) ease;
        }

        .form-actions .btn-primary:hover::before {
            left: 100%;
        }

        .form-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-outline-success {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all var(--animation-duration) ease;
        }

        .btn-outline-success:hover {
            background: var(--primary-color);
            color: var(--text-color);
            transform: translateY(-2px);
        }

        .btn i {
            transition: all var(--animation-duration) ease;
        }

        .btn:hover i {
            transform: translateX(2px);
        }

        .alert {
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            transition: all var(--animation-duration) ease;
        }

        .alert:hover {
            background: rgba(28, 28, 28, 0.7);
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }

        .alert i {
            color: var(--accent-color);
            margin-right: 0.75rem;
        }

        .alert-danger {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .container {
            max-width: 800px;
        }

        .form-container {
            background: rgba(28, 28, 28, 0.6);
            padding: 2.5rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .form-container:hover {
            background: rgba(28, 28, 28, 0.7);
            transform: translateY(-2px);
            border-color: var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .photo-upload {
            border: 2px dashed var(--border-color);
            background: rgba(28, 28, 28, 0.4);
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-color);
            border-radius: 15px;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all var(--animation-duration) ease;
            position: relative;
            overflow: hidden;
        }

        .photo-upload:hover {
            background: rgba(28, 28, 28, 0.5);
            border-color: var(--accent-color);
            transform: scale(1.02);
        }

        .photo-preview {
            max-width: 100%;
            max-height: 240px;
            border-radius: 12px;
            object-fit: contain;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
            transition: all var(--animation-duration) ease;
        }

        .photo-upload-text {
            color: var(--text-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: 1px solid var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all var(--animation-duration) ease;
            color: var(--text-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-color: var(--secondary-color);
        }

        .btn-menu {
            background: none;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all var(--animation-duration) ease;
            cursor: pointer;
        }

        .btn-menu:hover {
            background: var(--accent-color);
            color: var(--text-color);
            transform: scale(1.05);
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.05rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
        }

        .form-label i {
            color: var(--accent-color);
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            color: var(--secondary-color);
            transform: translateX(-5px);
        }

        .page-title {
            color: var(--primary-color);
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 1rem;
            font-size: 2rem;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .alert-danger {
            background-color: #ffe5e5;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }

        .page-title {
            color: var(--primary-color);
            margin-bottom: 2rem;
            position: relative;
            padding-bottom: 1rem;
            font-size: 2rem;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .required-field::after {
            content: ' *';
            color: #d32f2f;
        }

        .form-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background-color: #ffb300;
            border-color: #ffb300;
            transform: translateY(-2px);
        }

        .photo-upload {
            background: rgba(28, 28, 28, 0.4);
            border: 2px dashed var(--accent-color);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all var(--animation-duration) ease;
            position: relative;
            overflow: hidden;
        }

        .photo-upload::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .photo-upload:hover {
            background: rgba(28, 28, 28, 0.5);
            border-color: var(--accent-color);
            transform: scale(1.02);
        }

        .photo-upload .upload-icon {
            color: var(--accent-color);
            transition: all var(--animation-duration) ease;
        }

        .photo-upload:hover .upload-icon {
            transform: scale(1.1);
        }

        .upload-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .upload-text .text-primary {
            color: var(--primary-color);
            font-weight: 600;
            text-align: center;
        }

        .conditions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .condition-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .condition-icon {
            font-size: 1.5rem;
            line-height: 1;
            min-width: 24px;
            text-align: center;
        }

        .condition-text {
            color: var(--primary-color);
            font-weight: 600;
            flex: 1;
        }

        .form-control, .form-select {
            background: rgba(28, 28, 28, 0.7);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all var(--animation-duration) ease;
            width: 100%;
            font-size: 1rem;
            font-weight: 500;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(28, 28, 28, 0.9);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(164, 190, 123, 0.25);
            color: var(--text-color);
        }

        .form-control::placeholder {
            color: #fff;
            opacity: 0.8;
            font-weight: 400;
        }

        .form-select option {
            background: var(--light-color);
            color: var(--dark-color);
            padding: 8px;
            font-weight: 600;
            font-size: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all var(--animation-duration) ease;
        }

        .form-select option:checked, .form-select option:focus, .form-select option:hover {
            background: var(--accent-color);
            color: #fff;
        }

        .btn i {
            transition: all var(--animation-duration) ease;
        }

        .photo-upload:hover {
            background: rgba(28, 28, 28, 0.5);
            border-color: var(--accent-color);
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <div class="urban-grid"></div>
    <div class="urban-accent"></div>
    <?php include 'sidebar.php'; ?>
    <?php include 'profile_button.php'; ?>
    <div class="header-actions">
                <a href="dashboard.php" class="btn btn-menu">
                    <i class="fas fa-arrow-left"></i>
                    Retour
                </a>

    <div class="main-content">
        <div class="container py-5">
            <div class="form-container">
                <div class="page-title">
                    <i class="fas fa-seedling me-2"></i>Modifier une plante
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="plantForm">
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-image me-2"></i>Photo de la plante
                            <small class="text-muted ms-2">(Obligatoire)</small>
                        </label>
                        <div class="photo-upload" onclick="document.getElementById('photo').click()">
                            <div id="uploadText" class="text-center">
                                <i class="fas fa-camera upload-icon"></i>
                                <div class="upload-text">
                                    <span class="text-primary">Cliquez ou glissez une image ici</span>
                                    <div class="mt-2">
                                        <small class="text-primary">Format accepté : JPG, JPEG ou PNG</small><br>
                                        <small class="text-primary">Taille maximale : 2MB</small>
                                    </div>
                                </div>
                            </div>
                            <img id="photoPreview" class="photo-preview d-none" alt="Aperçu">
                        </div>
                        <input type="file" class="form-control d-none" id="photo" name="photo" accept="image/jpeg,image/png" 
                               onchange="validateFile(this)">

                        <div id="photoError" class="text-danger mt-2 d-none">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="photoErrorMessage"></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label required-field">Nom de la plante</label>
                        <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($plante['nom']) ?>" required 
                                placeholder="Ex: Monstera Deliciosa">
                    </div>

                    <div class="mb-4">
                        <label class="form-label required-field">Type de plante</label>
                        <select class="form-select" name="type" required>
                            <option value="" selected disabled>Choisir un type...</option>
                            <option value="Intérieur" <?= $plante['type'] == 'Intérieur' ? 'selected' : '' ?>>Plante d'intérieur</option>
                            <option value="Extérieur" <?= $plante['type'] == 'Extérieur' ? 'selected' : '' ?>>Plante d'extérieur</option>
                        </select>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Besoins en eau</label>
                            <select class="form-select" name="eau">
                                <option value="">Sélectionner...</option>
                                <option value="Faible" <?= $plante['besoins_eau'] == 'Faible' ? 'selected' : '' ?>>Faible</option>
                                <option value="Modéré" <?= $plante['besoins_eau'] == 'Modéré' ? 'selected' : '' ?>>Modéré</option>
                                <option value="Élevé" <?= $plante['besoins_eau'] == 'Élevé' ? 'selected' : '' ?>>Élevé</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Besoins en lumière</label>
                            <select class="form-select" name="lumiere">
                                <option value="">Sélectionner...</option>
                                <option value="Faible" <?= $plante['besoins_lumiere'] == 'Faible' ? 'selected' : '' ?>>Faible</option>
                                <option value="Modéré" <?= $plante['besoins_lumiere'] == 'Modéré' ? 'selected' : '' ?>>Modéré</option>
                                <option value="Direct" <?= $plante['besoins_lumiere'] == 'Direct' ? 'selected' : '' ?>>Direct</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label required-field">Date de plantation</label>
                        <input type="date" class="form-control" name="date" value="<?= $plante['date_plantation'] ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Remarques</label>
                        <textarea class="form-control" name="remarques" rows="4" 
                                  placeholder="Notes supplémentaires sur votre plante..."><?= htmlspecialchars($plante['remarques']) ?></textarea>
                    </div>

                </form>
                <div class="form-actions text-center mt-4">
                    <button type="submit" class="btn btn-primary" form="plantForm">
                        <i class="fas fa-save me-2"></i>Modifier la plante
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateFile(input) {
            const file = input.files[0];
            const errorDiv = document.getElementById('photoError');
            const errorMessage = document.getElementById('photoErrorMessage');
            const preview = document.getElementById('photoPreview');
            const uploadText = document.getElementById('uploadText');
            
            // Réinitialiser l'erreur
            errorDiv.classList.add('d-none');
            errorMessage.textContent = '';
            preview.classList.add('d-none');
            uploadText.classList.remove('d-none');

            if (!file) {
                return;
            }

            // Vérifier le type de fichier
            if (!file.type.startsWith('image/')) {
                errorMessage.textContent = 'Veuillez sélectionner une image valide (JPG, JPEG ou PNG)';
                errorDiv.classList.remove('d-none');
                return;
            }

            // Vérifier la taille du fichier
            if (file.size > 2097152) { // 2MB
                errorMessage.textContent = 'La taille du fichier ne doit pas dépasser 2MB';
                errorDiv.classList.remove('d-none');
                return;
            }

            // Prévisualisation de l'image
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
                uploadText.classList.add('d-none');
            };
            reader.readAsDataURL(file);
        }

        // Ajouter l'écouteur d'événement sur le champ de fichier
        document.getElementById('photo').addEventListener('change', function() {
            validateFile(this);
        });

        // Glisser-déposer pour l'upload de photo
        const dropZone = document.querySelector('.photo-upload');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('bg-light');
        }

        function unhighlight(e) {
            dropZone.classList.remove('bg-light');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('photo');
            
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>
