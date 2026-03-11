<?php
if (!isset($freshyCsrfToken) || !is_string($freshyCsrfToken) || $freshyCsrfToken === '') {
    require_once __DIR__ . '/csrf.php';
    $freshyCsrfToken = csrfToken();
}
?>
<!--Section footers-->
        <footer class="footer-section">
            <div class="footer-logo-container">
                <img src="images/logo_freshy.webp" alt="Freshy Industries Logo" class="footer-logo">
            </div>
            <div class="footer-sitemap">
                <div class="sitemap-column">
                    <h3>Plan du site</h3>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="#">Nos marques</a></li>
                        <li><a href="#">Nos produits</a></li>
                        <li><a href="epicerie_terroir.php">Epicerie du terroir</a></li>
                        <li><a href="actualite.php">Actualités</a></li>
                    </ul>
                </div>
                <div class="sitemap-column">
                    <h3 style="visibility: hidden;">Sous-catégories</h3> <!-- Titre caché pour l'alignement -->
                    <ul>
                        <li><a href="freshy_palm_page.php">Freshy Palm</a></li>
                        <li><a href="freshy_fruit_boost.php">Freshy Fruit boosté</a></li>
                        <li><a href="#">Les dérivés de la production</a></li>
                        <li><a href="actualite.php">Conseils et guide</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-buy-section">
                <h3>Où acheter ?</h3>
                <p>Vous souhaitez vous procurer les produits de Freshy industries pour votre consommation</p>
                <a href="point_vente.php" class="btn-points-vente">Nos points de vente</a>
            </div>
        </footer>
        <div id="toast-root" class="toast-stack" aria-live="polite" aria-atomic="false"></div>

        <script>
