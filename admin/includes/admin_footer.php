            </main>
        </div>
    </div>

    <!-- BOOTSTRAP BUNDLE JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SCRIPTS DE INTERAÇÃO DO PAINEL -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const btnToggle = document.getElementById('btn-toggle-sidebar');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (btnToggle && sidebar && overlay) {
            btnToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>