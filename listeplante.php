<?php
session_start();
require_once 'config.php';

// Debug - Afficher le contenu de la session
error_log('=== DEBUG LISTEPLANTE SESSION ===');
error_log(print_r($_SESSION, true));
error_log('==================');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Debug - Afficher les informations de l'utilisateur
$user_id = $_SESSION['user']['id_utilisateur'];
error_log('User ID: ' . $user_id);
error_log('User data: ' . print_r($_SESSION['user'], true));

// Récupérer les informations de l'utilisateur depuis la base de données
try {
    $stmt = $conn->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('User data from DB: ' . print_r($user_data, true));
} catch(PDOException $e) {
    error_log('Erreur lors de la récupération des données utilisateur: ' . $e->getMessage());
}

$message = '';
$plantes = [];
$categories = [];

// Récupérer les paramètres de tri et filtrage
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nom';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Message de confirmation de suppression
if (isset($_GET['message']) && $_GET['message'] === 'deleted') {
    $message = "La plante a été supprimée avec succès.";
}

try {
    // Récupérer les catégories pour le filtre
    $stmt = $conn->query("SELECT * FROM Categorie ORDER BY nom_categorie");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Construire la requête SQL de base
    $sql = "SELECT p.*, c.nom_categorie, 
                   CASE 
                       WHEN p.besoins_eau = 'Faible' THEN 1
                       WHEN p.besoins_eau = 'Modéré' THEN 2
                       WHEN p.besoins_eau = 'Élevé' THEN 3
                       ELSE 2
                   END as niveau_eau,
                   CASE 
                       WHEN p.`besoins_lumière` = 'Faible' THEN 1
                       WHEN p.`besoins_lumière` = 'Modéré' THEN 2
                       WHEN p.`besoins_lumière` = 'Direct' THEN 3
                       ELSE 2
                   END as niveau_lumiere,
                   p.besoins_eau as besoin_eau_texte,
                   p.`besoins_lumière` as besoin_lumiere_texte
            FROM Plante p 
            LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
            WHERE p.id_utilisateur = :user_id";
    
    // Déterminer l'ID de l'utilisateur
    $params = [':user_id' => $user_id];

    // Ajouter les conditions de filtrage
    if ($filter_type) {
        $sql .= " AND LOWER(p.type) = LOWER(:type)";
        $params[':type'] = $filter_type;
    }
    if ($filter_categorie) {
        $sql .= " AND p.id_categorie = :categorie";
        $params[':categorie'] = $filter_categorie;
    }
    if ($search) {
        $search = trim($search); // Nettoyer la recherche
        $sql .= " AND (
            LOWER(p.nom) LIKE :search_nom OR 
            LOWER(p.remarques) LIKE :search_remarques OR 
            LOWER(p.type) LIKE :search_type OR 
            LOWER(p.besoins_eau) LIKE :search_eau OR 
            LOWER(p.`besoins_lumière`) LIKE :search_lumiere OR
            LOWER(c.nom_categorie) LIKE :search_categorie
        )";
        $search_param = "%" . strtolower($search) . "%";
        $params[':search_nom'] = $search_param;
        $params[':search_remarques'] = $search_param;
        $params[':search_type'] = $search_param;
        $params[':search_eau'] = $search_param;
        $params[':search_lumiere'] = $search_param;
        $params[':search_categorie'] = $search_param;
    }

    // Ajouter le filtre de date
    if ($date_filter) {
        switch($date_filter) {
            case 'today':
                $sql .= " AND DATE(p.date_plantation) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND YEARWEEK(p.date_plantation, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $sql .= " AND YEAR(p.date_plantation) = YEAR(CURDATE()) AND MONTH(p.date_plantation) = MONTH(CURDATE())";
                break;
            case 'custom':
                if ($date_start && $date_end) {
                    $sql .= " AND p.date_plantation BETWEEN :date_start AND :date_end";
                    $params[':date_start'] = $date_start;
                    $params[':date_end'] = $date_end;
                }
                break;
        }
    }

    // Ajouter le tri
    switch($sort) {
        case 'date_plantation_asc':
            $sql .= " ORDER BY p.date_plantation ASC";
            break;
        case 'date_plantation_desc':
            $sql .= " ORDER BY p.date_plantation DESC";
            break;
        case 'nom':
        default:
            $sql .= " ORDER BY p.nom ASC";
            break;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $plantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
}

