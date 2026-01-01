// js/add_note.js - Validation pour l'ajout de notes
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('noteForm');
    const commentaireField = document.getElementById('commentaire');
    const photoField = document.getElementById('photo');
    const photoPreview = document.getElementById('photo-preview');

    // Validation en temps réel du commentaire
    commentaireField.addEventListener('blur', function() {
        validateCommentaire();
    });

    // Prévisualisation de la photo
    photoField.addEventListener('change', function() {
        previewPhoto(this.files[0]);
    });

    // Validation du formulaire avant soumission
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });

    function validateCommentaire() {
        const commentaire = commentaireField.value.trim();
        const errorElement = document.getElementById('commentaire-error');
        
        if (commentaire.length === 0) {
            errorElement.textContent = 'Le commentaire est obligatoire.';
            return false;
        } else if (commentaire.length < 10) {
            errorElement.textContent = 'Le commentaire doit contenir au moins 10 caractères.';
            return false;
        } else {
            errorElement.textContent = '';
            return true;
        }
    }

    function previewPhoto(file) {
        if (file) {
            // Vérifier le type de fichier
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Seuls les fichiers JPG, JPEG et PNG sont autorisés.');
                photoField.value = '';
                photoPreview.innerHTML = '';
                return;
            }

            // Vérifier la taille (2 Mo max)
            if (file.size > 2 * 1024 * 1024) {
                alert('La taille du fichier ne doit pas dépasser 2 Mo.');
                photoField.value = '';
                photoPreview.innerHTML = '';
                return;
            }

            // Créer la prévisualisation
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `
                    <div class="photo-preview">
                        <img src="${e.target.result}" alt="Prévisualisation" style="max-width: 200px; max-height: 200px;">
                        <p>Fichier sélectionné : ${file.name}</p>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            photoPreview.innerHTML = '';
        }
    }

    function validateForm() {
        return validateCommentaire();
    }
});

// js/edit_note.js - Validation pour la modification de notes
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editNoteForm');
    const commentaireField = document.getElementById('commentaire');
    const photoField = document.getElementById('photo');
    const photoPreview = document.getElementById('photo-preview');
    const removePhotoCheckbox = document.getElementById('remove_photo');

    // Validation en temps réel du commentaire
    commentaireField.addEventListener('blur', function() {
        validateCommentaire();
    });

    // Prévisualisation de la nouvelle photo
    photoField.addEventListener('change', function() {
        previewPhoto(this.files[0]);
    });

    // Gestion de la suppression de photo
    if (removePhotoCheckbox) {
        removePhotoCheckbox.addEventListener('change', function() {
            if (this.checked) {
                photoField.disabled = true;
                photoPreview.innerHTML = '<p style="color: orange;">La photo actuelle sera supprimée.</p>';
            } else {
                photoField.disabled = false;
                photoPreview.innerHTML = '';
            }
        });
    }

    // Validation du formulaire avant soumission
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });

    function validateCommentaire() {
        const commentaire = commentaireField.value.trim();
        const errorElement = document.getElementById('commentaire-error');
        
        if (commentaire.length === 0) {
            errorElement.textContent = 'Le commentaire est obligatoire.';
            return false;
        } else if (commentaire.length < 10) {
            errorElement.textContent = 'Le commentaire doit contenir au moins 10 caractères.';
            return false;
        } else {
            errorElement.textContent = '';
            return true;
        }
    }

    function previewPhoto(file) {
        if (file && !removePhotoCheckbox.checked) {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Seuls les fichiers JPG, JPEG et PNG sont autorisés.');
                photoField.value = '';
                photoPreview.innerHTML = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('La taille du fichier ne doit pas dépasser 2 Mo.');
                photoField.value = '';
                photoPreview.innerHTML = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `
                    <div class="photo-preview">
                        <img src="${e.target.result}" alt="Nouvelle photo" style="max-width: 200px; max-height: 200px;">
                        <p>Nouvelle photo : ${file.name}</p>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
    }

    function validateForm() {
        return validateCommentaire();
    }
});

// js/history.js - Fonctionnalités pour l'historique
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal pour les photos
    window.openModal = function(photoName) {
        const modal = document.getElementById('photoModal');
        const modalImage = document.getElementById('modalImage');
        
        modalImage.src = 'uploads/notes/' + photoName;
        modal.style.display = 'block';
        
        // Fermer le modal en cliquant à l'extérieur
        modal.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        };
    };

    window.closeModal = function() {
        const modal = document.getElementById('photoModal');
        modal.style.display = 'none';
    };

    // Fermer le modal avec la touche Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    // Animation des notes au scroll
    const noteItems = document.querySelectorAll('.note-item');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    noteItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(item);
    });
});

// Fonction pour charger la météo
function loadWeather() {
    const weatherLoading = document.getElementById('weather-loading');
    const weatherContent = document.getElementById('weather-content');
    
    if (!weatherLoading || !weatherContent) return;

    // Utiliser la géolocalisation du navigateur
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            // Appel à l'API météo via notre proxy PHP
            fetch(`get_weather.php?lat=${lat}&lon=${lon}`)
                .then(response => response.json())
                .then(data => {
                    if (data.main && data.weather) {
                        const temp = Math.round(data.main.temp);
                        const description = data.weather[0].description;
                        
                        weatherContent.querySelector('.temperature').textContent = `${temp}°C`;
                        weatherContent.querySelector('.description').textContent = description;
                        
                        weatherLoading.style.display = 'none';
                        weatherContent.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement de la météo:', error);
                    weatherLoading.textContent = 'Impossible de charger la météo';
                });
        }, error => {
            console.error('Erreur de géolocalisation:', error);
            weatherLoading.textContent = 'Localisation non disponible';
        });
    } else {
        weatherLoading.textContent = 'Géolocalisation non supportée';
    }
}

// Charger la météo au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    loadWeather();
});

// Gestionnaire pour le bouton "Compris !" dans la modale de conseil
document.addEventListener('DOMContentLoaded', function() {
    const comprisBtn = document.querySelector('.modal-footer .btn-success');
    if (comprisBtn) {
        comprisBtn.addEventListener('click', function() {
            const modal = document.getElementById('tipModal');
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
    }
});

// Gestion du menu latéral
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const navigationButtons = document.querySelector('.navigation-buttons');
    
    if (menuBtn && sidebar && mainContent && navigationButtons) {
        // Restaurer l'état du menu au chargement
        const sidebarActive = localStorage.getItem('sidebarActive') === 'true';
        if (sidebarActive) {
            sidebar.classList.add('active');
            mainContent.classList.add('sidebar-active');
            navigationButtons.classList.add('sidebar-active');
        }

        // Gérer le clic sur le bouton du menu
        menuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-active');
            navigationButtons.classList.toggle('sidebar-active');
            
            // Sauvegarder l'état du menu
            localStorage.setItem('sidebarActive', sidebar.classList.contains('active'));
        });

        // Fermer le menu au clic en dehors
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !menuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('sidebar-active');
                navigationButtons.classList.remove('sidebar-active');
                localStorage.setItem('sidebarActive', 'false');
            }
        });
    }
});