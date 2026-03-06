<?php
$page_title = '?picerie du terroir';
$additional_css = [];
include 'includes/header.php';
?>

    <section class="hero-section">
      <div
        class="marquee"
        aria-label="Livraison gratuite ? partir de 10000 Fcfa"
      >
        <div class="marquee-track">
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite ? partir de <strong>10000 Fcfa</strong></span
          >
        </div>
      </div>
      <div class="hero-visual">
        <div class="hero-overlay">
          <h1>Epicerie du terroir</h1>
        </div>
      </div>
    </section>

    <section class="filters-toolbar">
        <div class="filtre-trier-container">
        <div class="section filtre">
            <span class="texte">Filtre</span>
        </div>
    
        <div class="section trier-par" onclick="openTrierParModal()">
            <span class="texte">Trier par :</span>
        </div>
    </section>

    <section class="products-grid-section" aria-label="S?lection ?picerie du terroir" >
            <div class="products-grid" data-catalog-source="products-api" data-catalog-context="epicerie-main">
                <p class="panier-empty" data-catalog-loading="true">Chargement des produits...</p>
            
                
            </div>
    </section>


    <section class="page-container-flex hidden">
      <?php include 'includes/header.php'; ?>

    
    
      <section class="section-content form-section" id="formulaireLivraison">
          <header class="page-header">
              <div class="logo-brand">
                  <img src="images/logo_freshy.webp" style="height: 74px;" alt="Freshy Industries Logo" class="logo-image">
              </div>
              <a class="panier-link" href="panier.php">
                  Panier <i class="fas fa-shopping-bag"></i>
              </a>
          </header>

          <div class="form-section-group">
              <h2 class="form-title">Contact</h2>
              <input type="tel" id="checkoutPhone" name="checkout_phone" placeholder="Votre numero de téléphone (+229 01********)" class="form-input primary-input" autocomplete="tel">
          </div>

          <div class="form-section-group">
              <h2 class="form-title">Email (Facultatif)</h2>
              <input type="email" id="checkoutEmail" name="checkout_email" placeholder="Email" class="form-input" autocomplete="email">
              <label class="checkbox-label">
                  <input type="checkbox" class="form-checkbox">
                  J'accepte recevoir de nouvelles offres
              </label>
          </div>

          <div class="form-section-group">
              <h2 class="form-title">Livraison <span class="delivery-charge">(La livraison est à votre charge)</span></h2>
              
              <div class="dropdown-wrapper">
                  <select class="form-select" id="country">
                      <option value="" disabled selected>Pays</option>
                  </select>
                  <span class="dropdown-arrow"></span>
              </div>
              
              <div class="dropdown-wrapper">
                  <select class="form-select" id="city">
                      <option value="" disabled selected>Ville</option>
                  </select>
                  <span class="dropdown-arrow"></span>
              </div>

              <input type="text" id="checkoutNeighborhood" name="checkout_neighborhood" placeholder="Quartier" class="form-input" autocomplete="address-level3">
              <input type="text" id="checkoutRecipient" name="checkout_recipient" placeholder="Nom prénoms réceptionnaire" class="form-input" autocomplete="name">
          </div>
          
          <footer class="form-footer">
              <button class="btn-payer-form">Procéder au paiement</button>
          </footer>
      </section>

      

      <section class="section-content hidden container-fluid px-2 px-md-3" id="checkoutFinalRecap" aria-live="polite">
          <header class="page-header d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
              <div class="logo-brand">
                  <img src="images/logo_freshy.webp" style="height: 74px;" alt="Freshy Industries Logo" class="logo-image">
              </div>
              <a class="panier-link ms-sm-auto" href="epicerie_terroire.php">Continuer mes achats</a>
          </header>

          <div class="recap-commande-container mx-auto w-100">
              <h2 class="form-title">Commande confirmée</h2>
              <p id="finalRecapLead">Votre commande a bien été enregistrée.</p>

              <div class="recap-details" style="width: 100%;">
                  <div class="recap-row">
                      <span class="label">Numéro de commande</span>
                      <span class="value" id="finalOrderNumber">-</span>
                  </div>
                  <div class="recap-row">
                      <span class="label">Statut</span>
                      <span class="value" id="finalOrderStatus">pending</span>
                  </div>
                  <div class="recap-row">
                      <span class="label">Livraison</span>
                      <span class="value" id="finalOrderDelivery">-</span>
                  </div>
              </div>

              <hr class="recap-separator">
              <div id="finalOrderItems"></div>
              <hr class="recap-separator final-separator">

              <div class="total-final-row">
                  <span class="total-label">Total payé</span>
                  <span class="total-amount" id="finalOrderTotal">0 Fcfa</span>
              </div>
          </div>
      </section>
      <section class="section-content recap-section is-collapsed" id="orderRecapSection">
          <div class="recap-commande-container">
              <div id="recapItems"></div>
              
              <hr class="recap-separator">

              <div class="recap-details" style="width: 412px;">
                  <div class="recap-row">
                      <span class="label">Total produit</span>
                      <span class="value" id="recapTotalProducts">0</span> 
                  </div>
                  <div class="recap-row">
                      <span class="label">Exp?dition</span>
                      <span class="value" id="recapShippingNeighborhood">**********</span> 
                  </div>
              </div>

              <hr class="recap-separator final-separator">

              <div class="total-final-row">
                  <span class="total-label">Total montant</span>
                  <span class="total-amount" id="recapTotalAmount">0 Fcfa</span>
              </div>
          </div>
      </section>
      <button class="recap-toggle" type="button" aria-expanded="false" aria-controls="orderRecapSection">
        <span class="recap-toggle__label">R?sum? de la commande</span>
        <span class="recap-toggle__icon" aria-hidden="true">
          <i class="fas fa-chevron-down"></i>
        </span>
        <span class="recap-toggle__total" id="orderSummaryTotal">0 Fcfa</span>
      </button>
    </section>

    <section class="reset">
        <div class="modal-filtres-overlay hidden" id="filtersModal" aria-hidden="true">
          <div class="modal-filtres-content">
          
            <header class="modal-header">
                <h1 class="header-title">Filtres</h1>
                <button class="close-button" aria-label="Fermer" type="button">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 18L18 6M6 6L18 18" stroke="#172B4D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </header>

            <main class="modal-body">
                <div class="modal-filters-toggle">
                    <h2 class="section-title">Filtres</h2>
                    <button type="button" class="filters-toggle-btn" aria-expanded="false" aria-controls="filtersOptions">
                        <span class="toggle-icon">+</span>
                    </button>
                </div>
                <ul class="filtre-options-list" id="filtersOptions">
                    <li class="option-item">Nouveau</li>
                    <li class="option-item">Cr?mes</li>
                    <li class="option-item">Boissons</li>
                    <li class="option-item">Huiles</li>
                    <li class="option-item">?pices</li>
                    <li class="option-item">L?gumes</li>
                </ul>
            </main>

            <footer class="modal-footer">
                <button class="btn btn-reinitialiser">R?initialiser</button>
                <button class="btn btn-appliquer">Appliquer</button>
            </footer>
          </div>
        </div>
    </section>
 
    <section class="trier-par">
        <div class="modal-overlay" id="modalTrierPar">
            <div class="modal-content">
                
                <header class="modal-header">
                    <h1 class="header-title">Trier par</h1>
                    <button class="close-button" id="closeTrierParButton" aria-label="Fermer">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 18L18 6M6 6L18 18" stroke="#172B4D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </header>

                <main class="modal-body modal-sort-options">
                    <ul class="options-list">
                        <li class="option-item-trier" data-sort="default">Meilleure ventes</li>
                        <li class="option-item-trier" data-sort="alpha_asc">Alphabetique, de A a Z</li>
                        <li class="option-item-trier" data-sort="alpha_desc">Alphabetique, de Z a A</li>
                        <li class="option-item-trier" data-sort="price_asc">Prix : faible a eleve</li>
                        <li class="option-item-trier" data-sort="price_desc">Prix : eleve a faible</li>
                        <li class="option-item-trier" data-sort="date_oldest">Date, de la plus ancienne a la plus recente</li>
                        <li class="option-item-trier" data-sort="date_newest">Date, de la plus recente a la plus ancienne</li>
                    </ul>
                </main>

            </div>
        </div>
    </section>


    <section class="panier-modal-overlay hidden" id="panierModal" aria-hidden="true">
            <div class="panier-modal-content">
                
                <header class="panier-header">
                    <h1 class="header-title">Votre panier</h1>
                    <button class="close-panier-button" aria-label="Fermer le panier" type="button">
                        <svg class="close-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 18L18 6M6 6L18 18" stroke="#172B4D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </header>

                <main class="panier-body" id="modalCartItems">
                    <p class="panier-empty">Votre panier est vide.</p>
                </main>

                <footer class="panier-footer">
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-amount" id="modalCartTotal">0 Fcfa</span>
                    </div>
                    <button class="btn-payer" id="modalCheckoutBtn">Payer</button>
                </footer>
            </div>
    </section>

    <section id="section_creme" class="hidden" style="margin-top: 74px;">
        <a href="epicerie_terroire.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour</a>

      <div style="display: grid; grid-template-columns: 84px 1fr; gap: 24px">
        <div class="thumbnail-list" aria-label="Aper?u des visuels">
          <button type="button" class="active" aria-pressed="true">
            <img
              src="images/concentre_epicerie (2).webp"
              alt="Cr?me concentr?e de noix de palme"
            />
          </button>
          <button type="button" aria-pressed="false">
            <img src="images/motif_concentre_epicerie.webp" alt="Fruits de palme entiers" />
          </button>
          <button type="button" aria-pressed="false">
            <img src="images/motif_concentre_epicerie (2).webp" alt="Pr?sentation en cuisine" />
          </button>
        </div>
      </div>

      <section
        class="section-wrapper"
        aria-label="Cr?me concentr?e de noix de palme"
        >
        <figure class="visual-panel">
          <img
            src="images/concentre_epicerie (2).webp"
            alt="Visuel produit cr?me concentr?e de noix de palme Freshy"
          />
        </figure>

        <!-- Contr?les du carousel mobile pour les visuels cr?me -->
        <div class="creme-carousel-controls" aria-label="Navigation visuels produit">
          <button type="button" class="creme-carousel-arrow creme-carousel-arrow--prev" aria-label="Image pr?c?dente">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
          </button>
          <div class="creme-carousel-dots" role="tablist" aria-label="?tapes du visuel produit">
            <button type="button" class="creme-carousel-dot active" data-index="0" aria-label="Image 1" aria-current="true"></button>
            <button type="button" class="creme-carousel-dot" data-index="1" aria-label="Image 2"></button>
            <button type="button" class="creme-carousel-dot" data-index="2" aria-label="Image 3"></button>
          </div>
          <button type="button" class="creme-carousel-arrow creme-carousel-arrow--next" aria-label="Image suivante">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
          </button>
        </div>

        <div class="product-content">
          <h1>Cr?me ? Cr?me concentr?e de noix de palme</h1>
          <p class="badge">Produit populaire</p>
          <p class="price">1?500 Fcfa</p>
          <p class="description">
            Fait seulement ? base de noix de palme, notre cr?me est naturelle et
            sans additif. V?ritable alli? pour vos mets rapides et sans tapage,
            elle conserve toute la richesse aromatique des noix soigneusement
            s?lectionn?es.
          </p>
          <div
            class="format-selector"
            role="group"
            aria-label="Choix du format"
          >
            <button class="format-button active" type="button">1500 g</button>
            <button class="format-button" type="button">700 g</button>
          </div>
          <div class="quantity-row">
            <input
              class="quantity-field"
              type="number"
              min="1"
              value="1"
              aria-label="Quantit?"
            />
            <button class="cta-button " type="button">Ajouter au panier</button>
          </div>
        </div>
        <!--section 2-->
      </section>
      <figure class="visual-panel">
        <img
          style="width: 37%; position: relative; right: 192px"
          src="images/motif_concentre_epicerie.webp"
          alt="Visuel produit cr?me concentr?e de noix de palme Freshy"
        />
      </figure>
      <figure class="visual-panel">
        <img
          style="width: 37%; position: relative; right: 192px"
          src="images/motif_concentre_epicerie (2).webp"
          alt="Visuel produit cr?me concentr?e de noix de palme Freshy"
        />
      </figure>
      <h2
        class="title-details" >
        Vous aimeriez aussi ces produits
      </h2>
      
      <section class="products-grid-section" aria-label="S?lection ?picerie du terroir">
        <div class="carousel-container products-grid" data-catalog-source="products-api" data-catalog-context="epicerie-related" data-catalog-limit="4">
          <article class="product-card product-card--soldout">
                  <span class="product-card__badge--soldout badge--soldout"
                      >En rupture</span
                  >
                  <a class="product-card__media" data-target="section_creme" href="#section_creme">
                      <img
                      src=""
                      alt="D?cor de fruits de palme"
                      class="product-card__decor"
                      />
                      <img
                      src="images/concentre_epicerie.webp"
                      alt="Cr?me concentr?e de noix de palme"
                      class="product-card__image"
                      />
                  </a>
                  <h3>Cr?me - Cr?me concentr?e de noix de palme</h3>
                  <p><strong>A partir de 750 Fcfa</strong></p>
                  <div
                      class="product-card__overlay"
                      role="group"
                      aria-label="Options d'achat"
                  >
                      <select class="product-card__select" aria-label="Choisir un format">
                      <option value="300g">300g - 1500 Fcfa</option>
                      <option value="500g">500g - 2500 Fcfa</option>
                      <option value="1000g">1kg - 4500 Fcfa</option>
                      </select>
                      <button class="product-card__cta" type="button">
                      Ajouter au panier
                      <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                      </button>
                  </div>
          </article>
          <article class="product-card product-card--new">
                  <span class="product-card__badge--new badge--new">Nouveau</span>
                  <div class="product-card__media">
                      <img
                      src="images/"
                      alt="D?cor de citron_menthe"
                      class="product-card__decor"
                      />
                      <img
                      src="images/citronnade_booste.webp"
                      alt="citronnade ? la menthe"
                      class="product-card__image"
                      />
                  </div>
                  <h3>Citronnade - Z?ro soif fresh <br>vibes</h3>
                  <p><strong>A partir de 200 Fcfa</strong></p>
                  <div
                      class="product-card__overlay"
                      role="group"
                      aria-label="Options d'achat"
                  >
                      <select class="product-card__select" aria-label="Choisir un format">
                      <option value="300g">300g - 200 Fcfa</option>
                      <option value="500g">500g - 2500 Fcfa</option>
                      <option value="1000g">1kg - 4500 Fcfa</option>
                      </select>
                      <button class="product-card__cta" type="button">
                      Ajouter au panier
                      <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                      </button>
                  </div>
          </article>
          <article class="product-card product-card--soldout">
                  <span class="product-card__badge--soldout badge--soldout">En rupture</span>
                  <div class="product-card__media">
                      <img
                      src="images/noix_epicerie.webp"
                      alt="D?cor de fruits de palme"
                      class="product-card__decor"
                      />
                      <img
                      src="images/huile_epicerie.webp"
                      alt="Huile de palme"
                      class="product-card__image"
                      />
                  </div>
                  <h3>Huile de palme ? Z?ro artificiel Pure tradition</h3>
                  <p><strong>A partir de 1500 Fcfa</strong></p>
                  <div
                      class="product-card__overlay"
                      role="group"
                      aria-label="Options d'achat"
                  >
                      <select class="product-card__select" aria-label="Choisir un format">
                      <option value="300g">300g - 1500 Fcfa</option>
                      <option value="500g">500g - 2500 Fcfa</option>
                      <option value="1000g">1kg - 4500 Fcfa</option>
                      </select>
                      <button class="product-card__cta" type="button">
                      Ajouter au panier
                      <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                      </button>
                  </div>
          </article>
          <article class="product-card">
            <div class="product-card__media">
              <img
                src="images/noix_epicerie.webp"
                alt="D?cor de fruits de palme"
                class="product-card__decor"
              />
              <img
                src="images/concentre_epicerie.webp"
                alt="Cr?me concentr?e de noix de palme"
                class="product-card__image"
              />
            </div>
            <h3>Cr?me - Cr?me concentr?e de noix de palme</h3>
            <p><strong>A partir de 750 Fcfa</strong></p>
            <div class="product-card__overlay" role="group" aria-label="Options d'achat">
              <select class="product-card__select" aria-label="Choisir un format">
                <option value="300g">300g - 1500 Fcfa</option>
                <option value="500g">500g - 2500 Fcfa</option>
                <option value="1000g">1kg - 4500 Fcfa</option>
              </select>
              <button class="product-card__cta" type="button">
                Ajouter au panier
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
              </button>
            </div>
          </article>
        </div>
      </section>
    </section>

    

  <?php include 'includes/footer.php'; ?>












