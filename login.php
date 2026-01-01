<?php
session_start();
require_once 'config.php';

// Débogage - Afficher la méthode de requête
error_log('Méthode de requête: ' . $_SERVER['REQUEST_METHOD']);

// Récupérer le message s'il existe
$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'guest_ended':
            $message = "Veuillez créer un compte pour continuer.";
            break;
        case 'expired':
            $errors[] = "Veuillez vous connecter ou créer un compte pour continuer.";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $error_message = "Email ou mot de passe incorrect.";
            break;
        case 'empty':
            $error_message = "Veuillez remplir tous les champs.";
            break;
        case 'session_expired':
            $error_message = "Votre session a expiré. Veuillez vous reconnecter.";
            break;
        default:
            $error_message = "Une erreur est survenue.";
    }
}

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Débogage - Afficher les données POST
    error_log('Données POST reçues: ' . print_r($_POST, true));
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Veuillez remplir tous les champs.";
    } else {
        try {
            // Vérifier la connexion à la base de données
            if (!isset($conn)) {
                throw new Exception("La connexion à la base de données n'est pas établie.");
            }

            $stmt = $conn->prepare("SELECT * FROM utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Débogage - Afficher le résultat de la requête
            error_log('Résultat de la requête: ' . print_r($user, true));

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Nettoyer la session
                session_unset();
                session_regenerate_id(true);
                
                // Stocker les informations de l'utilisateur dans la session
                $_SESSION['user'] = [
                    'id_utilisateur' => $user['id_utilisateur'],
                    'nom' => $user['nom'],
                    'email' => $user['email'],
                    'genre' => $user['genre'],
                    'telephone' => $user['telephone'],
                    'photo_profil' => $user['photo_profil'],
                    'niveau_connaissance' => $user['niveau_connaissance']
                ];

                // Débogage - Afficher la session
                error_log('Session après connexion: ' . print_r($_SESSION, true));

                // Redirection vers le dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Email ou mot de passe incorrect.";
            }
        } catch(Exception $e) {
            error_log("Erreur de connexion : " . $e->getMessage());
            $errors[] = "Erreur de connexion à la base de données: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PlantCare</title>
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
            padding: 20px;
            position: relative;
            overflow: hidden;
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

        .login-container {
            width: 100%;
            max-width: 450px;
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

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 2.3rem;
            color: var(--secondary-color);
            opacity: 0.7;
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

        @media (max-width: 576px) {
            .card-body {
                padding: 2rem 1.5rem;
            }

            .brand-logo i {
                font-size: 3rem;
            }

            .brand-logo h2 {
                font-size: 1.8rem;
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

    <div class="login-container">
        <div class="card">
            <div class="card-body">
                <div class="brand-logo">
                    <i class="fas fa-seedling"></i>
                    <h2>PlantCare</h2>
                    <p>Connectez-vous à votre espace vert</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
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
                        <input type="password" class="form-control" id="password" name="password" placeholder="Votre mot de passe" required>
                    </div>

                    <button type="submit" class="urban-button">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </button>
                </form>

                <div class="text-center">
                    <a href="register.php" class="urban-link">
                        <i class="fas fa-user-plus"></i>
                        Créer un compte
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>