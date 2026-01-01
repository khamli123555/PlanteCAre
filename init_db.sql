-- Création de la base de données
CREATE DATABASE IF NOT EXISTS plantecare;
USE plantecare;

-- Table Utilisateur
CREATE TABLE Utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    genre ENUM('Homme', 'Femme', 'Autre') NOT NULL,
    telephone VARCHAR(20),
    photo_profil VARCHAR(255),
    niveau_connaissance ENUM('Débutant', 'Intermédiaire', 'Expert') DEFAULT 'Débutant',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    last_login DATETIME
);

-- Table Plante
CREATE TABLE Plante (
    id_plante INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    type ENUM('intérieur', 'extérieur') NOT NULL,
    besoins_eau VARCHAR(100),
    besoins_lumière VARCHAR(100),
    date_plantation DATE,
    remarques TEXT,
    photo VARCHAR(255),
    id_utilisateur INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE
);

-- Table Note
CREATE TABLE Note (
    id_note INT AUTO_INCREMENT PRIMARY KEY,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hauteur DECIMAL(10,2),
    nb_feuilles INT,
    etat_sante ENUM('Excellent', 'Bon', 'Moyen', 'Mauvais') DEFAULT 'Bon',
    couleur_feuilles ENUM('Vert foncé', 'Vert clair', 'Jaune', 'Marron') DEFAULT 'Vert foncé',
    commentaire TEXT,
    photo_note VARCHAR(255),
    id_plante INT,
    id_user INT,
    FOREIGN KEY (id_plante) REFERENCES Plante(id_plante) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE
);

-- Index pour optimisation des recherches
CREATE INDEX idx_nom_plante ON Plante(nom);
CREATE INDEX idx_type_plante ON Plante(type);
CREATE INDEX idx_date_plantation ON Plante(date_plantation);

