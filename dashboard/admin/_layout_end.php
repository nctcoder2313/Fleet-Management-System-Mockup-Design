            </div>
        </main>
    </div>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');
        const desktopViewport = window.matchMedia('(min-width: 1024px)');

        function setSidebarOpen(open) {
            sidebar.classList.toggle('-translate-x-full', !open);
            sidebarBackdrop.classList.toggle('hidden', !open);
            sidebarToggle.setAttribute('aria-expanded', String(open));
        }

        sidebarToggle.setAttribute('aria-expanded', String(desktopViewport.matches));
        sidebarToggle.addEventListener('click', () => {
            if (desktopViewport.matches) {
                const collapsed = sidebar.classList.toggle('lg:hidden');
                sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
                return;
            }
            setSidebarOpen(sidebar.classList.contains('-translate-x-full'));
        });
        sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (desktopViewport.matches) {
                sidebar.classList.add('lg:hidden');
                sidebarToggle.setAttribute('aria-expanded', 'false');
            } else {
                setSidebarOpen(false);
            }
        });
    </script>
</body>
</html>
