<?php
declare(strict_types=1);
$orderId = trim((string) ($_GET['order_id'] ?? ''));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Paiement echoue</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/payment_popup.css">
</head>
<body class="payment-page">
  <section class="payment-popup" role="dialog" aria-modal="true" aria-labelledby="paymentFailedTitle">
    <div class="payment-icon-wrap is-failed">
      <span class="payment-icon is-failed" aria-hidden="true">✕</span>
    </div>
    <h1 class="payment-title" id="paymentFailedTitle">Paiement echoue</h1>
    <p class="payment-message">La transaction n'a pas ete validee. Vous pouvez reessayer.</p>
    <?php if ($orderId !== ''): ?>
      <p class="payment-order">Commande #<?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <a class="payment-cta is-failed" href="panier.php">Revenir au panier</a>
  </section>
</body>
</html>

