<?php
declare(strict_types=1);

$page_title = 'Contact';
$additional_css = [];
include 'includes/header.php';
?>

<main>
    <div class="contact-banner"></div>
    <div class="h1">Vous avez une preoccupation, une question, besoin d'avoir plus de renseignements ?</div>

    <section class="main-contact-section">
        <div class="contact-form-container">
            <h2 class="form-title">Laissez-nous un message</h2>
            <form id="contactForm" method="POST" style="border: 1px solid #ddd; padding: 20px;" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($freshyCsrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">

                <div class="form-group">
                    <label for="full-name"></label>
                    <input type="text" id="full-name" name="name" placeholder="Nom complet" required minlength="2" maxlength="120">
                    <small class="form-error" data-error-for="name" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <input type="text" id="whatsapp-number" name="whatsapp_number" placeholder="Entrez votre numero (Ex : +229 01*********)">
                    <label for="whatsapp-number">Numero WhatsApp (optionnel)</label>
                    <small class="form-error" data-error-for="whatsapp_number" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <label for="email"></label>
                    <input type="email" id="email" name="email" placeholder="Email" required maxlength="190">
                    <small class="form-error" data-error-for="email" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <label for="subject"></label>
                    <select id="subject" name="subject" required>
                        <option value="">Objet</option>
                        <option value="Demande d'informations">Demande d'informations</option>
                        <option value="Support technique">Support technique</option>
                        <option value="Proposition de partenariat">Proposition de partenariat</option>
                        <option value="Autre">Autre</option>
                    </select>
                    <small class="form-error" data-error-for="subject" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <label for="message"></label>
                    <textarea id="message" name="message" placeholder="Message" rows="5" required minlength="10" maxlength="5000"></textarea>
                    <small class="form-error" data-error-for="message" aria-live="polite"></small>
                </div>

                <div style="margin-left: 219px;">
                    <button type="submit" id="contactSubmitBtn" class="btn-send-message">
                        <span class="btn-label">Envoyer <i class="fas fa-arrow-right"></i></span>
                        <span class="btn-spinner" aria-hidden="true" style="display:none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="contact-details-container">
            <h2 class="details-title">Siege Social</h2>
            <div class="contact-address">
                <p class="address-line">Abomey calavi,<br>Tankpe <br> Republique du Benin</p>
                <p class="contact-line">Contact : 0144920824, <br> Email : freshyindustries24@gmail.com</p>
            </div>

            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15843.435002778393!2d2.34865185!3d6.41680505!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x102353a25d266e7b%3A0x6e7e7e7e7e7e7e7e!2sAbomey-Calavi%2C%20B%C3%A9nin!5e0!3m2!1sfr!2sbj!4v1678912345678!5m2!1sfr!2sbj" width="100%" height="545" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactForm');
  const submitBtn = document.getElementById('contactSubmitBtn');
  if (!form || !submitBtn) return;

  const label = submitBtn.querySelector('.btn-label');
  const spinner = submitBtn.querySelector('.btn-spinner');
  const errorNodes = new Map(
    Array.from(form.querySelectorAll('[data-error-for]')).map((node) => [node.getAttribute('data-error-for'), node])
  );

  const clearErrors = () => {
    errorNodes.forEach((node, field) => {
      node.textContent = '';
      const input = form.querySelector(`[name="${field}"]`);
      if (input) input.classList.remove('is-invalid');
    });
  };

  const setErrors = (errors) => {
    if (!errors || typeof errors !== 'object') return;
    Object.entries(errors).forEach(([field, message]) => {
      const node = errorNodes.get(field);
      if (node) node.textContent = String(message || '');
      const input = form.querySelector(`[name="${field}"]`);
      if (input) input.classList.add('is-invalid');
    });
  };

  const setLoading = (loading) => {
    submitBtn.disabled = loading;
    if (label) label.style.display = loading ? 'none' : '';
    if (spinner) spinner.style.display = loading ? 'inline-flex' : 'none';
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    setLoading(true);
    try {
      const formData = new FormData(form);
      const response = await fetch('handlers/contact_submit.php', {
        method: 'POST',
        body: formData,
        headers: { Accept: 'application/json' }
      });
      const payload = await response.json();

      if (!response.ok || payload?.success !== true) {
        const firstFieldError = payload?.errors && typeof payload.errors === 'object'
          ? Object.values(payload.errors)[0]
          : '';
        setErrors(payload?.errors);
        const errorMessage = firstFieldError || payload?.message || 'Envoi impossible.';
        if (typeof window.showToast === 'function') {
          window.showToast('error', errorMessage, { key: 'contact_submit_error' });
        } else {
          alert(errorMessage);
        }
        return;
      }

      form.reset();
      clearErrors();
      if (typeof window.showToast === 'function') {
        window.showToast('success', payload.message || 'Votre message a ete envoye avec succes.', { key: 'contact_submit_success' });
      } else {
        alert(payload.message || 'Votre message a ete envoye avec succes.');
      }
    } catch (error) {
      if (typeof window.showToast === 'function') {
        window.showToast('error', 'Erreur reseau. Veuillez reessayer.', { key: 'contact_submit_network' });
      } else {
        alert('Erreur reseau. Veuillez reessayer.');
      }
    } finally {
      setLoading(false);
    }
  });

  form.addEventListener('input', (event) => {
    const field = event.target?.getAttribute?.('name');
    if (!field) return;
    const node = errorNodes.get(field);
    if (node && node.textContent) {
      node.textContent = '';
      event.target.classList.remove('is-invalid');
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
