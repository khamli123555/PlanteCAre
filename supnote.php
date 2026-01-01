<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$note_id) {
    header('Location: dashboard.php');
    exit();
}

// Récupérer la note et vérifier les permissions
$stmt = $pdo->prepare("
    SELECT n.*, p.nom as plant_name, p.id_utilisateur, p.id as plant_id
    FROM notes n 
    JOIN plantes p ON n.id_plante = p.id 
    WHERE n.id = ? AND p.id_utilisateur = ?
");
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$note) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Traitement de la confirmation de suppression
if (isset($_POST['confirm_delete'])) {
    try {
        // Supprimer la photo associée si elle existe
        if ($note['photo'] && file_exists('uploads/notes/' . $note['photo'])) {
            unlink('uploads/notes/' . $note['photo']);
        }
        
        // Supprimer la note de la base de données
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->execute([$note_id]);
        
        $message = 'Note supprimée avec succès !';
        
        // Redirection après 2 secondes
        header("refresh:2;url=history.php?plant_id=" . $note['plant_id']);
    } catch (PDOException $e) {
        $error = 'Erreur lors de la suppression de la note : ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer la note - <?php echo htmlspecialchars($note['plant_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Supprimer la note</h1>
            <p>Pour la plante : <strong><?php echo htmlspecialchars($note['plant_name']); ?></strong></p>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="delete-confirmation">
                <div class="alert alert-warning">
                    <h3>⚠️ Confirmation de suppression</h3>
                    <p>Êtes-vous sûr de vouloir supprimer cette note ? Cette action est irréversible.</p>
                </div>

                <div class="note-preview">
                    <h4>Aperçu de la note à supprimer :</h4>
                    <div class="note-card">
                        <div class="note-date">
                            <?php echo date('d/m/Y à H:i', strtotime($note['date_note'])); ?>
                        </div>
                        <div class="note-content">
                            <?php echo nl2br(htmlspecialchars($note['commentaire'])); ?>
                        </div>
                        <?php if ($note['photo']): ?>
                            <div class="note-photo">
                                <img src="uploads/notes/<?php echo htmlspecialchars($note['photo']); ?>" 
                                     alt="Photo de la note" style="max-width: 200px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" onsubmit="return confirmDelete()">
                    <div class="form-actions">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            Confirmer la suppression
                        </button>
                        <a href="history.php?plant_id=<?php echo $note['plant_id']; ?>" class="btn btn-secondary">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function confirmDelete() {
            return confirm('Êtes-vous vraiment sûr de vouloir supprimer cette note ? Cette action ne peut pas être annulée.');
        }
    </script>
</body>
</html>
