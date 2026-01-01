<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}

// Traitement de la recherche AJAX
if (isset($_GET['ajax']) && isset($_GET['query'])) {
    $search = trim($_GET['query']);
    $userId = $_SESSION['user']['id'];
    
    try {
        $sql = "SELECT p.*, c.nom_categorie 
                FROM Plante p 
                LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
                WHERE p.id_utilisateur = :user_id 
                AND (
                    p.nom LIKE :search 
                    OR p.type LIKE :search 
                    OR p.besoins_eau LIKE :search 
                    OR p.besoins_lumière LIKE :search 
                    OR p.remarques LIKE :search
                    OR c.nom_categorie LIKE :search
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':search' => "%$search%"
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Retourner les résultats en JSON
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche de plantes - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f9f4;
            font-family: 'Quicksand', sans-serif;
            padding: 20px;
        }
        .search-container {
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
        .search-input {
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.1rem;
        }
        .search-input:focus {
            border-color: #218838;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
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
        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Recherche de plantes</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>

        <div class="search-container">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-success"></i>
                        </span>
                        <input type="text" 
                               id="searchInput" 
                               class="form-control search-input border-start-0" 
                               placeholder="Rechercher une plante par nom, type, besoins..."
                               autocomplete="off">
                    </div>
                </div>
            </div>
        </div>

        <div class="loading">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>

        <div id="searchResults" class="results-container row">
            <!-- Les résultats seront affichés ici -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour créer une carte de plante
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

        // Fonction pour afficher "Aucun résultat"
        function showNoResults() {
            document.getElementById('searchResults').innerHTML = `
                <div class="col-12">
                    <div class="no-results">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Aucune plante trouvée</h4>
                        <p class="text-muted">Essayez avec d'autres termes de recherche</p>
                    </div>
                </div>
            `;
        }

        // Fonction de debounce
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Fonction de recherche
        const performSearch = debounce(async (query) => {
            const loading = document.querySelector('.loading');
            const resultsContainer = document.getElementById('searchResults');

            if (query.length < 2) {
                resultsContainer.innerHTML = '';
                return;
            }

            loading.style.display = 'block';
            resultsContainer.innerHTML = '';

            try {
                const response = await fetch(`search.php?ajax=1&query=${encodeURIComponent(query)}`);
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success) {
                    if (data.results.length > 0) {
                        resultsContainer.innerHTML = data.results.map(plant => createPlantCard(plant)).join('');
                    } else {
                        showNoResults();
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
        }, 300);

        // Écouteur d'événement pour l'input de recherche
        document.getElementById('searchInput').addEventListener('input', (e) => {
            performSearch(e.target.value.trim());
        });
    </script>
</body>
</html> 