<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}

// Traitement des filtres AJAX
if (isset($_GET['ajax'])) {
    $userId = $_SESSION['user']['id'];
    $conditions = ['p.id_utilisateur = :user_id'];
    $params = [':user_id' => $userId];
    
    // Filtre par type
    if (!empty($_GET['type'])) {
        $conditions[] = 'p.type = :type';
        $params[':type'] = $_GET['type'];
    }
    
    // Filtre par catégorie
    if (!empty($_GET['categorie'])) {
        $conditions[] = 'p.id_categorie = :categorie';
        $params[':categorie'] = $_GET['categorie'];
    }
    
    // Filtre par besoins en eau
    if (!empty($_GET['besoins_eau'])) {
        $conditions[] = 'p.besoins_eau = :besoins_eau';
        $params[':besoins_eau'] = $_GET['besoins_eau'];
    }
    
    // Filtre par besoins en lumière
    if (!empty($_GET['besoins_lumiere'])) {
        $conditions[] = 'p.besoins_lumière = :besoins_lumiere';
        $params[':besoins_lumiere'] = $_GET['besoins_lumiere'];
    }
    
    try {
        // Construction de la requête SQL
        $sql = "SELECT p.*, c.nom_categorie 
                FROM Plante p 
                LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
                WHERE " . implode(' AND ', $conditions);
        
        // Ajout du tri
        if (!empty($_GET['sort'])) {
            $sortField = $_GET['sort'];
            $allowedFields = ['nom', 'type', 'date_ajout'];
            if (in_array($sortField, $allowedFields)) {
                $sql .= " ORDER BY p.$sortField";
                if (isset($_GET['order']) && $_GET['order'] === 'desc') {
                    $sql .= " DESC";
                }
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        exit;
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Récupération des options de filtres
try {
    $userId = $_SESSION['user']['id'];
    
    // Récupérer les types uniques
    $typeStmt = $conn->prepare("SELECT DISTINCT type FROM Plante WHERE id_utilisateur = ? AND type IS NOT NULL");
    $typeStmt->execute([$userId]);
    $types = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupérer les catégories
    $catStmt = $conn->prepare("SELECT id_categorie, nom_categorie FROM Categorie");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les besoins en eau uniques
    $eauStmt = $conn->prepare("SELECT DISTINCT besoins_eau FROM Plante WHERE id_utilisateur = ? AND besoins_eau IS NOT NULL");
    $eauStmt->execute([$userId]);
    $besoinsEau = $eauStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Récupérer les besoins en lumière uniques
    $lumiereStmt = $conn->prepare("SELECT DISTINCT besoins_lumière FROM Plante WHERE id_utilisateur = ? AND besoins_lumière IS NOT NULL");
    $lumiereStmt->execute([$userId]);
    $besoinsLumiere = $lumiereStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Filtrer les plantes - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f9f4;
            font-family: 'Quicksand', sans-serif;
            padding: 20px;
        }
        .filter-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .results-container {
            margin-top: 20px;
        }
        .plant-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .plant-card:hover {
            transform: translateY(-5px);
        }
        .plant-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .plant-info {
            padding: 20px;
        }
        .badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        .sort-icon {
            cursor: pointer;
            margin-left: 5px;
        }
        .sort-icon.active {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Filtrer les plantes</h2>
            <div>
                <a href="search.php" class="btn btn-outline-success me-2">
                    <i class="fas fa-search"></i> Recherche simple
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>
        </div>

        <div class="filter-container">
            <form id="filterForm" class="row g-3">
                <!-- Type de plante -->
                <div class="col-md-3">
                    <label class="form-label">Type de plante</label>
                    <select class="form-select" name="type">
                        <option value="">Tous les types</option>
                        <?php foreach($types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Catégorie -->
                <div class="col-md-3">
                    <label class="form-label">Catégorie</label>
                    <select class="form-select" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom_categorie']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Besoins en eau -->
                <div class="col-md-3">
                    <label class="form-label">Besoins en eau</label>
                    <select class="form-select" name="besoins_eau">
                        <option value="">Tous les besoins</option>
                        <?php foreach($besoinsEau as $besoin): ?>
                            <option value="<?= htmlspecialchars($besoin) ?>"><?= htmlspecialchars($besoin) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Besoins en lumière -->
                <div class="col-md-3">
                    <label class="form-label">Besoins en lumière</label>
                    <select class="form-select" name="besoins_lumiere">
                        <option value="">Tous les besoins</option>
                        <?php foreach($besoinsLumiere as $besoin): ?>
                            <option value="<?= htmlspecialchars($besoin) ?>"><?= htmlspecialchars($besoin) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tri -->
                <div class="col-12">
                    <div class="d-flex align-items-center">
                        <label class="me-3">Trier par:</label>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary sort-btn" data-field="nom">
                                Nom <i class="fas fa-sort sort-icon"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary sort-btn" data-field="type">
                                Type <i class="fas fa-sort sort-icon"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary sort-btn" data-field="date_ajout">
                                Date d'ajout <i class="fas fa-sort sort-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="loading">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>

        <div id="filterResults" class="results-container row">
            <!-- Les résultats seront affichés ici -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour créer une carte de plante (même que dans search.php)
        function createPlantCard(plant) {
            return `
                <div class="col-md-4">
                    <div class="plant-card">
                        ${plant.photo 
                            ? `<img src="${plant.photo}" class="plant-image" alt="${plant.nom}">` 
                            : `<div class="plant-image bg-light d-flex align-items-center justify-content-center">
                                   <span class="text-muted">Pas de photo</span>
                               </div>`
                        }
                        <div class="plant-info">
                            <h5 class="mb-3">${plant.nom}</h5>
                            <div class="mb-2">
                                ${plant.type ? `<span class="badge bg-primary">${plant.type}</span>` : ''}
                                ${plant.nom_categorie ? `<span class="badge bg-secondary">${plant.nom_categorie}</span>` : ''}
                            </div>
                            <div class="small text-muted">
                                ${plant.besoins_eau ? `<p><i class="fas fa-tint"></i> ${plant.besoins_eau}</p>` : ''}
                                ${plant.besoins_lumière ? `<p><i class="fas fa-sun"></i> ${plant.besoins_lumière}</p>` : ''}
                            </div>
                            <div class="mt-3">
                                <a href="modifier.php?id=${plant.id_plante}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="note_suivie.php?id=${plant.id_plante}" class="btn btn-info btn-sm">
                                    <i class="fas fa-sticky-note"></i> Notes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Variables pour le tri
        let currentSort = {
            field: null,
            order: 'asc'
        };

        // Fonction pour mettre à jour les icônes de tri
        function updateSortIcons() {
            document.querySelectorAll('.sort-btn').forEach(btn => {
                const icon = btn.querySelector('.sort-icon');
                const field = btn.dataset.field;
                
                if (field === currentSort.field) {
                    icon.className = `fas fa-sort-${currentSort.order === 'asc' ? 'up' : 'down'} sort-icon active`;
                } else {
                    icon.className = 'fas fa-sort sort-icon';
                }
            });
        }

        // Fonction pour appliquer les filtres
        async function applyFilters() {
            const loading = document.querySelector('.loading');
            const resultsContainer = document.getElementById('filterResults');
            const formData = new FormData(document.getElementById('filterForm'));
            
            let queryParams = new URLSearchParams();
            queryParams.append('ajax', '1');
            
            for (let [key, value] of formData.entries()) {
                if (value) queryParams.append(key, value);
            }
            
            // Ajouter les paramètres de tri
            if (currentSort.field) {
                queryParams.append('sort', currentSort.field);
                queryParams.append('order', currentSort.order);
            }
            
            loading.style.display = 'block';
            resultsContainer.innerHTML = '';
            
            try {
                const response = await fetch(`filter.php?${queryParams.toString()}`);
                const data = await response.json();
                
                loading.style.display = 'none';
                
                if (data.success) {
                    if (data.results.length > 0) {
                        resultsContainer.innerHTML = data.results.map(plant => createPlantCard(plant)).join('');
                    } else {
                        resultsContainer.innerHTML = `
                            <div class="col-12">
                                <div class="text-center p-5">
                                    <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                                    <h4>Aucune plante ne correspond à ces critères</h4>
                                    <p class="text-muted">Essayez de modifier vos filtres</p>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                loading.style.display = 'none';
                resultsContainer.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            Une erreur est survenue : ${error.message}
                        </div>
                    </div>
                `;
            }
        }

        // Écouteurs d'événements
        document.getElementById('filterForm').querySelectorAll('select').forEach(select => {
            select.addEventListener('change', applyFilters);
        });

        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const field = btn.dataset.field;
                
                if (currentSort.field === field) {
                    currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.field = field;
                    currentSort.order = 'asc';
                }
                
                updateSortIcons();
                applyFilters();
            });
        });

        // Appliquer les filtres au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();
        });
    </script>
</body>
</html> 