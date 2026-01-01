<?php
session_start();
require_once 'config.php'; // Connexion à la base de données

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$nom = $user['nom'];
$email = $user['email'];
$genre = $user['genre'];
$photo = isset($user['photo_profil']) ? $user['photo_profil'] : null;
$telephone = isset($user['telephone']) ? $user['telephone'] : '';

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validation du nom
    $nom_modif = trim($_POST["nom"] ?? '');
    if ($nom_modif === '') {
        $errors[] = "Le nom est requis.";
    }

    // Validation de l'e-mail
    $email_modif = trim($_POST["email"] ?? '');
    if (!filter_var($email_modif, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    }

    // Validation du téléphone
    $telephone_modif = trim($_POST["telephone"] ?? '');
    if ($telephone_modif !== '' && !preg_match("/^0[0-9]{9}$/", $telephone_modif)) {
        $errors[] = "Le numéro de téléphone doit commencer par 0 et contenir 10 chiffres.";
    }

    // Validation du genre
    $genre_modif = $_POST["genre"] ?? '';
    $genres_valides = ["Homme", "Femme", "Autre"];
    if (!in_array($genre_modif, $genres_valides)) {
        $errors[] = "Genre invalide.";
    }

    // Gestion de la photo
    $nouvelle_photo = $photo; // Par défaut, garder l'ancienne
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
        $uploaded_file = handle_file_upload(
            $_FILES["photo"],
            UPLOAD_DIR_PROFILES,
            ALLOWED_IMAGE_TYPES,
            UPLOAD_MAX_SIZE
        );

        if ($uploaded_file) {
            // Supprimer l'ancienne photo si elle existe
            if ($photo && file_exists(UPLOAD_DIR_PROFILES . $photo)) {
                unlink(UPLOAD_DIR_PROFILES . $photo);
            }
            
            $nouvelle_photo = $uploaded_file;
        } else {
            $errors[] = "Erreur lors du téléchargement de la photo. Vérifiez le format et la taille du fichier.";
        }
    }

    // Vérifier si l'email est déjà utilisé par un autre utilisateur
    $stmt = $conn->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? AND id_utilisateur != ?");
    $stmt->execute([$email_modif, $user['id_utilisateur']]);
    $existe = $stmt->fetch();
    if ($existe) {
        $errors[] = "Cet e-mail est déjà utilisé par un autre compte.";
    }

    // Si pas d'erreurs, mise à jour
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE utilisateur SET nom = ?, email = ?, genre = ?, photo_profil = ?, telephone = ? WHERE id_utilisateur = ?");
            if ($stmt->execute([$nom_modif, $email_modif, $genre_modif, $nouvelle_photo, $telephone_modif, $user['id_utilisateur']])) {
                // Mise à jour de la session
                $_SESSION['user'] = array_merge($_SESSION['user'], [
                    'nom' => $nom_modif,
                    'email' => $email_modif,
                    'genre' => $genre_modif,
                    'photo_profil' => $nouvelle_photo,
                    'telephone' => $telephone_modif
                ]);

                // Message de succès dans la session
                $_SESSION['success_message'] = "Votre profil a été mis à jour avec succès !";

                // Redirection vers le dashboard après la sauvegarde
                header("Location: profil.php");
                exit;
            } else {
                $errors[] = "Erreur lors de la mise à jour du profil.";
            }
        } catch(PDOException $e) {
            $errors[] = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Modifier le profil - PlantCare</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
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

    .profile-photo::after {
      content: '\f030';
      font-family: 'Font Awesome 5 Free';
      font-weight: 900;
      position: absolute;
      bottom: -40px;
      left: 0;
      width: 100%;
      height: 40px;
      background: rgba(74, 122, 61, 0.8);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
    }

    .profile-photo:hover::after {
      bottom: 0;
    }

    .profile-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .profile-photo i {
      font-size: 4rem;
      color: var(--primary-color);
      opacity: 0.5;
    }

    #preview-container {
      width: 150px;
      height: 150px;
      margin: 0 auto 2rem;
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

    .form-control, .form-select {
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background-color: var(--light-color);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(74, 122, 61, 0.25);
    }

    .form-text {
      font-size: 0.875rem;
      color: #666;
      margin-top: 0.5rem;
    }

    .input-group-text {
      background-color: var(--light-color);
      border: 2px solid #e0e0e0;
      border-right: none;
      color: var(--primary-color);
    }

    .input-group .form-control {
      border-left: none;
    }

    .btn-success {
      background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(74, 122, 61, 0.2);
      background: linear-gradient(135deg, var(--dark-color), var(--primary-color));
    }

    .btn-outline-secondary {
      border: 2px solid #e0e0e0;
      color: #666;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      color: #333;
      transform: translateY(-2px);
    }

    .btn-outline-secondary:hover {
      background-color: #f8f9fa;
      border-color: #666;
      color: #333;
      transform: translateY(-2px);
    }

    .alert {
      border: none;
      border-radius: 15px;
      padding: 1rem 1.5rem;
      margin-bottom: 2rem;
      border-left: 4px solid;
    }

    .alert-danger {
      background-color: #fff5f5;
      border-left-color: #dc3545;
      color: #dc3545;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 500;
      margin-bottom: 2rem;
      transition: all 0.3s ease;
      border: 1px solid var(--primary-color);
      color: var(--primary-color);
      background-color: transparent;
      padding: 8px 16px;
      border-radius: 10px;
    }

    .btn-back:hover {
      background-color: var(--primary-color);
      color: white;
      transform: translateX(-5px);
    }

    .btn-back i {
      margin-right: 8px;
    }

    .custom-file-upload {
      display: none;
    }

    @media (max-width: 768px) {
      .profile-card {
        padding: 30px 20px;
      }

      .profile-photo {
        width: 120px;
        height: 120px;
      }

      #preview-container {
        width: 120px;
        height: 120px;
      }

      .btn {
        width: 100%;
        margin-bottom: 0.5rem;
      }
    }
  </style>
