<?php
// Définition des constantes de configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Oussama123@@');
define('DB_NAME', 'plantecare');
define('SQL_FILE', 'init_db.sql');
define('LOG_FILE', 'database_init.log');

// Fonction de logging
function logMessage($message, $type = 'INFO') {
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$type] $message\n";
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    echo $logMessage;
}

// Fonction pour exécuter les requêtes SQL
function executeSQLFile($mysqli, $filename) {
    try {
        if (!file_exists($filename)) {
            throw new Exception("Le fichier SQL $filename n'existe pas.");
        }

        $sql = file_get_contents($filename);
        if ($sql === false) {
            throw new Exception("Impossible de lire le fichier SQL $filename.");
        }

        // Séparation des requêtes SQL
        $queries = array_filter(
            array_map(
                'trim',
                explode(';', $sql)
            )
        );

        // Exécution de chaque requête
        foreach ($queries as $query) {
            if (empty($query)) continue;
            
            if ($mysqli->query($query) === false) {
                throw new Exception("Erreur lors de l'exécution de la requête : " . $mysqli->error);
            }
            logMessage("Requête exécutée avec succès : " . substr($query, 0, 50) . "...");
        }

        return true;
    } catch (Exception $e) {
        logMessage($e->getMessage(), 'ERROR');
        return false;
    }
}

// Fonction pour vérifier et créer les dossiers nécessaires
function createRequiredDirectories() {
    $directories = [
        'uploads',
        'uploads/profiles',
        'uploads/plants',
        'uploads/notes'
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                logMessage("Dossier '$dir' créé avec succès.");
            } else {
                logMessage("Impossible de créer le dossier '$dir'.", 'ERROR');
            }
        }
    }
}

// Fonction principale d'initialisation
function initializeDatabase() {
    try {
        // Création de la connexion
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($mysqli->connect_error) {
            throw new Exception("Erreur de connexion à MySQL: " . $mysqli->connect_error);
        }
        logMessage("Connexion à MySQL établie.");

        // Création de la base de données si elle n'existe pas
        if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
            throw new Exception("Erreur lors de la création de la base de données: " . $mysqli->error);
        }
        logMessage("Base de données " . DB_NAME . " créée ou déjà existante.");

        // Sélection de la base de données
        if (!$mysqli->select_db(DB_NAME)) {
            throw new Exception("Impossible de sélectionner la base de données: " . $mysqli->error);
        }

        // Exécution du fichier SQL
        if (executeSQLFile($mysqli, SQL_FILE)) {
            logMessage("Initialisation de la base de données terminée avec succès.");
        } else {
            throw new Exception("Erreur lors de l'initialisation de la base de données.");
        }

        // Création des dossiers nécessaires
        createRequiredDirectories();

        $mysqli->close();
        return true;

    } catch (Exception $e) {
        logMessage($e->getMessage(), 'ERROR');
        if (isset($mysqli)) {
            $mysqli->close();
        }
        return false;
    }
}

// Interface en ligne de commande
if (php_sapi_name() === 'cli') {
    echo "Démarrage de l'initialisation de la base de données...\n";
    if (initializeDatabase()) {
        echo "Initialisation terminée avec succès.\n";
        exit(0);
    } else {
        echo "Erreur lors de l'initialisation.\n";
        exit(1);
    }
}

// Interface web
if (isset($_POST['action']) && $_POST['action'] === 'initialize') {
    header('Content-Type: application/json');
    if (initializeDatabase()) {
        echo json_encode(['success' => true, 'message' => 'Base de données initialisée avec succès.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'initialisation de la base de données.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Initialisation de la Base de Données - PlantCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f9f4;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-4">Initialisation de la Base de Données</h2>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Attention : Cette opération va réinitialiser la base de données. Toutes les données existantes seront perdues.
                </div>

                <form id="initForm" class="mb-4">
                    <button type="submit" class="btn btn-primary">
                        Initialiser la Base de Données
                    </button>
                </form>

                <div id="result" class="d-none alert"></div>

                <h4 class="mt-4">Journal d'initialisation :</h4>
                <pre id="log"></pre>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('initForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const resultDiv = document.getElementById('result');
            const logPre = document.getElementById('log');
            const submitButton = e.target.querySelector('button');

            try {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Initialisation...';
                
                const response = await fetch('init_db.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=initialize'
                });

                const data = await response.json();
                
                resultDiv.className = `alert alert-${data.success ? 'success' : 'danger'}`;
                resultDiv.textContent = data.message;
                resultDiv.classList.remove('d-none');

                // Actualiser le journal
                const logResponse = await fetch('database_init.log');
                const logText = await logResponse.text();
                logPre.textContent = logText;

            } catch (error) {
                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Erreur lors de la communication avec le serveur.';
                resultDiv.classList.remove('d-none');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Initialiser la Base de Données';
            }
        });

        // Charger le journal au chargement de la page
        fetch('database_init.log')
            .then(response => response.text())
            .then(text => {
                document.getElementById('log').textContent = text;
            })
            .catch(error => console.error('Erreur lors du chargement du journal:', error));
    </script>
</body>
</html> 