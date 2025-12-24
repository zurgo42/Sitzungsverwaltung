    </main>

    <!-- Footer -->
    <?php
    $basePath = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : '';
    ?>
    <footer class="bg-primary text-light py-2 mt-auto">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4 text-center text-md-start small">
                    &copy; <?= date('Y') ?> AIDA Fantreffen
                </div>
                <div class="col-md-4 text-center small">
                    <a href="<?= $basePath ?>datenschutz.php" class="text-light text-decoration-none me-3">Datenschutz</a>
                    <a href="<?= $basePath ?>impressum.php" class="text-light text-decoration-none">Impressum</a>
                </div>
                <div class="col-md-4 text-center text-md-end small">
                    <a href="mailto:info@aidafantreffen.de" class="text-light text-decoration-none">
                        <i class="bi bi-envelope"></i> Kontakt
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
