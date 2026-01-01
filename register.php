<?php
session_start();
require_once 'config.php';

$errors = [];
$nom = $email = $genre = $telephone = '';
$age = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $age = intval($_POST['age'] ?? 0);
    $telephone = trim($_POST['telephone'] ?? '');
    $photo = $_FILES['photo'] ?? null;
    $niveaux_connaissance = $_POST['niveau_connaissance'] ?? [];
    $niveau_connaissance = !empty($niveaux_connaissance) ? implode(', ', $niveaux_connaissance) : 'Débutant';

    // Validation
    if (empty($nom)) {
        $errors['nom'] = "Le nom est obligatoire.";
    } elseif (strlen($nom) < 2 || strlen($nom) > 100) {
        $errors['nom'] = "Le nom doit contenir entre 2 et 100 caractères.";
    }

    if (empty($email)) {
        $errors['email'] = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'adresse e-mail n'est pas valide.";
    }

    if (empty($age)) {
        $errors['age'] = "L'âge est obligatoire.";
    } elseif ($age < 1 || $age > 150) {
        $errors['age'] = "L'âge doit être compris entre 1 et 150 ans.";
    }

    if (empty($password)) {
        $errors['password'] = "Le mot de passe est obligatoire.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors['password'] = "Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.";
    }

    if ($password !== $confirm) {
        $errors['confirm'] = "Les mots de passe ne correspondent pas.";
    }

    if (empty($genre)) {
        $errors['genre'] = "Le genre est obligatoire.";
    }

    if (!empty($telephone)) {
        if (!preg_match("/^0[0-9]{9}$/", $telephone)) {
            $errors['telephone'] = "Le numéro de téléphone doit commencer par 0 et contenir 10 chiffres.";
        }
    }

    // Validation de la photo
    if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors['photo'] = "Le format de la photo doit être JPG ou PNG.";
        } elseif ($photo['size'] > 2 * 1024 * 1024) {
            $errors['photo'] = "La taille de la photo ne doit pas dépasser 2 Mo.";
        }
    }

    // Vérifier si l'e-mail existe déjà
    $stmt = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors['email'] = "Cet e-mail est déjà utilisé.";
    }

    // Traitement si pas d'erreurs
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $photoName = null;

            // Gérer la photo
            if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
                $photoName = uniqid() . '.' . $ext;
                
                if (!is_dir(UPLOAD_DIR_PROFILES)) {
                    mkdir(UPLOAD_DIR_PROFILES, 0777, true);
                }
                
                if (move_uploaded_file($photo['tmp_name'], UPLOAD_DIR_PROFILES . $photoName)) {
                    // La photo a été uploadée avec succès
                } else {
                    $errors['photo'] = "Erreur lors de l'upload de la photo.";
                }
            }

            // Insérer dans la base de données
            $stmt = $conn->prepare("INSERT INTO utilisateur (nom, email, mot_de_passe, genre, age, telephone, photo_profil, niveau_connaissance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $hashedPassword, $genre, $age, $telephone ?: null, $photoName, $niveau_connaissance]);

            // Récupérer l'utilisateur créé
            $userId = $conn->lastInsertId();
            $stmt = $conn->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Créer la session
            $_SESSION['user'] = [
                'id_utilisateur' => $user['id_utilisateur'],
                'nom' => $user['nom'],
                'email' => $user['email'],
                'genre' => $user['genre'],
                'age' => $user['age'],
                'photo_profil' => $user['photo_profil'],
                'telephone' => $user['telephone'],
                'niveau_connaissance' => $user['niveau_connaissance']
            ];

            // Rediriger vers la page de bienvenue
            header("Location: welcome_page.php");
            exit;
        } catch(PDOException $e) {
            $errors['db'] = "Erreur lors de la création du compte : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2D5A27;
            --secondary-color: #A4BE7B;
            --accent-color: #3C2A21;
            --light-color: #F5F5DC;
            --error-color: #dc3545;
            --success-color: #198754;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
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
                linear-gradient(90deg, rgba(45, 90, 39, 0.1) 1px, transparent 1px),
                linear-gradient(0deg, rgba(45, 90, 39, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            transform: perspective(500px) rotateX(60deg);
            transform-origin: center top;
            animation: gridMove 20s linear infinite;
            pointer-events: none;
        }

        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 30px 30px; }
        }

        .urban-accent {
            position: fixed;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(164, 190, 123, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(45, 90, 39, 0.4) 0%, transparent 50%);
            filter: blur(60px);
            opacity: 0.5;
            pointer-events: none;
        }

        .urban-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .urban-shape {
            position: absolute;
            border: 2px solid rgba(164, 190, 123, 0.3);
            border-radius: 10px;
        }

        .shape1 {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 5%;
            animation: rotateShape 20s linear infinite;
        }

        .shape2 {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 8%;
            animation: rotateShape 15s linear infinite reverse;
        }

        .shape3 {
            width: 100px;
            height: 100px;
            top: 50%;
            right: 20%;
            animation: rotateShape 10s linear infinite;
        }

        @keyframes rotateShape {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .register-container {
            width: 100%;
            max-width: 800px;
            margin: 2rem auto;
            position: relative;
            z-index: 1;
        }

        .card {
            border: none;
            border-radius: 20px;
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(164, 190, 123, 0.2);
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .card-body {
            padding: 3rem 2.5rem;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .brand-logo i {
            font-size: 3.5rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .brand-logo h2 {
            color: #ffffff;
            font-weight: 600;
            margin: 0;
            font-size: 2.2rem;
            background: linear-gradient(45deg, #ffffff 30%, var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-logo p {
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(164, 190, 123, 0.2);
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: var(--secondary-color);
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(164, 190, 123, 0.25);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(164, 190, 123, 0.2);
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-select:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: var(--secondary-color);
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(164, 190, 123, 0.25);
        }

        .form-select option {
            background-color: #1a1a1a;
            color: #ffffff;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 2.3rem;
            color: var(--secondary-color);
            opacity: 0.7;
        }

        .password-requirements {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 1rem;
            border: 1px solid rgba(164, 190, 123, 0.2);
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
            color: rgba(255, 255, 255, 0.7);
        }

        .password-requirements li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .password-requirements i {
            color: var(--secondary-color);
        }

        .urban-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .urban-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .urban-button:hover::before {
            transform: translateX(100%);
        }

        .urban-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(45, 90, 39, 0.3);
        }

        .urban-link {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 1rem;
        }

        .urban-link:hover {
            color: #ffffff;
            transform: translateX(5px);
        }

        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
            background-color: rgba(220, 53, 69, 0.2);
            color: #ffffff;
            backdrop-filter: blur(5px);
        }

        .alert-success {
            background-color: rgba(25, 135, 84, 0.2);
        }

        .gender-options {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .gender-option {
            flex: 1;
            text-align: center;
            padding: 1rem;
            border: 1px solid rgba(164, 190, 123, 0.2);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .gender-option:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .gender-option.selected {
            background: rgba(164, 190, 123, 0.2);
            border-color: var(--secondary-color);
        }

        .gender-option i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .photo-upload-container {
            position: relative;
            width: 100%;
            margin-bottom: 1rem;
        }

        .photo-upload-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            color: #ffffff;
            background: rgba(164, 190, 123, 0.2);
            border: 2px dashed rgba(164, 190, 123, 0.4);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }

        .photo-upload-button:hover {
            background: rgba(164, 190, 123, 0.3);
            border-color: rgba(164, 190, 123, 0.6);
        }

        .photo-upload-button i {
            font-size: 1.2rem;
            color: var(--secondary-color);
        }

        .photo-preview-container {
            margin-top: 1rem;
            text-align: center;
            position: relative;
            display: none;
        }

        .photo-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 12px;
            border: 2px solid rgba(164, 190, 123, 0.2);
            object-fit: cover;
        }

        .photo-preview-remove {
            position: absolute;
            top: -10px;
            right: calc(50% - 85px);
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-preview-remove:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }

        .photo-upload-text {
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .photo-requirements {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .invalid-feedback {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .age-requirements {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .form-check-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .form-check {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(164, 190, 123, 0.2);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-check:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .form-check.active {
            background: rgba(164, 190, 123, 0.2);
            border-color: var(--secondary-color);
        }

        .form-check input[type="checkbox"] {
            display: none;
        }

        .form-check-label {
            color: #ffffff;
            margin: 0;
            cursor: pointer;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 2rem 1.5rem;
            }

            .brand-logo i {
                font-size: 3rem;
            }

            .brand-logo h2 {
                font-size: 1.8rem;
            }

            .gender-options {
                flex-direction: column;
            }

            .urban-shape {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="urban-grid"></div>
    <div class="urban-accent"></div>
    <div class="urban-shapes">
        <div class="urban-shape shape1"></div>
        <div class="urban-shape shape2"></div>
        <div class="urban-shape shape3"></div>
    </div>

    <div class="register-container">
        <div class="card">
            <div class="card-body">
                <div class="brand-logo">
                    <i class="fas fa-seedling"></i>
                    <h2>PlantCare</h2>
                    <p>Créez votre compte jardinier</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="nom" class="form-label">
                            <i class="fas fa-user"></i>
                            Nom complet
                        </label>
                        <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" placeholder="Votre nom complet" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email
                        </label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Votre adresse email" required>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Mot de passe
                        </label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Créez votre mot de passe" required>
                        <div class="password-requirements">
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Au moins 8 caractères</li>
                                <li><i class="fas fa-check-circle"></i> Une lettre majuscule</li>
                                <li><i class="fas fa-check-circle"></i> Une lettre minuscule</li>
                                <li><i class="fas fa-check-circle"></i> Un chiffre</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword" class="form-label">
                            <i class="fas fa-lock"></i>
                            Confirmer le mot de passe
                        </label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirmez votre mot de passe" required>
                    </div>

                    <div class="form-group">
                        <label for="age" class="form-label">
                            <i class="fas fa-birthday-cake"></i>
                            Âge
                        </label>
                        <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" placeholder="Votre âge" required min="1" max="150">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-seedling"></i>
                            Niveau en jardinage
                        </label>
                        <div class="form-check-group">
                            <div class="form-check" onclick="toggleNiveau(this)">
                                <input type="checkbox" name="niveau_connaissance[]" value="Débutant">
                                <span class="form-check-label">Débutant</span>
                            </div>
                            <div class="form-check" onclick="toggleNiveau(this)">
                                <input type="checkbox" name="niveau_connaissance[]" value="Intermédiaire">
                                <span class="form-check-label">Intermédiaire</span>
                            </div>
                            <div class="form-check" onclick="toggleNiveau(this)">
                                <input type="checkbox" name="niveau_connaissance[]" value="Expert">
                                <span class="form-check-label">Expert</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Genre</label>
                        <div class="gender-options">
                            <div class="gender-option" onclick="selectGender('Homme')">
                                <i class="fas fa-male"></i>
                                <div>Homme</div>
                                <input type="radio" name="genre" value="Homme" <?php echo $genre === 'Homme' ? 'checked' : ''; ?> style="display: none;">
                            </div>
                            <div class="gender-option" onclick="selectGender('Femme')">
                                <i class="fas fa-female"></i>
                                <div>Femme</div>
                                <input type="radio" name="genre" value="Femme" <?php echo $genre === 'Femme' ? 'checked' : ''; ?> style="display: none;">
                            </div>
                            <div class="gender-option" onclick="selectGender('Autre')">
                                <i class="fas fa-user"></i>
                                <div>Autre</div>
                                <input type="radio" name="genre" value="Autre" <?php echo $genre === 'Autre' ? 'checked' : ''; ?> style="display: none;">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="telephone" class="form-label">
                            <i class="fas fa-phone"></i>
                            Téléphone (optionnel)
                        </label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo htmlspecialchars($telephone); ?>" placeholder="Votre numéro de téléphone">
                    </div>

                    <div class="form-group">
                        <label for="photo" class="form-label">Photo de profil (optionnel)</label>
                        <div class="photo-upload-container">
                            <label for="photo" class="photo-upload-button">
                                <i class="fas fa-camera"></i>
                                <span>Choisir une photo</span>
                            </label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png" style="display: none;">
                            <div class="photo-requirements">
                                <i class="fas fa-info-circle"></i> Format JPG ou PNG, max 2 Mo
                            </div>
                            <div class="photo-preview-container" id="photoPreviewContainer">
                                <img id="photoPreview" class="photo-preview">
                                <button type="button" class="photo-preview-remove" id="removePhoto">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="urban-button">
                        <i class="fas fa-user-plus"></i>
                        Créer mon compte
                    </button>

                    <div class="text-center mt-4">
                        <a href="login.php" class="urban-link">
                            <i class="fas fa-arrow-left"></i>
                            Retour à la connexion
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prévisualisation de la photo
        document.getElementById('photo').addEventListener('change', function(e) {
            const preview = document.getElementById('photoPreview');
            const previewContainer = document.getElementById('photoPreviewContainer');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        });

        // Supprimer la photo
        document.getElementById('removePhoto').addEventListener('click', function() {
            const input = document.getElementById('photo');
            const previewContainer = document.getElementById('photoPreviewContainer');
            input.value = '';
            previewContainer.style.display = 'none';
        });

        // Sélection du genre
        function selectGender(gender) {
            document.querySelectorAll('.gender-option').forEach(option => {
                option.classList.remove('selected');
            });
            const selectedOption = document.querySelector(`.gender-option:has(input[value="${gender}"])`);
            selectedOption.classList.add('selected');
            selectedOption.querySelector('input').checked = true;
        }

        // Initialiser la sélection du genre si une valeur est déjà sélectionnée
        window.addEventListener('load', function() {
            const selectedGender = document.querySelector('input[name="genre"]:checked');
            if (selectedGender) {
                selectGender(selectedGender.value);
            }
        });

        // Fonction pour gérer les cases à cocher du niveau de jardinage
        function toggleNiveau(element) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            element.classList.toggle('active');
            checkbox.checked = !checkbox.checked;
        }
    </script>
</body>
</html>