</head>
<body>

<div class="profile-container">
  <a href="profil.php" class="btn-back">
    <i class="fas fa-arrow-left"></i>
    Retour au profil
  </a>

  <div class="profile-card">

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="form-header text-center mb-4">
        <h2 style="color: var(--primary-color);">Modifier votre profil</h2>
        <p class="text-muted">Mettez à jour vos informations personnelles</p>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <div id="preview-container">
        <label for="photo" class="profile-photo" style="cursor: pointer;">
          <?php if ($photo && file_exists(UPLOAD_DIR_PROFILES . $photo)): ?>
            <img src="<?= UPLOAD_DIR_PROFILES . htmlspecialchars($photo) ?>" alt="Photo de profil">
          <?php else: ?>
            <i class="fas fa-user"></i>
          <?php endif; ?>
        </label>
      </div>
      
      <div class="form-group text-center">
        <input type="file" class="custom-file-upload" id="photo" name="photo" accept="image/*">
        <div class="form-text" style="color: var(--text-muted);">Cliquez sur la photo pour la modifier</div>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--primary-color);">Nom complet</label>
        <div class="input-group">
          <span class="input-group-text">
            <i class="fas fa-user"></i>
          </span>
          <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($nom); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--primary-color);">Adresse e-mail</label>
        <div class="input-group">
          <span class="input-group-text">
            <i class="fas fa-envelope"></i>
          </span>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--primary-color);">Numéro de téléphone</label>
        <div class="input-group">
          <span class="input-group-text">
            <i class="fas fa-phone"></i>
          </span>
          <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($telephone); ?>" pattern="[0-9]{10}" maxlength="10">
        </div>
        <div class="form-text" style="color: var(--text-muted);">Format : 10 chiffres (ex: 0612345678)</div>
      </div>

      <div class="form-group">
        <label class="form-label" style="color: var(--primary-color);">Genre</label>
        <div class="input-group">
          <span class="input-group-text">
            <i class="fas fa-venus-mars"></i>
          </span>
          <select name="genre" class="form-select" required>
            <option value="Homme" <?= $genre === "Homme" ? "selected" : "" ?>>Homme</option>
            <option value="Femme" <?= $genre === "Femme" ? "selected" : "" ?>>Femme</option>
            <option value="Autre" <?= $genre === "Autre" ? "selected" : "" ?>>Autre</option>
          </select>
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-success">
          <i class="fas fa-save me-2"></i>Enregistrer les modifications
        </button>
        <a href="profil.php" class="btn btn-outline-secondary">
          <i class="fas fa-times me-2"></i>Annuler
        </a>
      </div>
    </form>
  </div>
</div>

<script>
// Validation de la photo
document.getElementById('photo').addEventListener('change', function (e) {
    const file = e.target.files[0];
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-danger mt-2';
    errorDiv.style.display = 'none';
    
    // Vérifier le type de fichier
    if (file && !file.type.startsWith('image/')) {
        errorDiv.textContent = 'Veuillez sélectionner un fichier image valide (JPG, JPEG, PNG)';
        errorDiv.style.display = 'block';
        document.querySelector('.form-group.text-center').appendChild(errorDiv);
        return;
    }

    // Vérifier la taille du fichier (2MB max)
    if (file && file.size > 2097152) {
        errorDiv.textContent = 'La taille du fichier ne doit pas dépasser 2MB';
        errorDiv.style.display = 'block';
        document.querySelector('.form-group.text-center').appendChild(errorDiv);
        return;
    }

    // Si tout est OK, afficher la prévisualisation
    const reader = new FileReader();
    reader.onload = function (e) {
        const previewContainer = document.querySelector('.profile-photo');
        // Supprimer l'icône si elle existe
        const existingIcon = previewContainer.querySelector('i');
        if (existingIcon) {
            existingIcon.remove();
        }
        // Créer ou mettre à jour l'image
        let img = previewContainer.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            previewContainer.appendChild(img);
        }
        img.src = e.target.result;
        img.alt = 'Aperçu de la photo';
        
        // Supprimer l'erreur si elle existe
        const error = document.querySelector('.form-group.text-center .text-danger');
        if (error) {
            error.remove();
        }
    };
    reader.readAsDataURL(file);
});

// Validation du numéro de téléphone
const phoneInput = document.querySelector('input[name="telephone"]');
if (phoneInput) {
    phoneInput.addEventListener('input', function() {
        const value = this.value;
        // Supprimer tout ce qui n'est pas un chiffre
        this.value = value.replace(/[^0-9]/g, '');
    });

    phoneInput.addEventListener('blur', function() {
        const value = this.value;
        if (value && value.length !== 10) {
            this.setCustomValidity('Le numéro de téléphone doit contenir exactement 10 chiffres');
        } else {
            this.setCustomValidity('');
        }
    });

    phoneInput.addEventListener('invalid', function() {
        this.reportValidity();
    });
}
</script>

</body>
</html>
