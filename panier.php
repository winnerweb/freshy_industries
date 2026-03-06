<?php
$page_title = 'Panier';
$additional_css = [];
include 'includes/header.php';
?>

    <section class="hero-section"     >
      <div
        class="marquee"
        aria-label="Livraison gratuite à partir de 10000 Fcfa"
      >
        <div class="marquee-track">
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
          <span class="marquee-item"
            >Livraison gratuite à partir de <strong>10000 Fcfa</strong></span
          >
        </div>
      </div>
      <div class="hero-visual panier-hero-visual">
        <div class="hero-overlay">
          <h1>Epicerie du terroir</h1>
        </div>
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
                       <span class="label">Expédition</span>
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
        <span class="recap-toggle__label">Résumé de la commande</span>
        <span class="recap-toggle__icon" aria-hidden="true">
          <i class="fas fa-chevron-down"></i>
        </span>
        <span class="recap-toggle__total" id="orderSummaryTotal">0 Fcfa</span>
      </button>
    </section>

    <section class="enregistré" id="panierSection" style="margin-top: -302px;">
      <div class="panier-container" id="panierContainer">
        <h1 class="panier-titre">Mon Panier</h1>

        <div class="panier-header">
          <span class="header-produit">Produit</span>
          <span class="header-quantites">Quantités</span>
          <span class="header-total">Total</span>
        </div>

        <hr class="panier-separator-header" />

        <div class="panier-articles" id="panierArticles">
          <p class="panier-empty">Votre panier est vide.</p>
        </div>

        <hr class="panier-separator-article hidden" id="cartDivider" />

        <div class="panier-footer">
          <div class="footer-total">
            Total: <span class="footer-total-montant" id="cartTotalAmount">0 Fcfa</span>
          </div>
          <button class="footer-btn-verifier">Vérifier</button>
        </div>
      </div>
      <div class="carousel-header">
        <h2>Ceux-ci pourraient vous intéresser aussi</h2>
      </div>
      <section class="products-grid-section" aria-label="Sélection épicerie du terroir">
        <div class="carousel-container products-grid" data-catalog-source="products-api" data-catalog-context="panier-related" data-catalog-limit="4">
          <p class="panier-empty" data-catalog-loading="true">Chargement des produits...</p>
        
        </div>
      </section>
    </section>

  <?php include 'includes/footer.php'; ?>






