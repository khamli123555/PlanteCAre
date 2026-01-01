<?php
// config.php - Configuration de la base de données

// Paramètres de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'plantecare');
define('DB_USER', 'root');
define('DB_PASS', 'Oussama123@@');

// Configuration des uploads
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2 Mo
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png']);
define('UPLOAD_DIR_PLANTS', 'uploads/plants/');
define('UPLOAD_DIR_NOTES', 'uploads/notes/');
define('UPLOAD_DIR_PROFILES', 'uploads/profiles/');

// Configuration de la session
define('SESSION_LIFETIME', 7200); // 2 heures
define('SESSION_REGENERATION_TIME', 1800); // 30 minutes

// Démarrer une session sécurisée si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Établir la connexion à la base de données
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch(PDOException $e) {
    error_log("Erreur de connexion à la base de données : " . $e->getMessage());
    die("Une erreur est survenue lors de la connexion à la base de données.");
}

// Fonctions utilitaires
function is_logged_in() {
    return isset($_SESSION['user']) && isset($_SESSION['user']['id_utilisateur']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] ?: 'info';
        $message = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

// Création des dossiers d'upload si nécessaire
function ensure_upload_dirs() {
    $dirs = [UPLOAD_DIR_PLANTS, UPLOAD_DIR_NOTES, UPLOAD_DIR_PROFILES];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Fonction pour gérer les uploads de fichiers
function handle_file_upload($file, $destination_dir, $allowed_types = ALLOWED_IMAGE_TYPES, $max_size = UPLOAD_MAX_SIZE) {
    try {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Paramètres invalides.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Le fichier dépasse la taille autorisée.');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('Aucun fichier n\'a été téléchargé.');
            default:
                throw new RuntimeException('Erreur inconnue.');
        }

        if ($file['size'] > $max_size) {
            throw new RuntimeException('Le fichier dépasse la taille limite.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        if (!in_array($mime_type, $allowed_types)) {
            throw new RuntimeException('Format de fichier non autorisé.');
        }

        $extension = array_search($mime_type, [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ], true);

        if (false === $extension) {
            throw new RuntimeException('Format de fichier invalide.');
        }

        $filename = sprintf(
            '%s.%s',
            sha1_file($file['tmp_name']),
            $extension
        );

        if (!move_uploaded_file(
            $file['tmp_name'],
            sprintf('%s/%s',
                rtrim($destination_dir, '/'),
                $filename
            )
        )) {
            throw new RuntimeException('Impossible de déplacer le fichier.');
        }

        return $filename;

    } catch (RuntimeException $e) {
        error_log("Erreur lors de l'upload : " . $e->getMessage());
        return false;
    }
}

// Initialisation
ensure_upload_dirs();

?>