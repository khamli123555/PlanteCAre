<?php
session_start();
require_once 'config.php';

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les données utilisateur depuis la session
$user = $_SESSION['user'];
$nom = $user['nom'];
$email = $user['email'];
$genre = $user['genre'];
$photo = isset($user['photo_profil']) ? $user['photo_profil'] : null;
$telephone = isset($user['telephone']) && !empty($user['telephone']) ? $user['telephone'] : null;

// Débogage temporaire
error_log('Photo de profil : ' . ($photo ? $photo : 'aucune'));
if ($photo) {
    error_log('Chemin complet : ' . UPLOAD_DIR_PROFILES . $photo);
    error_log('Le fichier existe : ' . (file_exists(UPLOAD_DIR_PROFILES . $photo) ? 'oui' : 'non'));
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Mon Profil - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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

        .profile-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header h1 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }

        .profile-card {
            background: rgba(28, 28, 28, 0.6);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 4px solid var(--accent-color);
            background-color: rgba(28, 28, 28, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            overflow: hidden;
            cursor: pointer;
        }

        .profile-photo:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-photo i {
            font-size: 4rem;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .profile-info {
            text-align: center;
            margin-bottom: 2rem;
        }

   

        .profile-email {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            color: var(--dark-color);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--light-color);
        }

        .form-control:disabled {
            background-color: var(--light-color);
            color: #666;
            cursor: not-allowed;
            opacity: 0.8;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 122, 61, 0.25);
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 122, 61, 0.2);
            background: linear-gradient(135deg, var(--dark-color), var(--primary-color));
            color: white;
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            color: var(--primary-color);
            background-color: transparent;
            border: 1px solid var(--primary-color);
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .btn-back:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateX(-5px);
        }

        .btn-back i {
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .profile-card {
                padding: 30px 20px;
            }

            .profile-photo {
                width: 120px;
                height: 120px;
            }

            .profile-name {
                font-size: 1.5rem;
            }
        }

        .info-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .info-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #2c3e50;
            font-size: 1.1rem;
            padding: 0.75rem;
            background-color: var(--light-color);
            border-radius: 10px;
            margin: 0;
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-edit:hover {
            text-decoration: none;
        }
    </style>
</head>
<body>

<a href="dashboard.php" class="btn-back">
    <i class="fas fa-arrow-left"></i>
    <span>Retour au tableau de bord</span>
</a>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container py-5">
        <div class="profile-container">

            <div class="profile-card">
                <div class="profile-photo">
                    <?php 
                    if ($photo && file_exists(UPLOAD_DIR_PROFILES . $photo)): 
                        $photo_path = UPLOAD_DIR_PROFILES . $photo;
                        $photo_type = mime_content_type($photo_path);
                        if (strpos($photo_type, 'image/') === 0):
                    ?>
                        <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo de profil de <?= htmlspecialchars($nom) ?>">
                    <?php else: ?>
                        <?php if (file_exists('uploads/profiles/guest.png')): ?>
                            <img src="uploads/profiles/guest.png" alt="Photo de profil par défaut">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    <?php 
                        endif;
                    else: 
                    ?>
                        <?php if (file_exists('uploads/profiles/guest.png')): ?>
                            <img src="uploads/profiles/guest.png" alt="Photo de profil par défaut">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

               

                <div class="info-group">
                    <label class="info-label">Nom</label>
                    <p class="info-value"><?= htmlspecialchars($nom) ?></p>
                </div>

                <div class="info-group">
                    <label class="info-label">Email</label>
                    <p class="info-value"><?= htmlspecialchars($email) ?></p>
                </div>

                <div class="info-group">
                    <label class="info-label">Genre</label>
                    <p class="info-value">
                        <?php
                        switch($genre) {
                            case 'Homme':
                                echo 'Homme';
                                break;
                            case 'Femme':
                                echo 'Femme';
                                break;
                            case 'Autre':
                                echo 'Autre';
                                break;
                            default:
                                echo 'Non spécifié';
                        }
                        ?>
                    </p>
                </div>

                <?php if ($telephone): ?>
                <div class="info-group">
                    <label class="info-label">Téléphone</label>
                    <p class="info-value"><?= htmlspecialchars($telephone) ?></p>
                </div>
                <?php endif; ?>

                <a href="modifier_p.php" class="btn-edit">
                    <i class="fas fa-edit me-2"></i>Modifier le profil
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
