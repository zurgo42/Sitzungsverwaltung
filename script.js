/**
 * script.js - Client-seitiges JavaScript für Sitzungsverwaltung
 * Hamburger-Menü mit LocalStorage
 */

document.addEventListener('DOMContentLoaded', function() {
    // Hamburger-Menü Funktionalität
    const hamburger = document.getElementById('hamburger-menu');
    const navigation = document.querySelector('.navigation');

    if (hamburger && navigation) {
        // Prüfe ob es der erste Besuch ist
        const hasVisited = localStorage.getItem('menuVisited');

        // Beim ersten Besuch Menü anzeigen, danach verstecken
        if (hasVisited === 'true') {
            navigation.classList.add('mobile-hidden');
        } else {
            // Erste Besuch - Menü anzeigen und Flag setzen
            localStorage.setItem('menuVisited', 'true');
        }

        // Hamburger-Klick Handler
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            navigation.classList.toggle('mobile-hidden');

            // Hamburger Animation
            this.classList.toggle('active');
        });

        // Klick außerhalb schließt das Menü (nur auf Mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!navigation.contains(e.target) && !hamburger.contains(e.target)) {
                    navigation.classList.add('mobile-hidden');
                    hamburger.classList.remove('active');
                }
            }
        });

        // Bei Klick auf einen Link Menü schließen
        const navLinks = navigation.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    navigation.classList.add('mobile-hidden');
                    hamburger.classList.remove('active');
                }
            });
        });
    }
});
