<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$message = null;
$error = null;

// Récupérer l'ID de la plante depuis l'URL
$id_plante = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user']['id_utilisateur'];

// Vérifier que la plante existe et appartient à l'utilisateur connecté
try {
    $stmt = $conn->prepare("SELECT * FROM Plante WHERE id_plante = :id AND id_utilisateur = :user_id");
    $stmt->execute([
        ':id' => $id_plante,
        ':user_id' => $user_id
    ]);
    $plante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plante) {
        header('Location: listeplante.php');
        exit();
    }

    // Récupérer les notes existantes
    $stmt = $conn->prepare("SELECT * FROM Note WHERE id_plante = :id_plante ORDER BY date_ajout DESC");
    $stmt->execute([':id_plante' => $id_plante]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hauteur = !empty($_POST['hauteur']) ? (float)$_POST['hauteur'] : null;
    $nb_feuilles = !empty($_POST['nb_feuilles']) ? (int)$_POST['nb_feuilles'] : null;
    $etat_sante = $_POST['etat_sante'];
    $couleur_feuilles = $_POST['couleur_feuilles'];
    $photo = null;
    
    // Gestion de l'upload de photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2 Mo
        
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $error = "Le fichier doit être une image JPG ou PNG.";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $error = "La photo ne doit pas dépasser 2 Mo.";
        } else {
            // Créer le dossier uploads s'il n'existe pas
            $upload_dir = 'uploads/notes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Générer un nom unique pour le fichier
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('note_') . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
                $photo = $filepath;
            } else {
                $error = "Erreur lors du téléchargement de la photo.";
            }
        }
    }
    
    // Insérer la note si aucune erreur
    if (empty($error)) {
        try {
            $stmt = $conn->prepare("INSERT INTO Note (id_plante, hauteur, nb_feuilles, etat_sante, couleur_feuilles, photo_note, date_ajout) 
                                VALUES (:id_plante, :hauteur, :nb_feuilles, :etat_sante, :couleur_feuilles, :photo, NOW())");
            $stmt->execute([
                ':id_plante' => $id_plante,
                ':hauteur' => $hauteur,
                ':nb_feuilles' => $nb_feuilles,
                ':etat_sante' => $etat_sante,
                ':couleur_feuilles' => $couleur_feuilles,
                ':photo' => $photo
            ]);
            
            $_SESSION['success'] = "Note de croissance ajoutée avec succès !";
            header("Location: note_suivie.php?id=" . $id_plante);
            exit();
        } catch(PDOException $e) {
            $error = "Erreur lors de l'ajout de la note : " . $e->getMessage();
        }
    }
}

