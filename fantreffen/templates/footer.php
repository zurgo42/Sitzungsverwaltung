    </main>

    <!-- Footer -->
    <?php
    $basePath = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : '';
    ?>
    <footer>
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small">
                <span>&copy; <?= date('Y') ?> AIDA Fantreffen</span>
                <span>
                    <a href="<?= $basePath ?>datenschutz.php">Datenschutz</a> ·
                    <a href="<?= $basePath ?>impressum.php">Impressum</a>
                </span>
                <a href="mailto:info@aidafantreffen.de">✉ Kontakt</a>
            </div>
        </div>
    </footer>

    <!-- Minimales JS für Navigation & Akkordeon -->
    <script>
    function toggleNav() {
        document.getElementById('mainNav').classList.toggle('show');
    }

    document.querySelectorAll('.accordion-button').forEach(btn => {
        btn.addEventListener('click', () => {
            // Button ist in <h2>, Content ist das nächste Element nach <h2>
            const header = btn.closest('.accordion-header');
            const content = header.nextElementSibling;
            if (content) {
                content.classList.toggle('show');
                btn.classList.toggle('collapsed');
            }
        });
    });
    </script>
</body>
</html>