// Fonction pour générer les liens de tri
function getSortLink($field, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $field;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Plantes - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css_styles.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="scripts.js"></script>
    <style>
        :root {
            --primary-color: #8B7355;
            --secondary-color: #8B7355;
            --accent-color: #A4BE7B;
            --light-color: #F5F5DC;
            --dark-color: #3C2A21;
            --text-color: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.7);
            --border-color: rgba(139, 115, 85, 0.2);
            --card-bg: rgba(26, 26, 26, 0.7);
            --input-bg: rgba(26, 26, 26, 0.7);
            --input-bg-focus: rgba(26, 26, 26, 0.9);
            --font-family: 'Poppins', sans-serif;
            --primary-gradient: linear-gradient(135deg, #8B7355, #3C2A21);
            --primary-gradient-hover: linear-gradient(135deg, #7a6548, #2a1c15);
            --secondary-gradient: linear-gradient(135deg, #8B7355, #3C2A21);
            --secondary-gradient-hover: linear-gradient(135deg, #7a6548, #2a1c15);
            --danger-gradient: linear-gradient(135deg, #D57E7E, #E9967A);
            --danger-gradient-hover: linear-gradient(135deg, #c56e6e, #d88670);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.25);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.3);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.4);
            --blur-effect: blur(10px);
        }

        body {
            font-family: var(--font-family);
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
                radial-gradient(circle at 20% 20%, rgba(139, 115, 85, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(60, 42, 33, 0.4) 0%, transparent 50%);
            filter: blur(60px);
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 30px 30px; }
        }

        .main-content {
            position: relative;
            z-index: 1;
            padding-top: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, rgba(139, 115, 85, 0.1) 0%, rgba(60, 42, 33, 0.2) 100%);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(139, 115, 85, 0.2);
            backdrop-filter: var(--blur-effect);
        }

        .title-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .icon-circle {
            width: 45px;
            height: 45px;
            background: rgba(164, 190, 123, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(164, 190, 123, 0.3);
        }

        .icon-circle i {
            font-size: 1.2rem;
            color: #A4BE7B;
        }

        .title-content h1 {
            font-size: 2rem;
            margin: 0;
            color: #ffffff;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .title-content p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 5px 0 0;
            font-weight: 400;
        }

        .stats-grid {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .stat-box {
            background: rgba(28, 28, 28, 0.4);
            padding: 6px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .stat-icon {
            width: 24px;
            height: 24px;
            background: rgba(139, 115, 85, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 0.8rem;
            color: var(--accent-color);
        }

        .stat-info {
            flex: 1;
        }

        .stat-info .stat-number {
            font-size: 0.9rem;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .stat-info .stat-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
        }

        .guest-timer {
            display: none;
        }

        .filters {
            background: rgba(28, 28, 28, 0.6);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(164, 190, 123, 0.2);
            backdrop-filter: blur(10px);
        }

        .form-label {
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-label i {
            color: #A4BE7B;
            font-size: 1.1rem;
        }

        .form-control, .form-select {
            background: rgba(28, 28, 28, 0.6);
            border: 1px solid rgba(164, 190, 123, 0.2);
            color: #ffffff;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(28, 28, 28, 0.8);
            border-color: rgba(164, 190, 123, 0.4);
            box-shadow: 0 0 0 0.2rem rgba(164, 190, 123, 0.25);
            color: #ffffff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.95rem;
        }

        .input-group {
            background: rgba(28, 28, 28, 0.6);
            border-radius: 8px;
            border: 1px solid rgba(164, 190, 123, 0.2);
            padding: 2px;
        }

        .input-group .form-control {
            border: none;
            background: transparent;
        }

        .input-group .btn {
            border-radius: 6px;
            margin: 0;
            padding: 8px 15px;
            background: rgba(164, 190, 123, 0.2);
            border: 1px solid rgba(164, 190, 123, 0.3);
            color: #ffffff;
        }

        .input-group .btn:hover {
            background: rgba(164, 190, 123, 0.3);
            border-color: rgba(164, 190, 123, 0.4);
        }

        .input-group .btn i {
            color: #A4BE7B;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn i {
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #A4BE7B, #8B7355);
            border: none;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #8B7355, #A4BE7B);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border: none;
            color: #ffffff;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #45a049, #4CAF50);
            transform: translateY(-2px);
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid rgba(164, 190, 123, 0.4);
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: rgba(164, 190, 123, 0.1);
            border-color: rgba(164, 190, 123, 0.6);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .btn-outline-primary i {
            margin-right: 8px;
            color: #A4BE7B;
        }

        .filters .btn {
            padding: 10px 20px;
            font-weight: 500;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .alert-success {
            background: rgba(139, 115, 85, 0.1);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 0 15px;
        }

        .stat-card {
            background: rgba(28, 28, 28, 0.6);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(164, 190, 123, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(164, 190, 123, 0.1), rgba(28, 28, 28, 0));
            z-index: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(164, 190, 123, 0.4);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(164, 190, 123, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            position: relative;
            z-index: 2;
            border: 1px solid rgba(164, 190, 123, 0.3);
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #A4BE7B;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin: 10px 0;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .stat-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 2;
            font-weight: 500;
        }

        .plant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .plant-card {
            background: linear-gradient(to bottom, #1e2a3a, #141e2a);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            height: 450px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .plant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .plant-image {
            position: relative;
            height: 200px;
            overflow: hidden;
            border-radius: 10px 10px 0 0;
            display: block;
        }

        .plant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
        }

        .plant-card:hover .plant-image img {
            transform: scale(1.05);
        }

        .zoom-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            color: white;
            font-size: 2.5rem;
            z-index: 2;
            transition: all 0.3s ease;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }

        .plant-card:hover .zoom-icon {
            transform: translate(-50%, -50%) scale(1);
        }

        .plant-card:hover .zoom-icon {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .plant-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background: transparent;
            min-height: 250px;
        }

        .plant-name {
            font-size: 1.4rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
            line-height: 1.4;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .plant-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
            flex: 1;
            padding: 10px 5px;
        }

        .info-actions-container {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .info-actions-container:hover {
            transform: translateY(-2px);
        }

        .info-actions-content {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .info-section {
            flex: 1;
        }

        .actions-section {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
            min-height: 70px;
        }

        .action-button {
            width: 45px;
            height: 45px;
            font-size: 1rem;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            margin: 0;
            padding: 0;
        }

        .action-button i {
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
            height: auto;
            line-height: normal;
        }

        .action-button::before {
            content: attr(data-text);
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            white-space: nowrap;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            text-transform: capitalize;
            z-index: 1;
            pointer-events: none;
        }

        .action-button:hover {
            width: 130px;
            border-radius: 25px;
        }

        .action-button:hover i {
            opacity: 0;
        }

        .action-button:hover::before {
            opacity: 1;
        }

        .action-button.edit {
            background: rgba(33, 150, 243, 0.3);
            border-color: rgba(33, 150, 243, 0.5);
        }

        .action-button.edit:hover {
            background: rgba(33, 150, 243, 0.4);
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
        }

        .action-button.notes {
            background: rgba(255, 193, 7, 0.3);
            border-color: rgba(255, 193, 7, 0.5);
        }

        .action-button.notes:hover {
            background: rgba(255, 193, 7, 0.4);
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
        }

        .action-button.delete {
            background: rgba(244, 67, 54, 0.3);
            border-color: rgba(244, 67, 54, 0.5);
        }

        .action-button.delete:hover {
            background: rgba(244, 67, 54, 0.4);
            box-shadow: 0 0 15px rgba(244, 67, 54, 0.3);
        }

        .info-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 8px;
        }

        .info-item {
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(40, 40, 40, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 0;
            height: 100%;
            min-height: 45px;
        }

        .info-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .info-item span {
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9rem;
        }

        /* Type styles */
        .info-item.type {
            border-left: 3px solid #2ecc71;
        }
        .info-item.type i {
            color: #2ecc71;
        }
        .info-item.type:hover {
            background: rgba(46, 204, 113, 0.2);
            transform: translateX(5px);
            border-color: #2ecc71;
        }

        /* Arrosage styles */
        .info-item.water {
            border-left: 3px solid #3498db;
        }
        .info-item.water i {
            color: #3498db;
        }
        .info-item.water:hover {
            background: rgba(52, 152, 219, 0.2);
            transform: translateX(5px);
            border-color: #3498db;
        }

        /* Lumière styles */
        .info-item.light {
            border-left: 3px solid #f1c40f;
        }
        .info-item.light i {
            color: #f1c40f;
        }
        .info-item.light:hover {
            background: rgba(241, 196, 15, 0.2);
            transform: translateX(5px);
            border-color: #f1c40f;
        }

        /* Catégorie styles */
        .info-item:has(i.fa-tag) {
            border-left: 3px solid #9b59b6;
        }
        .info-item:has(i.fa-tag) i {
            color: #9b59b6;
        }
        .info-item:has(i.fa-tag):hover {
            background: rgba(155, 89, 182, 0.2);
            transform: translateX(5px);
            border-color: #9b59b6;
        }

        .plant-actions {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
                justify-content: center;
            }

        .navigation-buttons {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navigation-buttons.sidebar-active {
            transform: translateX(280px);
        }

        .add-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border: none;
            border-radius: 50%;
            color: #ffffff;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .add-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #45a049, #4CAF50);
        }

        .add-button i {
            transition: transform 0.3s ease;
        }

        .add-button:hover i {
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            .navigation-buttons.sidebar-active {
                transform: translateX(250px);
            }

            .add-button {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .info-actions-container {
                padding: 15px;
            }

            .info-actions-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .actions-section {
                width: 100%;
                justify-content: center;
                gap: 15px;
            }

            .info-content {
                grid-template-columns: 1fr;
            }

            .action-button {
                width: 45px;
                height: 45px;
            }

            .action-button:hover {
                width: auto;
                min-width: 130px;
                padding: 10px 30px;
            }

            .info-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="urban-grid"></div>
    <div class="urban-accent"></div>

    <div class="navigation-buttons">
        <button class="menu-btn" id="menu-toggle" title="Menu">
            <i class="fas fa-bars"></i>
        </button>
        </div>

    <a href="ajouter_plante.php" class="add-button" title="Ajouter une plante">
        <i class="fas fa-plus"></i>
    </a>

    <div class="main-content">
        <div class="page-header">
            <div class="container">
                <div class="title-wrapper">
                    <div class="icon-circle">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <div class="title-content">
                        <h1>Ma Collection de Plantes</h1>
                        <p class="lead">Gérez et suivez l'évolution de vos plantes avec facilité</p>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="stat-info">
                            <p class="stat-number"><?= count(array_filter($plantes, function($p) { return strtolower($p['type']) === 'intérieur'; })) ?></p>
                            <p class="stat-label">Plantes d'Intérieur</p>
                        </div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-sun"></i>
                        </div>
                        <div class="stat-info">
                            <p class="stat-number"><?= count(array_filter($plantes, function($p) { return strtolower($p['type']) === 'extérieur'; })) ?></p>
                            <p class="stat-label">Plantes d'Extérieur</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-search"></i> Rechercher une plante
                        </label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Rechercher par nom, type, besoins...">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fas fa-filter"></i> Type de plante
                        </label>
                        <select name="type" class="form-select">
                            <option value="">Tous les types</option>
                            <option value="Intérieur" <?= $filter_type === 'Intérieur' ? 'selected' : '' ?>>Plantes d'intérieur</option>
                            <option value="Extérieur" <?= $filter_type === 'Extérieur' ? 'selected' : '' ?>>Plantes d'extérieur</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fas fa-sort"></i> Trier par
                        </label>
                        <select name="sort" class="form-select">
                            <option value="nom" <?= $sort === 'nom' ? 'selected' : '' ?>>Par date de plantation</option>
                            <option value="date_plantation_desc" <?= $sort === 'date_plantation_desc' ? 'selected' : '' ?>>Plus récentes</option>
                            <option value="date_plantation_asc" <?= $sort === 'date_plantation_asc' ? 'selected' : '' ?>>Plus anciennes</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Appliquer
                        </button>
                        <a href="listeplante.php" class="btn btn-outline-primary">
                            <i class="fas fa-undo"></i> Réinitialiser les filtres
                            </a>
                    </div>
                </form>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="stat-number"><?= count($plantes) ?></div>
                    <div class="stat-label">Total des Plantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-number">
                        <?= count(array_filter($plantes, function($p) { return strtolower($p['type']) === 'intérieur'; })) ?>
                    </div>
                    <div class="stat-label">Plantes d'Intérieur</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="stat-number">
                        <?= count(array_filter($plantes, function($p) { return strtolower($p['type']) === 'extérieur'; })) ?>
                    </div>
                    <div class="stat-label">Plantes d'Extérieur</div>
                </div>
            </div>

            <?php if (empty($plantes)): ?>
                <div class="empty-state">
                    <i class="fas fa-leaf"></i>
                    <h3>Aucune plante pour le moment</h3>
                    <p>Commencez à ajouter vos plantes pour les suivre et en prendre soin !</p>
                </div>
            <?php else: ?>
                <div class="plant-grid">
                    <?php foreach ($plantes as $plante): ?>
                        <div class="plant-card">
                            <div class="plant-image">
                                <?php if ($plante['photo'] && file_exists("uploads/plants/" . $plante['photo'])): ?>
                                    <img src="uploads/plants/<?= htmlspecialchars($plante['photo']) ?>" alt="<?= htmlspecialchars($plante['nom']) ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/300x200?text=Pas+de+photo" alt="Pas de photo">
                                <?php endif; ?>
                                <i class="fas fa-search-plus zoom-icon"></i>
                            </div>
                            <div class="plant-info">
                                <h3 class="plant-name"><?= htmlspecialchars($plante['nom']) ?></h3>
                                
                                <div class="info-actions-container">
                                    <div class="info-actions-content">
                                        <div class="info-section">
                                            <div class="info-content">
                                                <div class="info-item type">
                                                    <i class="<?= $plante['type'] === 'Intérieur' ? 'fas fa-home' : 'fas fa-sun' ?>"></i>
                                                    <span>Type: <?= htmlspecialchars($plante['type']) ?></span>
                                    </div>
                                                <?php if ($plante['besoins_eau'] || $plante['besoins_lumière']): ?>
                                                    <div class="info-row">
                                                        <?php if ($plante['besoins_eau']): ?>
                                                            <div class="info-item water">
                                        <i class="fas fa-tint"></i>
                                                                <span><?= htmlspecialchars($plante['besoins_eau']) ?></span>
                                    </div>
                                                        <?php endif; ?>
                                                        <?php if ($plante['besoins_lumière']): ?>
                                                            <div class="info-item light">
                                        <i class="fas fa-sun"></i>
                                                                <span><?= htmlspecialchars($plante['besoins_lumière']) ?></span>
                                    </div>
                                                        <?php endif; ?>
                                </div>
                                                <?php endif; ?>
                                                <?php if ($plante['nom_categorie']): ?>
                                                <div class="info-item">
                                                    <i class="fas fa-tag"></i>
                                                    <span>Catégorie: <?= htmlspecialchars($plante['nom_categorie']) ?></span>
                                    </div>
                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="actions-section">
                                        <a href="modifier.php?id=<?= htmlspecialchars($plante['id_plante']) ?>" class="action-button edit" data-text="Modifier" onclick="console.log('ID plante:', <?= json_encode($plante['id_plante']) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                        <a href="note_suivie.php?id=<?= $plante['id_plante'] ?>" class="action-button notes" data-text="Note">
                                            <i class="fas fa-clipboard"></i>
                                    </a>
                                        <a href="supprimer.php?id=<?= $plante['id_plante'] ?>" class="action-button delete" data-text="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateFilter = document.querySelector('select[name="date_filter"]');
        const dateRange = document.querySelector('.date-range');

        function toggleDateRange() {
            if (dateFilter.value === 'custom') {
                dateRange.style.display = 'block';
            } else {
                dateRange.style.display = 'none';
            }
        }

        dateFilter.addEventListener('change', toggleDateRange);
        toggleDateRange(); // Initial check
    });
    </script>
</body>
</html>