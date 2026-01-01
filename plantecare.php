<?php
session_start();
require_once 'config.php';

// Rediriger vers le tableau de bord si déjà connecté
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantCare - Votre Assistant Botanique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2D5A27;
            --secondary-color: #A4BE7B;
            --accent-color: #3C2A21;
            --light-color: #F5F5DC;
            --success-color: #198754;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 700;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            color: var(--secondary-color);
        }

        .nav-link {
            color: #333 !important;
            font-weight: 500;
            padding: 0.8rem 1.5rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(164, 190, 123, 0.1);
        }

        /* Hero Section - Style Urbain Moderne */
        .hero-section {
            min-height: 100vh;
            background: #1a1a1a;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .urban-grid {
            position: absolute;
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
        }

        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 30px 30px; }
        }

        .urban-accent {
            position: absolute;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(164, 190, 123, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(45, 90, 39, 0.4) 0%, transparent 50%);
            filter: blur(60px);
            opacity: 0.5;
        }

        .urban-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .urban-shape {
            position: absolute;
            border: 2px solid rgba(164, 190, 123, 0.3);
            border-radius: 10px;
        }

        .shape1 {
            width: 200px;
            height: 200px;
            top: 10%;
            left: 5%;
            animation: rotateShape 20s linear infinite;
        }

        .shape2 {
            width: 150px;
            height: 150px;
            bottom: 15%;
            right: 8%;
            animation: rotateShape 15s linear infinite reverse;
        }

        .shape3 {
            width: 100px;
            height: 100px;
            top: 50%;
            right: 20%;
            animation: rotateShape 10s linear infinite;
        }

        @keyframes rotateShape {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            padding: 2rem;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text {
            text-align: left;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, #ffffff 30%, var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
        }

        .hero-title span {
            display: block;
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 1rem;
            color: var(--secondary-color);
            -webkit-text-fill-color: var(--secondary-color);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 3rem;
        }

        .hero-image {
            position: relative;
            height: 500px;
            perspective: 1000px;
        }

        .image-container {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            animation: floatImage 6s ease-in-out infinite;
        }

        @keyframes floatImage {
            0%, 100% { transform: translateY(0) rotateY(0); }
            50% { transform: translateY(-20px) rotateY(3deg); }
        }

        .image-frame {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px solid rgba(164, 190, 123, 0.3);
            border-radius: 20px;
            overflow: hidden;
            background: rgba(26, 26, 26, 0.5);
            backdrop-filter: blur(10px);
        }

        .image-frame::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 0%,
                rgba(164, 190, 123, 0.1) 50%,
                transparent 100%
            );
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .urban-button {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .urban-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .urban-button:hover::before {
            transform: translateX(100%);
        }

        .urban-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(45, 90, 39, 0.3);
        }

        .urban-button-outline {
            background: transparent;
            border: 2px solid var(--secondary-color);
        }

        .urban-button-outline:hover {
            background: rgba(164, 190, 123, 0.1);
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
        }

        @media (max-width: 992px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }

            .hero-text {
                text-align: center;
            }

            .hero-title {
                font-size: 3rem;
            }

            .hero-image {
                height: 400px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .urban-shape {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .hero-image {
                height: 300px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .urban-button {
                width: 100%;
                justify-content: center;
            }
        }

        /* Features Section - Style Urbain Moderne */
        .features-section {
            background: #1a1a1a;
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .features-section .urban-grid {
            opacity: 0.3;
        }

        .features-section .section-title {
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 4rem;
            background: linear-gradient(45deg, #ffffff 30%, var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .features-section .section-title::after {
            display: none;
        }

        .feature-card {
            background: rgba(26, 26, 26, 0.5);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(164, 190, 123, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 0%,
                rgba(164, 190, 123, 0.1) 50%,
                transparent 100%
            );
            animation: shimmer 3s linear infinite;
            pointer-events: none;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--secondary-color);
            box-shadow: 0 15px 40px rgba(45, 90, 39, 0.2);
        }

        .feature-icon {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(10deg);
            color: white;
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: white;
        }

        .feature-text {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 0;
        }



        /* Buttons */
        .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            box-shadow: 0 4px 15px rgba(45, 90, 39, 0.2);
        }

        .btn-success:hover {
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 90, 39, 0.3);
        }

        .btn-outline-success {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            background: transparent;
        }

        .btn-outline-success:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Animation des éléments au scroll */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Footer - Style Urbain Moderne */
        .footer-section {
            background: #1a1a1a;
            color: white;
            padding: 60px 0 30px;
            position: relative;
            overflow: hidden;
        }

        .footer-section .urban-grid {
            opacity: 0.1;
            transform: perspective(1000px) rotateX(60deg) scale(1.5);
        }

        .footer-content {
            position: relative;
            z-index: 2;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 2rem;
        }

        .footer-logo i {
            color: var(--secondary-color);
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .footer-column h4 {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .footer-column h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--secondary-color);
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column ul li {
            margin-bottom: 0.8rem;
        }

        .footer-column ul li a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-column ul li a:hover {
            color: var(--secondary-color);
            transform: translateX(5px);
        }

        .footer-column ul li a i {
            font-size: 0.8rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            margin-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .footer-bottom p {
            margin: 0;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            justify-content: center;
        }

        .social-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .social-links a:hover {
            color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-3px);
        }

        @media (max-width: 768px) {
            .footer-links {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-column h4::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .footer-column ul li a {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-seedling"></i>
                PlantCare
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section - Style Urbain Moderne -->
    <section class="hero-section">
        <div class="urban-grid"></div>
        <div class="urban-accent"></div>
        <div class="urban-shapes">
            <div class="urban-shape shape1"></div>
            <div class="urban-shape shape2"></div>
            <div class="urban-shape shape3"></div>
        </div>
        
        <div class="hero-content">
            <div class="hero-container">
                <div class="hero-text">
                    <h1 class="hero-title">
                        <span>Bienvenue sur PlantCare</span>
                        Créez votre jardin urbain intelligent
                    </h1>
                    <p class="hero-subtitle">
                        Transformez votre espace en un havre de verdure connecté. 
                        PlantCare vous accompagne avec des solutions innovantes pour 
                        cultiver et entretenir vos plantes en milieu urbain.
                    </p>
                    <div class="hero-buttons">
                        <a href="register.php" class="urban-button">
                            <i class="fas fa-user-plus"></i>
                            Créer votre compte
                        </a>
                        <a href="login.php" class="urban-button urban-button-outline">
                            <i class="fas fa-user"></i>
                            Se connecter
                        </a>
                    </div>
                </div>
                <div class="hero-image">
                    <div class="image-container">
                        <div class="image-frame"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section - Style Urbain Moderne -->
    <section class="features-section">
        <div class="urban-grid"></div>
        <div class="urban-accent"></div>
        <div class="container">
            <h2 class="section-title">Pourquoi choisir PlantCare ?</h2>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <i class="fas fa-leaf feature-icon"></i>
                        <h3 class="feature-title">Suivi personnalisé</h3>
                        <p class="feature-text">
                            Suivez la croissance de vos plantes avec des rappels d'arrosage 
                            et des conseils adaptés à chaque espèce.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <i class="fas fa-book feature-icon"></i>
                        <h3 class="feature-title">Journal de bord</h3>
                        <p class="feature-text">
                            Gardez une trace de l'évolution de vos plantes avec des notes 
                            et des photos pour documenter leur croissance.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <i class="fas fa-lightbulb feature-icon"></i>
                        <h3 class="feature-title">Conseils experts</h3>
                        <p class="feature-text">
                            Recevez des conseils quotidiens et des astuces pour maintenir 
                            vos plantes en parfaite santé.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer Section -->
    <footer class="footer-section">
        <div class="urban-grid"></div>
        <div class="urban-accent"></div>
        <div class="container footer-content">
            <div class="footer-logo">
                <i class="fas fa-seedling"></i>
                PlantCare
            </div>
            
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Navigation</h4>
                    <ul>
                        <li><a href="login.php"><i class="fas fa-chevron-right"></i>Connexion</a></li>
                        <li><a href="register.php"><i class="fas fa-chevron-right"></i>Inscription</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Fonctionnalités</h4>
                    <ul>
                        <li><a href="#"><i class="fas fa-leaf"></i>Suivi des plantes</a></li>
                        <li><a href="#"><i class="fas fa-book"></i>Journal de bord</a></li>
                        <li><a href="#"><i class="fas fa-lightbulb"></i>Conseils experts</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> PlantCare. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation au scroll
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });

            fadeElements.forEach(element => {
                observer.observe(element);
            });
        });
    </script>
</body>
</html> 