window.addEventListener('scroll', function () {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) { // Ajoutez l'ombre après 50px de défilement
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            document.addEventListener('DOMContentLoaded', () => {
                /* --- Slider des catégories (section product-categories) --- */
                const categorySection = document.querySelector('.product-categories');
                const categoryCards = categorySection ? Array.from(categorySection.querySelectorAll('.category-card')) : [];
                const arrowLeft = categorySection ? categorySection.querySelector('.category-slider-arrow--left') : null;
                const arrowRight = categorySection ? categorySection.querySelector('.category-slider-arrow--right') : null;

                if (categorySection && categoryCards.length && arrowLeft && arrowRight) {
                    let currentCategoryIndex = 0;

                    const updateCategorySlide = () => {
                        categoryCards.forEach((card, index) => {
                            card.classList.toggle('active', index === currentCategoryIndex);
                        });

                        // Flèche gauche cachée sur la première carte
                        if (currentCategoryIndex === 0) {
                            arrowLeft.classList.add('disabled');
                        } else {
                            arrowLeft.classList.remove('disabled');
                        }

                        // Flèche droite cachée sur la dernière carte
                        if (currentCategoryIndex === categoryCards.length - 1) {
                            arrowRight.classList.add('disabled');
                        } else {
                            arrowRight.classList.remove('disabled');
                        }
                    };

                    arrowRight.addEventListener('click', () => {
                        if (currentCategoryIndex < categoryCards.length - 1) {
                            currentCategoryIndex++;
                        }
                        updateCategorySlide();
                    });

                    arrowLeft.addEventListener('click', () => {
                        if (currentCategoryIndex > 0) {
                            currentCategoryIndex--;
                        }
                        updateCategorySlide();
                    });

                    // Assure que l'état initial est correct
                    updateCategorySlide();
                }

                const articlesSection = document.querySelector('.articles-section');
                const articleDetailPage = document.querySelector('.article-detail-page');
                const relatedArticlesCarousel = document.querySelector('.related-articles-carousel');
                
                // Vérification robuste avant manipulation
                if (articleDetailPage && articlesSection) {
                    const backLink = articleDetailPage.querySelector('.back-link');

                    document.querySelectorAll('.articles-section .btn-read-article').forEach(button => {
                        button.addEventListener('click', (e) => {
                            e.preventDefault();
                            if (articlesSection) articlesSection.style.display = 'none';
                            articleDetailPage.style.display = 'block';
                            if (relatedArticlesCarousel) relatedArticlesCarousel.style.display = 'block';
                        });
                    });

                    if (backLink) {
                        backLink.addEventListener('click', (e) => {
                            e.preventDefault();
                            articleDetailPage.style.display = 'none';
                            if (relatedArticlesCarousel) relatedArticlesCarousel.style.display = 'none';
                            if (articlesSection) articlesSection.style.display = 'block';
                        });
                    }
                }

                // Carousel functionality
                const carouselContainer = document.querySelector('.carousel-container');
                const prevArrow = document.querySelector('.prev-arrow');
                const nextArrow = document.querySelector('.next-arrow');

                if (carouselContainer && prevArrow && nextArrow) {
                    nextArrow.addEventListener('click', () => {
                        carouselContainer.scrollBy({ left: carouselContainer.offsetWidth, behavior: 'smooth' });
                    });

                    prevArrow.addEventListener('click', () => {
                        carouselContainer.scrollBy({ left: -carouselContainer.offsetWidth, behavior: 'smooth' });
                    });
                }
const recapToggle = document.querySelector('.recap-toggle');
                const recapSection = document.getElementById('orderRecapSection');
                if (recapToggle && recapSection) {
                    const applyRecapState = (shouldExpand) => {
                        recapToggle.setAttribute('aria-expanded', String(shouldExpand));
                        recapSection.classList.toggle('is-collapsed', !shouldExpand);

                        if (shouldExpand) {
                            recapSection.style.maxHeight = '0px';
                            requestAnimationFrame(() => {
                                recapSection.style.maxHeight = `${recapSection.scrollHeight}px`;
                                recapSection.style.opacity = '1';
                            });
                        } else {
                            recapSection.style.maxHeight = `${recapSection.scrollHeight}px`;
                            requestAnimationFrame(() => {
                                recapSection.style.maxHeight = '0px';
                                recapSection.style.opacity = '0';
                            });
                        }
                    };

                    const isExpanded = recapToggle.getAttribute('aria-expanded') === 'true';
                    applyRecapState(isExpanded);

                    recapToggle.addEventListener('click', () => {
                        const currentlyExpanded = recapToggle.getAttribute('aria-expanded') === 'true';
                        applyRecapState(!currentlyExpanded);
                    });
                }

                const filtersToggleBtn = document.querySelector('.filters-toggle-btn');
                const filtersOptionsList = document.querySelector('.filtre-options-list');
                const toggleIcon = filtersToggleBtn?.querySelector('.toggle-icon');
                if (filtersToggleBtn && filtersOptionsList && toggleIcon) {
                    const compactQuery = window.matchMedia('(max-width: 899px)');

                    const syncFiltersAccordion = (isCompact) => {
                        if (isCompact) {
                            filtersOptionsList.classList.add('collapsed');
                            filtersToggleBtn.setAttribute('aria-expanded', 'false');
                            toggleIcon.textContent = '+';
                        } else {
                            filtersOptionsList.classList.remove('collapsed');
                            filtersToggleBtn.setAttribute('aria-expanded', 'true');
                            toggleIcon.textContent = '−';
                        }
                    };

                    syncFiltersAccordion(compactQuery.matches);

                    compactQuery.addEventListener('change', (event) => {
                        syncFiltersAccordion(event.matches);
                    });

                    filtersToggleBtn.addEventListener('click', () => {
                        if (!compactQuery.matches) return;
                        const isHidden = filtersOptionsList.classList.toggle('collapsed');
                        filtersToggleBtn.setAttribute('aria-expanded', String(!isHidden));
                        toggleIcon.textContent = isHidden ? '+' : '−';
                    });
                }
const modalTrierPar = document.getElementById('modalTrierPar');
                if (modalTrierPar) {
                    const closeTrierParButton = document.getElementById('closeTrierParButton');

                    const closeModal = () => {
                        modalTrierPar.style.display = 'none';
                    };

                    window.openTrierParModal = function () {
                        modalTrierPar.style.display = 'flex';
                    };

                    closeTrierParButton?.addEventListener('click', closeModal);
                    modalTrierPar.addEventListener('click', (event) => {
                        if (event.target === modalTrierPar) {
                            closeModal();
                        }
                    });
                }
            });

            // Menu burger functionality
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const closeBtn = document.getElementById('closeBtn');
            const navLinks = document.querySelector('.nav-links');
            const sidebarDropdowns = document.querySelectorAll('.sidebar-dropdown .dropdown-toggle');

            // Open sidebar
            function openSidebar() {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                menuToggle.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Changer l'icône en croix
                //const icon = menuToggle.querySelector('i');
                //if (icon) {
                //    icon.classList.remove('fa-bars');
                //    icon.classList.add('fa-times');
                //}
            }

            // Close sidebar
            function closeSidebar() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                menuToggle.classList.remove('active');
                document.body.style.overflow = '';

                // Remettre l'icône en barres
                const icon = menuToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }

            // Toggle sidebar on burger click
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    if (sidebar.classList.contains('active')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }

            // Close sidebar when clicking close button
            if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
            }

            // Close sidebar when clicking overlay
            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });

            // Sidebar dropdown functionality
            sidebarDropdowns.forEach(dropdown => {
                dropdown.addEventListener('click', (e) => {
                    e.preventDefault();
                    const parent = dropdown.parentElement;
                    const dropdownContent = parent ? parent.querySelector('.sidebar-dropdown-content') : null;

                    // Vérification robuste de l'existence des éléments
                    if (parent && dropdownContent) {
                        parent.classList.toggle('active');

                        if (parent.classList.contains('active')) {
                            dropdownContent.style.maxHeight = dropdownContent.scrollHeight + 'px';
                        } else {
                            dropdownContent.style.maxHeight = '0';
                        }
                    }
                });
            });

            // Set active link based on current page
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            
            // Vérification robuste avant manipulation
            if (sidebarLinks && sidebarLinks.length > 0) {
                sidebarLinks.forEach(link => {
                    if (link && link.getAttribute('href') === currentPage) {
                        link.classList.add('active');
                    }
                });
            }

            // Toggle nav-links for old menu (compatibility)
            if (navLinks && menuToggle) {
                menuToggle.addEventListener('click', () => {
                    navLinks.classList.toggle('active');
                });
            }
        </script>
        <script>
            window.FRESHY_CSRF_TOKEN = <?php echo json_encode($freshyCsrfToken, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script src="<?php echo htmlspecialchars(function_exists('freshyAsset') ? freshyAsset('js/toast.js') : 'js/toast.js'); ?>"></script>
        <script src="<?php echo htmlspecialchars(function_exists('freshyAsset') ? freshyAsset('js/whatsapp-reserve.js') : 'js/whatsapp-reserve.js'); ?>"></script>
        <script src="<?php echo htmlspecialchars(function_exists('freshyAsset') ? freshyAsset('js/navbar-search.js') : 'js/navbar-search.js'); ?>"></script>
        <script src="<?php echo htmlspecialchars(function_exists('freshyAsset') ? freshyAsset('js/newsletter.js') : 'js/newsletter.js'); ?>"></script>
        <script src="<?php echo htmlspecialchars(function_exists('freshyAsset') ? freshyAsset('js/freshy_core.js') : 'js/freshy_core.js'); ?>"></script>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>





















