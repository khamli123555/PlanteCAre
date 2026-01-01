<?php
session_start();
require_once 'config.php';

// Vérification de base de la session
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

// Récupérer les informations de l'utilisateur
$user_photo = '';
$user_name = '';
if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id_utilisateur'];
    $stmt = $conn->prepare("SELECT photo_profil, nom FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_photo = $user['photo_profil'] ?? '';
    $user_name = $user['nom'] ?? 'Utilisateur';
}
?>

<style>
    /* Profile Button */
    .profile-button-container {
        position: fixed !important;
        top: 20px !important;
        right: 20px !important;
        z-index: 1000 !important;
    }

    .profile-btn {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        background: rgba(26, 26, 26, 0.8) !important;
        padding: 6px 12px 6px 6px !important;
        border-radius: 30px !important;
        text-decoration: none !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color) !important;
        box-shadow: var(--shadow-sm) !important;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
        backdrop-filter: blur(10px) !important;
        font-weight: 500 !important;
    }

    .profile-btn:hover {
        background: var(--card-bg) !important;
        transform: translateY(-2px) !important;
        box-shadow: var(--shadow-md) !important;
        color: var(--accent-color) !important;
        border-color: var(--accent-color) !important;
    }

    .profile-avatar {
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        background: rgba(164, 190, 123, 0.1) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 2px solid var(--accent-color) !important;
        transition: all 0.3s ease !important;
    }

    .profile-avatar img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }

    .profile-avatar i {
        color: var(--accent-color) !important;
        font-size: 1rem !important;
    }

    .profile-name {
        font-weight: 500 !important;
        font-size: 0.9rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 150px !important;
        transition: color 0.3s ease !important;
    }
    
    .profile-btn:hover .profile-name {
        color: var(--accent-color) !important;
    }
    
    .profile-btn:hover .profile-avatar {
        transform: scale(1.1) !important;
        background: rgba(164, 190, 123, 0.2) !important;
    }

    @media (max-width: 768px) {
        .profile-btn {
            padding: 6px 12px !important;
        }

        .profile-avatar {
            width: 30px !important;
            height: 30px !important;
        }

        .profile-name {
            font-size: 0.85rem !important;
            max-width: 100px !important;
        }
    }

    /* Menu Button */
    .menu-btn {
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1000;
        background: linear-gradient(135deg, #A4BE7B, #8B7355);
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 12px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        color: #ffffff;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        width: 45px;
        height: 45px;
        backdrop-filter: blur(8px);
    }

    .menu-btn:hover {
        background: linear-gradient(135deg, #8B7355, #A4BE7B);
        transform: translateY(-2px);
        color: #ffffff;
    }

    .menu-btn i {
        font-size: 1.3rem;
        transition: transform 0.3s ease;
    }

    .menu-btn:hover i {
        transform: scale(1.1) rotate(90deg);
    }

    .menu-btn.shifted {
        left: 300px;
    }

    /* Sidebar */
    .sidebar {
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

    .sidebar.active {
        left: 0;
    }

    .sidebar h4 {
        color: white;
        margin-bottom: 20px;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar h4 i {
        color: #A4BE7B;
    }

    .sidebar nav {
        margin-top: 30px;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px); /* Hauteur totale moins l'espace pour le titre */
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

    /* Main Content Shift */
    .main-content {
        margin-left: 0;
        transition: all 0.3s ease;
        min-height: 100vh;
        padding: 20px;
        padding-top: 80px;
    }

    .main-content.sidebar-active {
        margin-left: 280px;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 250px;
            left: -250px;
        }

        .main-content.sidebar-active {
            margin-left: 0;
            transform: translateX(250px);
        }
    }
</style>

<!-- Profile Button -->
<div class="profile-button-container">
<a href="profil.php" class="profile-btn">
    <div class="profile-avatar">
        <?php if ($user_photo && file_exists("uploads/profiles/" . $user_photo)): ?>
            <img src="uploads/profiles/<?= htmlspecialchars($user_photo) ?>" alt="Photo de profil">
        <?php else: ?>
            <i class="fas fa-user"></i>
        <?php endif; ?>
    </div>
    <span class="profile-name"><?= htmlspecialchars($user_name) ?></span>
</a>
</div>

<div class="sidebar">
    <h4>
        <i class="fas fa-seedling"></i>
        PlantCare
    </h4>

    <nav>
        <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            Tableau de bord
        </a>
        <a href="listeplante.php" class="nav-link <?= $current_page === 'listeplante.php' ? 'active' : '' ?>">
            <i class="fas fa-leaf"></i>
            Mes plantes
        </a>
        <a href="ajouter_plante.php" class="nav-link <?= $current_page === 'ajouter_plante.php' ? 'active' : '' ?>">
            <i class="fas fa-plus"></i>
            Ajouter une plante
        </a>
        <a href="profil.php" class="nav-link <?= $current_page === 'profil.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            Mon profil
        </a>
        <a href="logout.php" class="nav-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            Déconnexion
        </a>
    </nav>
</div>