<?php
declare(strict_types=1);
$orderId = trim((string) ($_GET['order_id'] ?? ''));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Paiement reussi</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/payment_popup.css">
</head>
<body class="payment-page">
  <section class="payment-popup" role="dialog" aria-modal="true" aria-labelledby="paymentSuccessTitle">
    <div class="payment-confetti" aria-hidden="true">
      <span style="left:18%;top:18%;background:#f59e0b;"></span>
      <span style="left:28%;top:12%;background:#3b82f6;"></span>
      <span style="left:44%;top:10%;background:#a855f7;"></span>
      <span style="left:62%;top:14%;background:#ef4444;"></span>
      <span style="left:74%;top:18%;background:#83BA3A;"></span>
    </div>
    <div class="payment-icon-wrap">
      <span class="payment-icon" aria-hidden="true">✓</span>
    </div>
    <h1 class="payment-title" id="paymentSuccessTitle">Success!</h1>
    <p class="payment-message">Votre paiement a ete confirme.</p>
    <?php if ($orderId !== ''): ?>
      <p class="payment-order">Commande #<?= htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <a class="payment-cta" href="panier.php">Voir le recapitulatif</a>
  </section>
</body>
</html>


