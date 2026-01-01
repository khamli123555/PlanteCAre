<?php
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$message = '';

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: listeplante.php');
    exit();
}

$id_plante = (int)$_GET['id'];
$user_id = $_SESSION['user']['id_utilisateur'];

// Vérifier si la confirmation est reçue
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        // Récupérer d'abord les informations de la plante pour la photo
        $stmt = $conn->prepare("SELECT photo FROM Plante WHERE id_plante = :id AND id_utilisateur = :user_id");
        $stmt->execute([
            ':id' => $id_plante,
            ':user_id' => $user_id
        ]);
        $plante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($plante) {
            // Supprimer d'abord toutes les notes associées
            $stmt = $conn->prepare("DELETE FROM Note WHERE id_plante = :id_plante");
            $stmt->execute([':id_plante' => $id_plante]);

            // Supprimer la photo si elle existe
            if (!empty($plante['photo'])) {
                $photo_path = "uploads/plants/" . $plante['photo'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }

            // Supprimer la plante de la base de données
            $stmt = $conn->prepare("DELETE FROM Plante WHERE id_plante = :id AND id_utilisateur = :user_id");
            $stmt->execute([
                ':id' => $id_plante,
                ':user_id' => $user_id
            ]);

            header('Location: listeplante.php?message=deleted');
            exit();
        } else {
            $message = "Plante non trouvée ou vous n'avez pas les droits pour la supprimer.";
        }
    } catch(PDOException $e) {
        $message = "Erreur lors de la suppression : " . $e->getMessage();
    }
} else {
    // Vérifier si la plante existe et appartient à l'utilisateur
    try {
        $stmt = $conn->prepare("SELECT nom, photo FROM Plante WHERE id_plante = :id AND id_utilisateur = :user_id");
        $stmt->execute([
            ':id' => $id_plante,
            ':user_id' => $user_id
        ]);
        $plante = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plante) {
            header('Location: listeplante.php');
            exit();
        }
    } catch(PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Supprimer une plante - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css_styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8B7355;
            --secondary-color: #8B7355;
            --accent-color: #A4BE7B;
            --background-dark: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #e0e0e0;
            --text-muted: #a0a0a0;
            --border-color: #3d3d3d;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
        }

        body {
            background-color: var(--background-dark);
            color: var(--text-color);
        }

        .delete-container {
            max-width: 500px;
            margin: 60px auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        .delete-container .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .plant-preview {
            text-align: center;
            margin-bottom: 30px;
        }

        .plant-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            border: 2px solid var(--primary-color);
            object-fit: cover;
        }

        .plant-name {
            font-size: 1.8rem;
            color: var(--accent-color);
            margin: 20px 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .warning-text {
            color: #ff9e80;
            font-size: 1rem;
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 8px;
            border-left: 4px solid var(--danger-color);
        }

        .confirmation-text {
            text-align: center;
            margin: 30px 0;
            color: var(--text-color);
            font-size: 1.2rem;
            line-height: 1.6;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger-color), var(--danger-hover));
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 8px;
            width: 100%;
            margin: 15px 0;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, var(--danger-hover), var(--danger-color));
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-delete:active {
            transform: translateY(1px);
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            transform: translateY(-2px);
            border-color: var(--accent-color);
        }

        .header h3 {
            color: var(--accent-color);
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }

        .header h3:after {
            content: '';
            position: absolute;
            width: 60px;
            height: 3px;
            background: var(--accent-color);
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 3px;
        }

        .no-photo {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #333, #222);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px dashed var(--border-color);
        }

        .no-photo i {
            font-size: 3.5rem;
            color: var(--accent-color);
            opacity: 0.7;
        }

        /* Animation de secousse pour le bouton de suppression */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .btn-delete:hover {
            animation: shake 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <?php include 'profile_button.php'; ?>

    <div class="main-content">
        <div class="delete-container">
            <div class="header">
                <h3>Supprimer la plante</h3>
              
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="plant-preview">
                <?php if(!empty($plante['photo']) && file_exists("uploads/plants/" . $plante['photo'])): ?>
                    <img src="uploads/plants/<?php echo htmlspecialchars($plante['photo']); ?>" alt="Photo de la plante">
                <?php else: ?>
                    <div class="no-photo">
                        <i class="fas fa-leaf"></i>
                    </div>
                <?php endif; ?>
                <div class="plant-name"><?php echo htmlspecialchars($plante['nom']); ?></div>
            </div>

            <div class="confirmation-text">
                Êtes-vous sûr de vouloir supprimer cette plante ?
            </div>

            <div class="warning-text">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cette action est irréversible et supprimera définitivement la plante, ses notes et sa photo.
            </div>

            <form method="POST">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn btn-delete">
                    <i class="fas fa-trash me-2"></i>Oui, supprimer cette plante
                </button>
                <a href="listeplante.php" class="btn btn-cancel">
                    <i class="fas fa-times me-2"></i>Non, annuler
                </a>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>