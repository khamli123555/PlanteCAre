<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

//Récupérer les informations de l'utilisateur
$user = $_SESSION['user'];
$user_id = $user['id_utilisateur'];

// Récupérer les statistiques
try {
    // Nombre total de plantes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Plante WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $total_plantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nombre de plantes d'intérieur
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Plante WHERE id_utilisateur = ? AND LOWER(type) = 'intérieur'");
    $stmt->execute([$user_id]);
    $plantes_interieur = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nombre de plantes d'extérieur
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Plante WHERE id_utilisateur = ? AND LOWER(type) = 'extérieur'");
    $stmt->execute([$user_id]);
    $plantes_exterieur = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Dernière plante ajoutée
    $stmt = $conn->prepare("SELECT nom, date_plantation, type FROM Plante WHERE id_utilisateur = ? ORDER BY id_plante DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $derniere_plante = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prochaine plante à arroser
    $stmt = $conn->prepare("
        SELECT nom, date_plantation, besoins_eau
        FROM Plante 
        WHERE id_utilisateur = ? 
        AND besoins_eau IS NOT NULL
        ORDER BY date_plantation DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $prochaine_arrosage = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les dernières notes
    $stmt = $conn->prepare("
        SELECT n.*, p.nom as nom_plante, p.photo as photo_plante
        FROM Note n
        JOIN Plante p ON n.id_plante = p.id_plante
        WHERE p.id_utilisateur = ?
        ORDER BY n.date_ajout DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $dernieres_notes = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log("Erreur dans dashboard.php : " . $e->getMessage());
    $total_plantes = 0;
    $plantes_interieur = 0;
    $plantes_exterieur = 0;
    $derniere_plante = null;
    $prochaine_arrosage = null;
    $dernieres_notes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css_styles.css" rel="stylesheet">
    <style>
        /* Styles pour la modale */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            padding: 30px;
            position: relative;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            transform: translateY(20px);
            transition: transform 0.3s ease-out;
            animation: modalFadeIn 0.4s forwards;
        }

        @keyframes modalFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal .close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: var(--text-secondary);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal .close:hover {
            color: var(--accent-color);
            text-decoration: none;
        }

        .modal h3 {
            color: var(--accent-color);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal h3 i {
            font-size: 1.3em;
        }

        .modal p {
            color: var(--text-primary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .modal button {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(164, 190, 123, 0.3);
        }

        .modal button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(164, 190, 123, 0.4);
        }

        .modal button i {
            font-size: 1.1em;
        }

        /* Bouton flottant du conseil du jour */
        .floating-tip-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--accent-gradient);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 100;
            transition: all 0.3s ease;
            border: none;
        }

        .floating-tip-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .floating-tip-btn i {
            transition: transform 0.3s ease;
        }

        .floating-tip-btn:hover i {
            transform: rotate(10deg);
        }

        :root {
            /* Couleurs principales */
            --primary-color: #8B7355;    /* Marron clair */
            --secondary-color: #6B5A45;  /* Marron plus foncé */
            --accent-color: #A4BE7B;     /* Vert doux */
            --accent-hover: #8FAA6A;     /* Vert plus foncé au survol */
            
            /* Arrière-plans */
            --bg-dark: #1A1A1A;          /* Fond principal très foncé */
            --bg-darker: #121212;        /* Fond secondaire plus foncé */
            --card-bg: #252525;          /* Fond des cartes */
            --card-hover: #2E2E2E;       /* Fond des cartes au survol */
            
            /* Textes */
            --text-primary: #E0E0E0;     /* Texte principal */
            --text-secondary: #A0A0A0;   /* Texte secondaire */
            --text-muted: #6C757D;       /* Texte en sourdine */
            
            /* Bordures */
            --border-color: #3A3A3A;     /* Couleur de bordure par défaut */
            --border-accent: #8B7355;    /* Bordure d'accent */
            
            /* Dégradés */
            --primary-gradient: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            --accent-gradient: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            
            /* Éléments d'interface */
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.2);
            
            /* États */
            --success: #28A745;          /* Succès */
            --info: #17A2B8;             /* Information */
            --warning: #FFC107;          /* Avertissement */
            --danger: #DC3545;           /* Danger/Erreur */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            padding-left: 0;
            transition: all 0.3s ease;
            position: relative;
            overflow-x: hidden;
            line-height: 1.6;
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
            z-index: 0;
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
            z-index: 0;
        }

        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 30px 30px; }
        }

        /* Menu Button */
        #menuBtn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(139, 115, 85, 0.2);
            border: 1px solid rgba(139, 115, 85, 0.3);
            padding: 12px 15px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            color: #8B7355;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(10px);
            width: 45px;
            height: 45px;
        }

        #menuBtn:hover {
            background: rgba(139, 115, 85, 0.3);
            transform: scale(1.05);
            color: #ffffff;
        }

        #menuBtn i {
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        /* Profile Button */
        .profile-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(26, 26, 26, 0.8);
            padding: 8px 15px 8px 8px;
            border-radius: 30px;
            text-decoration: none;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            font-weight: 500;
        }

        .profile-btn:hover {
            background: var(--card-bg);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(164, 190, 123, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--accent-color);
            transition: all 0.3s ease;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar i {
            color: var(--accent-color);
            font-size: 1rem;
        }

        .profile-name {
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
            transition: color 0.3s ease;
        }
        
        .profile-btn:hover .profile-name {
            color: var(--accent-color);
        }
        
        .profile-btn:hover .profile-avatar {
            transform: scale(1.1);
            background: rgba(164, 190, 123, 0.2);
        }

        #menuBtn.shifted {
            left: 300px;
        }

        /* Sidebar */
        #sidebar {
            min-height: 100vh;
            background: linear-gradient(165deg, #2D5A27 0%, #3C2A21 100%);
            padding: 25px 20px;
            width: 280px;
            position: fixed;
            left: -280px;
            top: 0;
            color: white;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        #sidebar.active {
            left: 0;
        }

        #sidebar h4 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #sidebar h4 i {
            color: #A4BE7B;
        }

        #sidebar nav {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 120px);
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 5px;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .nav-link.logout-link {
            margin-top: auto;
            color: #FFB4B4;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
        }

        .nav-link.logout-link:hover {
            background: rgba(255, 0, 0, 0.1);
            color: white;
        }

        .nav-link.logout-link i {
            color: #FFB4B4;
        }

        .nav-link.logout-link:hover i {
            color: white;
        }

        /* Main Content */
        #mainContent {
            margin-left: 0;
            padding: 20px;
            padding-top: 90px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        #mainContent.shifted {
            margin-left: 280px;
        }

        @media (max-width: 768px) {
            #mainContent {
                padding-top: 80px;
            }

            .welcome-banner {
                padding: 1.5rem;
                margin-top: 15px;
            }

            .profile-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1rem;
            }

            .profile-section .profile-info h1 {
                font-size: 1.5rem;
            }

            .profile-section .profile-info p {
                font-size: 1rem;
            }
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, rgba(139, 115, 85, 0.1), rgba(26, 26, 26, 0.1));
            padding: 30px 0;
            margin: 0 -15px 25px -15px;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
            animation: progressBar 2s ease-in-out forwards;
        }

        @keyframes progressBar {
            from { width: 0; }
            to { width: 100%; }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            background: rgba(26, 26, 26, 0.5);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .profile-section:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-color);
        }

        .profile-section .profile-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(26, 26, 26, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .profile-section .profile-image::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            padding: 3px;
            background: var(--accent-gradient);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.7;
            transition: all 0.4s ease;
            z-index: -1;
        }

        .profile-section:hover .profile-image::before {
            opacity: 1;
            animation: rotate 8s linear infinite;
        }

        @keyframes rotate {
            to { transform: rotate(360deg); }
        }

        .profile-section .profile-image i {
            transition: transform 0.3s ease;
        }

        .profile-section:hover .profile-image i {
            transform: scale(1.1);
        }

        .profile-section .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(1);
        }

        .profile-section:hover .profile-image img {
            transform: scale(1.05);
        }
        /* Cartes de statistiques */
        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-color);
            transition: width 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card:hover::before {
            width: 6px;
        }
        
        .stats-card.total { --card-accent: #A4BE7B; }
        .stats-card.interior { --card-accent: #8B7355; }
        .stats-card.exterior { --card-accent: #D4A76A; }
        
        .stats-card .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: var(--card-accent);
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card .stats-content {
            flex: 1;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            display: block;
            margin-bottom: 5px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }

        .stats-label {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            margin-top: 5px;
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 20px;
            background: rgba(164, 190, 123, 0.1);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid var(--accent-color);
        }
        
        .dashboard-card:hover .feature-icon {
            transform: rotate(10deg) scale(1.1);
            background: rgba(164, 190, 123, 0.2);
            box-shadow: 0 8px 25px rgba(164, 190, 123, 0.2);
        }

        .note-card {
            display: flex;
            align-items: center;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }
        
        .note-card:hover {
            transform: translateX(10px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-color);
            background: var(--card-hover);
        }

        .note-image {
            width: 90px;
            height: 90px;
            border-radius: 10px;
            overflow: hidden;
            margin-right: 25px;
            flex-shrink: 0;
            background: rgba(164, 190, 123, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-color);
            font-size: 1.8rem;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .note-card:hover .note-image {
            transform: scale(1.05);
            border-color: var(--accent-color);
            background: rgba(164, 190, 123, 0.2);
        }

        .note-content h5 {
            margin: 0 0 8px 0;
            color: var(--accent-color);
            font-size: 1.2rem;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .note-card:hover .note-content h5 {
            color: var(--text-primary);
        }

        .note-content p {
            margin: 0 0 8px 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .note-content small {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .note-content small::before {
            content: '\f017';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-right: 5px;
        }
        
        .section-title {
            display: inline-flex;
            align-items: center;
            background: var(--card-bg);
            padding: 8px 20px 8px 16px;
            position: relative;
            z-index: 1;
            color: var(--accent-color);
            font-size: 1.3rem;
            font-weight: 700;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            border-left: 3px solid var(--accent-color);
            margin-bottom: 15px;
        }
        
        .section-line {
            height: 2px;
            background: linear-gradient(90deg, var(--accent-color), transparent);
            margin: -15px 0 25px;
            width: 100%;
            position: relative;
            z-index: 0;
            opacity: 0.3;
        }
        
        .section-title i {
            font-size: 1.3em;
            margin-right: 12px;
            transition: all 0.3s ease;
            color: var(--accent-color);
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(164, 190, 123, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
            z-index: -1;
        }
        
        .section-title:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .section-title:hover::before {
            transform: translateX(0);
        }
        
        .section-title:hover i {
            transform: scale(1.2) rotate(8deg);
            color: var(--accent-hover);
        }

        .section-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-color), transparent);
            z-index: 0;
        }

        /* Actions Rapides */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .quick-action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .quick-action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .quick-action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-color);
            background: var(--card-hover);
        }
        
        .quick-action-card:hover::before {
            transform: scaleX(1);
        }

        .quick-action-icon {
            width: 80px;
            height: 80px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 32px;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .quick-action-card:hover .quick-action-icon {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
        }

        .quick-action-card h4 {
            font-size: 1.3rem;
            margin: 0 0 12px 0;
            color: var(--text-primary);
            font-weight: 600;
            transition: color 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .quick-action-card:hover h4 {
            color: var(--accent-color);
        }

        .quick-action-card p {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.6;
            max-width: 90%;
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }
        
        .quick-action-card:hover p {
            color: var(--text-primary);
        }

        .add-plant { 
            background: linear-gradient(135deg, #A4BE7B, #8B7355);
            box-shadow: 0 8px 25px rgba(164, 190, 123, 0.3);
        }
        
        .view-plants { 
            background: linear-gradient(135deg, #8B7355, #6B5A45);
            box-shadow: 0 8px 25px rgba(139, 115, 85, 0.3);
        }
        
        .add-note { 
            background: linear-gradient(135deg, #D4A76A, #8B7355);
            box-shadow: 0 8px 25px rgba(212, 167, 106, 0.3);
        }

        /* Animation au survol des cartes */
        @keyframes cardHover {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }

        .quick-action-card:hover {
            animation: cardHover 0.8s ease-in-out;
        }

        .stats-details {
            margin-top: 20px;
        }

        .stats-details .stats-label {
            color: var(--dark-color);
            font-size: 0.9rem;
            font-weight: 500;
            display: block;
            margin-bottom: 5px;
        }

        .bg-success {
            background: linear-gradient(135deg, #2D5A27, #A4BE7B) !important;
        }

        .bg-primary {
            background: linear-gradient(135deg, #3C2A21, #8B7355) !important;
        }

        .notes-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .notes-list::-webkit-scrollbar {
            width: 5px;
        }

        .notes-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }

        .notes-list::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="urban-grid"></div>
    <div class="urban-accent"></div>

    <!-- Menu Button -->
    <button id="menuBtn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Profile Button -->
    <a href="profil.php" class="profile-btn">
        <div class="profile-avatar">
            <?php if (!empty($user['photo_profil'])): ?>
                <img src="<?php echo UPLOAD_DIR_PROFILES . htmlspecialchars($user['photo_profil']); ?>" alt="Photo de profil">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        <span class="profile-name"><?php echo htmlspecialchars($user['nom']); ?></span>
    </a>

    <!-- Sidebar -->
    <div id="sidebar">
        <h4>
            <i class="fas fa-seedling"></i>
            PlantCare
        </h4>

        <nav>
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                Tableau de bord
            </a>
            <a href="listeplante.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'listeplante.php' ? 'active' : ''; ?>">
                <i class="fas fa-leaf"></i>
                Mes plantes
            </a>
            <a href="ajouter_plante.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'ajouter_plante.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i>
                Ajouter une plante
            </a>
            <a href="profil.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                Mon profil
            </a>
            <a href="logout.php" class="nav-link logout-link">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </nav>
    </div>

    <div id="mainContent">
        <div class="welcome-banner">
            <div class="welcome-content">
                <div class="profile-section">
                    <div class="profile-image <?php echo empty($user['photo_profil']) ? 'no-image' : ''; ?>">
                        <?php if (!empty($user['photo_profil'])): ?>
                            <img src="<?php echo UPLOAD_DIR_PROFILES . htmlspecialchars($user['photo_profil']); ?>" alt="Photo de profil">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h1>Bienvenue, <?php echo htmlspecialchars($user['nom']); ?></h1>
                        <p><?php echo htmlspecialchars($user['niveau_connaissance']); ?> en jardinage</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid py-4">
            <div class="row g-4">
                <!-- Statistiques -->
                <div class="col-md-4">
                    <div class="stats-card total">
                        <div class="stats-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <div class="stats-content">
                            <span class="stats-number"><?php echo $total_plantes; ?></span>
                            <span class="stats-label">Plantes au total</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card interior">
                        <div class="stats-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="stats-content">
                            <span class="stats-number"><?php echo $plantes_interieur; ?></span>
                            <span class="stats-label">Plantes d'intérieur</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card exterior">
                        <div class="stats-icon">
                            <i class="fas fa-sun"></i>
                        </div>
                        <div class="stats-content">
                            <span class="stats-number"><?php echo $plantes_exterieur; ?></span>
                            <span class="stats-label">Plantes d'extérieur</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Rapides -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Actions Rapides
                        </h2>
                        <div class="section-line"></div>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="ajouter_plante.php" class="quick-action-card">
                            <div class="quick-action-icon add-plant">
                                <i class="fas fa-seedling"></i>
                            </div>
                            <h4>Ajouter une plante</h4>
                            <p>Enregistrez une nouvelle plante dans votre collection</p>
                        </a>
                        <a href="listeplante.php" class="quick-action-card">
                            <div class="quick-action-icon view-plants">
                                <i class="fas fa-spa"></i>
                            </div>
                            <h4>Mes plantes</h4>
                            <p>Consultez et gérez toutes vos plantes</p>
                        </a>
                        <a href="note_suivie.php" class="quick-action-card">
                            <div class="quick-action-icon add-note">
                                <i class="fas fa-clipboard"></i>
                            </div>
                            <h4>Suivi de croissance</h4>
                            <p>Ajoutez des notes sur l'évolution de vos plantes</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dernières Notes -->
            <div class="row mt-4">
                <div class="col-12">
                    <h2 class="section-title">
                        <i class="fas fa-book"></i>
                        Dernières Notes
                    </h2>
                    <?php if (!empty($dernieres_notes)): ?>
                        <?php foreach ($dernieres_notes as $note): ?>
                            <a href="note_suivie.php?id=<?php echo $note['id_note']; ?>" class="note-card">
                                <div class="note-image">
                                    <?php if (!empty($note['photo_plante'])): ?>
                                        <img src="<?php echo UPLOAD_DIR_PLANTS . $note['photo_plante']; ?>" alt="<?php echo htmlspecialchars($note['nom_plante']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-leaf"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="note-content">
                                    <h5><?php echo htmlspecialchars($note['nom_plante']); ?></h5>
                                    <p><?php echo htmlspecialchars(substr($note['contenu'], 0, 100)) . '...'; ?></p>
                                    <small><?php echo date('d/m/Y', strtotime($note['date_ajout'])); ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune note pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton flottant pour le conseil du jour -->
    <button class="floating-tip-btn" onclick="openTipModal()" title="Conseil du jour">
        <i class="fas fa-lightbulb"></i>
    </button>

    <!-- Modal Conseil du Jour -->
    <div id="tipModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTipModal()">&times;</span>
            <h3><i class="fas fa-lightbulb"></i>Conseil du Jour</h3>
            <div class="tip-content">
                <p id="tipContent">
                    <?php
                    $tips = [
                        "Arrosez vos plantes tôt le matin pour éviter l'évaporation rapide.",
                        "Utilisez de l'eau à température ambiante pour ne pas choquer vos plantes.",
                        "Vérifiez régulièrement le dessous des feuilles pour détecter les parasites.",
                        "Tournez vos plantes d'un quart de tour chaque semaine pour une croissance uniforme.",
                        "Un bon drainage est essentiel pour éviter les racines pourries.",
                        "Les feuilles poussiéreuses ne peuvent pas bien photosynthétiser. Nettoyez-les !",
                        "La plupart des plantes aiment l'humidité. Vaporisez-les régulièrement.",
                        "Évitez de placer vos plantes près des courants d'air froid.",
                        "Un pot trop grand peut noyer les racines. Choisissez la bonne taille !",
                        "Les plantes aussi ont besoin de repos. Respectez leur période de dormance.",
                        "Observez la couleur des feuilles pour détecter les carences en nutriments.",
                        "Un terreau adapté est la clé d'une plante en bonne santé.",
                        "N'hésitez pas à tailler les feuilles mortes pour stimuler la croissance.",
                        "Les plantes d'intérieur apprécient un taux d'humidité entre 40% et 60%.",
                        "Prenez des photos régulières pour suivre l'évolution de vos plantes.",
                        "Évitez de déplacer trop souvent vos plantes, elles aiment la stabilité.",
                        "En été, augmentez la fréquence d'arrosage selon les besoins.",
                        "En hiver, réduisez l'arrosage car la croissance est plus lente.",
                        "Utilisez un pulvérisateur pour nettoyer les feuilles et augmenter l'humidité.",
                        "Les pots doivent avoir des trous de drainage pour éviter l'excès d'eau.",
                        "Certaines plantes peuvent être multipliées par bouturage dans l'eau.",
                        "Un apport en engrais est important pendant la période de croissance.",
                        "Groupez vos plantes pour créer un microclimat favorable.",
                        "La lumière indirecte convient à la plupart des plantes d'intérieur.",
                        "Surveillez l'apparition de taches sur les feuilles, signe de maladie.",
                        "Retirez la poussière des feuilles avec un chiffon humide.",
                        "Les cache-pots décoratifs ne doivent pas retenir l'eau.",
                        "Adaptez l'arrosage selon la saison et l'exposition.",
                        "Un compost maison enrichit naturellement la terre.",
                        "La rotation des plantes assure une croissance équilibrée."
                    ];
                    
                    $dayOfYear = date('z');
                    $tipIndex = $dayOfYear % count($tips);
                    echo $tips[$tipIndex];
                    ?>
                </p>
                <button onclick="closeTipModal()">
                    <i class="fas fa-check"></i>
                    J'ai compris !
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu functionality
            const menuBtn = document.getElementById('menuBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const tipButton = document.querySelector('.tip-button');

            function toggleMenu(show) {
                if (show === undefined) {
                    sidebar.classList.toggle('active');
                    menuBtn.classList.toggle('shifted');
                    mainContent.classList.toggle('shifted');
                    
                    if (tipButton) {
                        tipButton.style.opacity = sidebar.classList.contains('active') ? '0' : '1';
                        tipButton.style.visibility = sidebar.classList.contains('active') ? 'hidden' : 'visible';
                    }
                } else {
                    sidebar.classList[show ? 'add' : 'remove']('active');
                    menuBtn.classList[show ? 'add' : 'remove']('shifted');
                    mainContent.classList[show ? 'add' : 'remove']('shifted');
                    
                    if (tipButton) {
                        tipButton.style.opacity = show ? '0' : '1';
                        tipButton.style.visibility = show ? 'hidden' : 'visible';
                    }
                }
            }

            if (menuBtn && sidebar && mainContent) {
                // Menu button click
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleMenu();
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(e) {
                    const isClickInside = sidebar.contains(e.target) || menuBtn.contains(e.target);
                    if (!isClickInside && sidebar.classList.contains('active')) {
                        toggleMenu(false);
            }
                });

                // Handle escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        toggleMenu(false);
            }
        });

                // Handle window resize
                let resizeTimer;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                            toggleMenu(false);
                        }
                    }, 250);
                });
            }

            // Tip modal functionality
            const modal = document.getElementById('tipModal');
            const closeButton = document.querySelector('.close');

            if (tipButton && modal && closeButton) {
                tipButton.addEventListener('click', function() {
                    modal.style.display = 'block';
            });

                closeButton.addEventListener('click', function() {
                    modal.style.display = 'none';
                });

                window.addEventListener('click', function(e) {
                    if (e.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            }
        });

        // Gestion de l'affichage du conseil du jour
        function openTipModal() {
            const modal = document.getElementById('tipModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closeTipModal() {
            const modal = document.getElementById('tipModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Fermer la modale en cliquant en dehors ou avec la touche Échap
        window.onclick = function(event) {
            const modal = document.getElementById('tipModal');
            if (event.target === modal) {
                closeTipModal();
            }
        };

        document.addEventListener('keydown', function(event) {
            const modal = document.getElementById('tipModal');
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeTipModal();
            }
        });

        // Afficher automatiquement le conseil du jour au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier si l'utilisateur a déjà vu le conseil aujourd'hui
            const lastTipDate = localStorage.getItem('lastTipDate');
            const today = new Date().toDateString();
            
            if (lastTipDate !== today) {
                // Attendre un peu avant d'afficher le conseil
                setTimeout(openTipModal, 2000);
                // Mettre à jour la date du dernier conseil vu
                localStorage.setItem('lastTipDate', today);
            }
        });
    </script>
</body>
</html>