// Obtenir la date actuelle au format français
setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');
$date_actuelle = strftime("%d %B %Y");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes de Suivi - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css_styles.css" rel="stylesheet">
  <style>
        :root {
            --primary-color: #8B7355;
            --secondary-color: #8B7355;
            --accent-color: #A4BE7B;
            --light-color: #F5F5DC;
            --dark-color: #3C2A21;
            --text-color: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.7);
            --border-color: rgba(164, 190, 123, 0.2);
            --card-bg: rgba(26, 26, 26, 0.7);
            --input-bg: rgba(26, 26, 26, 0.7);
            --input-bg-focus: rgba(26, 26, 26, 0.9);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #1a1a1a;
            color: var(--text-color);
            min-height: 100vh;
            padding-left: 0;
            transition: all 0.3s ease;
            position: relative;
            overflow-x: hidden;
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
        }

        .main-content {
            position: relative;
            z-index: 1;
            padding: 2rem;
            background: transparent;
            min-height: 100vh;
        }

        .note-container {
            background: rgba(28, 28, 28, 0.6);
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .plant-header {
            position: relative;
            margin-bottom: 2rem;
            border-radius: 20px;
            overflow: hidden;
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid var(--border-color);
        }

        .plant-header-banner {
            background: linear-gradient(45deg, rgba(164, 190, 123, 0.2), rgba(139, 115, 85, 0.2));
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid var(--border-color);
        }

        .plant-title {
            color: var(--text-color);
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .plant-title h2 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--text-color);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .plant-title .plant-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        .plant-subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }

        .plant-stats-container {
            background: rgba(28, 28, 28, 0.4);
            padding: 1.5rem;
            border-radius: 0 0 20px 20px;
        }

        .plant-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .plant-stat {
            background: rgba(28, 28, 28, 0.6);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(5px);
        }

        .plant-stat:hover {
            transform: translateY(-2px);
            border-color: var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            font-size: 1.8rem;
            color: var(--accent-color);
            margin-bottom: 0.75rem;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .form-section {
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            backdrop-filter: blur(10px);
        }

        .form-section h3 {
            color: var(--text-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group .form-label {
            color: var(--primary-color) !important;
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

        .form-control, .form-select {
            background: rgba(28, 28, 28, 0.7);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
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
        }

        .form-select option:checked, .form-select option:focus, .form-select option:hover {
            background: var(--accent-color);
            color: #fff;
        }

        .form-select {
            background: var(--light-color) !important;
            color: var(--dark-color) !important;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-select:focus {
            background: var(--accent-color) !important;
            color: #fff !important;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(164, 190, 123, 0.25);
        }

        .photo-upload {
            background: rgba(28, 28, 28, 0.4);
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .photo-upload:hover {
            border-color: var(--accent-color);
            background: rgba(28, 28, 28, 0.6);
        }

        .photo-upload i {
            font-size: 2rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .photo-upload-text {
            color: var(--text-color);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .photo-upload-hint {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }

        .btn-submit i {
            font-size: 1.1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-section {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .notes-timeline {
            margin-top: 3rem;
        }

        .timeline-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .timeline-header h3 {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
        }

        .note-card {
            position: relative;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #5F8D4E;
            transition: all 0.3s ease;
        }

        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .note-edit-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #5F8D4E;
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .note-edit-btn:hover {
            background: #5F8D4E;
            color: white;
            transform: translateY(-2px);
        }

        .note-edit-btn i {
            font-size: 1rem;
        }

        .note-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .note-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .health-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .health-excellent { background-color: #d4edda; color: #28a745; }
        .health-bon { background-color: #e8f3eb; color: #5F8D4E; }
        .health-moyen { background-color: #fff3cd; color: #ffc107; }
        .health-mauvais { background-color: #f8d7da; color: #dc3545; }

        .leaf-color {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            vertical-align: middle;
        }

        .leaf-color-vert-fonce { background-color: #1B4332; }
        .leaf-color-vert-clair { background-color: #95D5B2; }
        .leaf-color-jaune { background-color: #FFD700; }
        .leaf-color-marron { background-color: #8B4513; }

        .leaf-color-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .leaf-color-text {
            font-weight: 500;
            color: #2c3e50;
        }

        .note-photo {
            margin-top: 1.5rem;
            text-align: center;
        }

        .note-photo img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            color: #5F8D4E;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }

        .default-plant-image {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            background: linear-gradient(45deg, #E9EDC9, #CCD5AE);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .default-plant-image i {
            font-size: 4rem;
            color: #5F8D4E;
            opacity: 0.6;
        }

        .default-plant-image:hover {
            transform: scale(1.05);
            border-color: white;
        }

        .timeline-section {
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            backdrop-filter: blur(10px);
        }

        .timeline-section h3 {
            color: var(--text-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, 
                var(--accent-color) 0%, 
                rgba(164, 190, 123, 0.3) 100%);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
            padding-left: 1.5rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.25rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            background: var(--accent-color);
            border: 2px solid var(--background);
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(164, 190, 123, 0.2);
        }

        .timeline-date {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .timeline-content {
            background: rgba(28, 28, 28, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .timeline-content:hover {
            background: rgba(28, 28, 28, 0.6);
            transform: translateX(5px);
        }

        .timeline-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            background: rgba(28, 28, 28, 0.9);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
        }

        .stat-item i {
            color: var(--accent-color);
            font-size: 1.2rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-item span {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .timeline-photo {
            margin-top: 1rem;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            width: 200px;
            height: 200px;
            margin-left: auto;
            margin-right: auto;
            border: 2px solid var(--border-color);
            background: rgba(28, 28, 28, 0.4);
        }

        .timeline-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .timeline-photo:hover {
            border-color: var(--accent-color);
        }

        .timeline-photo:hover img {
            transform: scale(1.1);
        }

        .timeline-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            justify-content: flex-end;
        }

        .action-btn {
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        .action-btn i {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .timeline-section {
                padding: 1.5rem;
            }

            .timeline {
                padding-left: 1.5rem;
            }

            .timeline-item {
                padding-left: 1rem;
            }

            .timeline-item::before {
                left: -1.75rem;
            }

            .timeline-stats {
                grid-template-columns: 1fr;
            }
        }

        .photo-upload input[type="file"] {
            display: none;
        }
        .photo-upload {
            cursor: pointer;
        }
        .photo-upload.selected {
            border-color: var(--accent-color);
            background: rgba(28, 28, 28, 0.6);
        }
        .photo-upload-filename {
            color: var(--accent-color);
            font-size: 0.95rem;
            margin-top: 0.5rem;
            word-break: break-all;
        }
        
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<?php include 'profile_button.php'; ?>

    <div class="urban-grid"></div>
    <div class="urban-accent"></div>

    <div class="main-content">
        <div class="note-container">
            <!-- Bouton de retour en haut de page -->
            <div class="mb-4">
                <a href="listeplante.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la liste des plantes
                </a>
            </div>
            
            <div class="plant-header">
                <div class="plant-header-banner">
                    <div class="plant-title">
                        <i class="fas fa-leaf plant-icon"></i>
                        <h2><?= htmlspecialchars($plante['nom']) ?></h2>
                        <p class="plant-subtitle">Notes de suivi de croissance</p>
                    </div>
                </div>
                
                <div class="plant-stats-container">
                    <div class="plant-stats">
                        <div class="plant-stat">
                            <i class="fas fa-ruler-vertical stat-icon"></i>
                            <div class="stat-value"><?= $plante['hauteur'] ?? 'Non spécifié' ?> cm</div>
                            <div class="stat-label">Hauteur actuelle</div>
                        </div>
                        <div class="plant-stat">
                            <i class="fas fa-leaf stat-icon"></i>
                            <div class="stat-value"><?= $plante['nb_feuilles'] ?? 'Non spécifié' ?></div>
                            <div class="stat-label">Nombre de feuilles</div>
                        </div>
                        <div class="plant-stat">
                            <i class="fas fa-calendar-alt stat-icon"></i>
                            <div class="stat-value"><?= date('d/m/Y', strtotime($plante['date_plantation'])) ?></div>
                            <div class="stat-label">Date de plantation</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages de succès/erreur -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
  
            <!-- Formulaire de croissance -->
            <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> Ajouter une note de suivi</h3>
                
                <form method="POST" enctype="multipart/form-data" class="mt-4">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-ruler-vertical"></i>
                                Hauteur (cm)
                            </label>
                            <input type="number" step="0.1" class="form-control" id="hauteur" name="hauteur" placeholder="En cm" required>
                            <div id="hauteurError" class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-leaf"></i>
                                Nombre de feuilles
                            </label>
                            <input type="number" class="form-control" id="nb_feuilles" name="nb_feuilles" placeholder="Nombre de feuilles" min="0" required>
                            <div id="nb_feuillesError" class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-heart"></i>
                                État de santé
                            </label>
                            <select name="etat_sante" id="etat_sante" class="form-select" required>
                                <option value="" disabled selected>Choisir un état de santé</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Bon">Bon</option>
                                <option value="Moyen">Moyen</option>
                                <option value="Mauvais">Mauvais</option>
                            </select>
                            <div id="etat_santeError" class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-palette"></i>
                                Couleur des feuilles
                            </label>
                            <select name="couleur_feuilles" id="couleur_feuilles" class="form-select" required>
                                <option value="" disabled selected>Choisir une couleur</option>
                                <option value="Vert foncé">Vert foncé</option>
                                <option value="Vert clair">Vert clair</option>
                                <option value="Jaune">Jaune</option>
                                <option value="Marron">Marron</option>
                            </select>
                            <div id="couleur_feuillesError" class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-camera"></i>
                            Photo de la plante
                        </label>
                        <div class="photo-upload" id="photoUpload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="photo-upload-text">Cliquez ou glissez une photo ici</div>
                            <div class="photo-upload-hint">Format JPG ou PNG, max 2 Mo</div>
                            <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png">
                            <div class="photo-upload-filename" id="photoFilename"></div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>
                            Enregistrer la note
                        </button>
                    </div>
                </form>
            </div>
    
            <!-- Timeline des notes -->
            <div class="timeline-section">
                <h3>
                    <i class="fas fa-history"></i>
                    Historique des suivis
                </h3>
                
                <div class="timeline">
                    <?php foreach ($notes as $note): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($note['date_ajout'])); ?>
                            </div>
                            
                            <div class="timeline-content">
                                <div class="timeline-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-ruler-vertical"></i>
                                        <span><?php echo number_format($note['hauteur'], 1); ?> cm</span>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <i class="fas fa-leaf"></i>
                                        <span><?php echo $note['nb_feuilles']; ?> feuilles</span>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <i class="fas fa-heart"></i>
                                        <span><?php echo $note['etat_sante']; ?></span>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <i class="fas fa-palette"></i>
                                        <span><?php echo $note['couleur_feuilles']; ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($note['photo_note'])): ?>
                                    <div class="timeline-photo">
                                        <img src="<?php echo htmlspecialchars($note['photo_note']); ?>" alt="Photo de la plante">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="timeline-actions">
                                    <a href="edit_note.php?id=<?php echo $note['id_note']; ?>" class="action-btn">
                                        <i class="fas fa-edit"></i>
                                        Modifier
                                    </a>
                                    <a href="supnote.php?id=<?php echo $note['id_note']; ?>" class="action-btn" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette note ?');">
                                        <i class="fas fa-trash-alt"></i>
                                        Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
        // Fonction de validation de la hauteur
        function validateHauteur() {
            const hauteur = document.getElementById('hauteur');
            if (!hauteur.value.trim()) {
                showFieldError('hauteur', 'La hauteur est requise');
                return false;
            } else if (isNaN(hauteur.value) || parseFloat(hauteur.value) <= 0) {
                showFieldError('hauteur', 'Veuillez entrer un nombre supérieur à 0');
                return false;
            } else {
                showFieldError('hauteur', '');
                return true;
            }
        }

        // Fonction de validation du nombre de feuilles
        function validateNbFeuilles() {
            const nbFeuilles = document.getElementById('nb_feuilles');
            if (!nbFeuilles.value.trim()) {
                showFieldError('nb_feuilles', 'Le nombre de feuilles est requis');
                return false;
            } else if (isNaN(nbFeuilles.value) || parseInt(nbFeuilles.value) < 0) {
                showFieldError('nb_feuilles', 'Veuillez entrer un nombre positif');
                return false;
            } else {
                showFieldError('nb_feuilles', '');
                return true;
            }
        }

        // Fonction de validation de l'état de santé
        function validateEtatSante() {
            const etatSante = document.getElementById('etat_sante');
            if (!etatSante.value) {
                showFieldError('etat_sante', 'Veuillez sélectionner un état de santé');
                return false;
            } else {
                showFieldError('etat_sante', '');
                return true;
            }
        }

        // Fonction de validation de la couleur des feuilles
        function validateCouleurFeuilles() {
            const couleurFeuilles = document.getElementById('couleur_feuilles');
            if (!couleurFeuilles.value) {
                showFieldError('couleur_feuilles', 'Veuillez sélectionner une couleur');
                return false;
            } else {
                showFieldError('couleur_feuilles', '');
                return true;
            }
        }

        // Fonction pour afficher les erreurs de formulaire
        function showFieldError(fieldId, message) {
            // Supprimer l'erreur existante si elle existe
            const existingError = document.getElementById(`${fieldId}Error`);
            if (existingError) {
                existingError.remove();
            }
            
            if (message) {
                // Créer et afficher le message d'erreur
                const errorDiv = document.createElement('div');
                errorDiv.id = `${fieldId}Error`;
                errorDiv.className = 'invalid-feedback d-block';
                errorDiv.style.color = '#dc3545';
                errorDiv.textContent = message;
                
                // Insérer après le champ concerné
                const field = document.getElementById(fieldId);
                field.classList.add('is-invalid');
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            } else {
                // Supprimer la classe d'erreur si le champ est valide
                const field = document.getElementById(fieldId);
                if (field) field.classList.remove('is-invalid');
            }
        }
        
        // Validation du formulaire avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validation de tous les champs
            if (!validateHauteur()) isValid = false;
            if (!validateNbFeuilles()) isValid = false;
            if (!validateEtatSante()) isValid = false;
            if (!validateCouleurFeuilles()) isValid = false;
            
            // Validation de la photo (si fournie)
            const fileInput = document.getElementById('photoInput');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedTypes = ['image/jpeg', 'image/png'];
                const maxSize = 2 * 1024 * 1024; // 2 Mo
                
                // Vérifier le type de fichier
                if (!allowedTypes.includes(file.type)) {
                    showPhotoError('Format de fichier non supporté. Veuillez sélectionner une image JPG ou PNG.');
                    isValid = false;
                }
                
                // Vérifier la taille du fichier
                if (file.size > maxSize) {
                    showPhotoError('La taille du fichier dépasse 2 Mo. Veuillez sélectionner une image plus petite.');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                // Faire défiler jusqu'au premier champ invalide
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
        });
        
        // Fonction pour afficher les erreurs de photo
        function showPhotoError(message) {
            // Supprimer l'erreur existante si elle existe
            const existingError = document.getElementById('photoError');
            if (existingError) {
                existingError.remove();
            }
            
            // Créer et afficher le message d'erreur
            const errorDiv = document.createElement('div');
            errorDiv.id = 'photoError';
            errorDiv.className = 'mt-2 small';
            errorDiv.style.color = '#dc3545';
            errorDiv.textContent = message;
            
            // Insérer après le conteneur de téléchargement
            const uploadContainer = document.getElementById('photoUpload');
            uploadContainer.parentNode.insertBefore(errorDiv, uploadContainer.nextSibling);
        }
        
        // Gestion du changement de fichier
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const fileInput = e.target;
            const fileName = fileInput.files[0]?.name || 'Aucun fichier sélectionné';
            const fileSize = fileInput.files[0]?.size || 0;
            const maxSize = 2 * 1024 * 1024; // 2 Mo
            const allowedTypes = ['image/jpeg', 'image/png'];
            
            // Réinitialiser les messages d'erreur
            const existingError = document.getElementById('photoError');
            if (existingError) {
                existingError.remove();
            }
            
            // Vérifier si un fichier est sélectionné
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Vérifier le type de fichier
                if (!allowedTypes.includes(file.type)) {
                    showPhotoError('Format de fichier non supporté. Veuillez sélectionner une image JPG ou PNG.');
                    fileInput.value = '';
                    document.getElementById('photoFilename').textContent = '';
                    return;
                }
                
                // Vérifier la taille du fichier
                if (file.size > maxSize) {
                    showPhotoError('La taille du fichier dépasse 2 Mo. Veuillez sélectionner une image plus petite.');
                    fileInput.value = '';
                    document.getElementById('photoFilename').textContent = '';
                    return;
                }
                
                // Si tout est bon, afficher le nom du fichier
                document.getElementById('photoFilename').textContent = fileName;
                document.getElementById('photoUpload').classList.add('selected');
            }
        });
        
        // Ajout des écouteurs d'événements pour la validation en temps réel
        document.getElementById('hauteur').addEventListener('blur', validateHauteur);
        document.getElementById('nb_feuilles').addEventListener('blur', validateNbFeuilles);
        document.getElementById('etat_sante').addEventListener('change', validateEtatSante);
        document.getElementById('couleur_feuilles').addEventListener('change', validateCouleurFeuilles);
        
        // Validation lors de la frappe pour les champs numériques
        document.getElementById('hauteur').addEventListener('input', function() {
            if (this.value.trim() === '') {
                showFieldError('hauteur', 'La hauteur est requise');
            } else if (isNaN(this.value) || parseFloat(this.value) <= 0) {
                showFieldError('hauteur', 'Veuillez entrer un nombre supérieur à 0');
            } else {
                showFieldError('hauteur', '');
            }
        });
        
        document.getElementById('nb_feuilles').addEventListener('input', function() {
            if (this.value.trim() === '') {
                showFieldError('nb_feuilles', 'Le nombre de feuilles est requis');
            } else if (isNaN(this.value) || parseInt(this.value) < 0) {
                showFieldError('nb_feuilles', 'Veuillez entrer un nombre positif');
            } else {
                showFieldError('nb_feuilles', '');
            }
        });
        
        // Gestion du glisser-déposer
        const dropZone = document.getElementById('photoUpload');
        const fileInput = document.getElementById('photoInput');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.classList.add('selected');
        }

        function unhighlight() {
            dropZone.classList.remove('selected');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            // Déclencher l'événement change manuellement
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
        
        // Prévisualisation de l'image
        document.getElementById('photoInput').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
        
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
            }
                reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            }
        });

    // Animation des cartes au défilement
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.note-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease-out';
        observer.observe(card);
    });

    document.addEventListener('DOMContentLoaded', function() {
        const photoUpload = document.getElementById('photoUpload');
        const photoInput = document.getElementById('photoInput');
        const photoFilename = document.getElementById('photoFilename');

        photoUpload.addEventListener('click', function(e) {
            // Éviter d'ouvrir le file picker si on clique sur le nom du fichier déjà sélectionné
            if (e.target !== photoInput) {
                photoInput.click();
            }
        });

        photoInput.addEventListener('change', function() {
            if (photoInput.files.length > 0) {
                photoUpload.classList.add('selected');
                photoFilename.textContent = photoInput.files[0].name;
            } else {
                photoUpload.classList.remove('selected');
                photoFilename.textContent = '';
            }
        });
    });
  </script>
</body>
</html>