# README — Envoi Formulaire Contact vers Email

## Objectif
Rendre le formulaire `contact.php` fiable et sécurisé avec un envoi email SMTP + sauvegarde en base.

## Ce qui a été mis en place
- **Front**: `contact.php`
  - Soumission AJAX vers `handlers/contact_submit.php`
  - Bouton désactivé + spinner pendant l'envoi
  - Toast succès/erreur
  - Affichage des erreurs de validation sous les champs
- **Backend**: `handlers/contact_submit.php`
  - Validation serveur stricte
  - Vérification CSRF
  - Honeypot anti-bot (`website`)
  - Rate limiting (IP + email)
  - Transaction PDO: insert DB + envoi SMTP
- **Validation**: `includes/validation.php`
- **Config DB**: `config/database.php`
- **Config SMTP**: `config/smtp.php`
- **Dépendance email**: `phpmailer/phpmailer` via Composer
- **Table DB**: `contact_messages` (dans `database/schema.sql`)

## Logique transactionnelle
Flux dans `handlers/contact_submit.php`:
1. `BEGIN TRANSACTION`
2. `INSERT INTO contact_messages`
3. Envoi email SMTP via PHPMailer
4. Si tout va bien: `COMMIT`
5. Si erreur email/serveur: `ROLLBACK`

Résultat: on évite les incohérences (pas de message enregistré sans email envoyé).

## Variables SMTP à configurer (Apache / vhost)
Dans `httpd-vhosts.conf`:
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS` (mot de passe d'application Gmail si Gmail)
- `SMTP_ENCRYPTION` (`tls` ou `ssl`)
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `CONTACT_TO_EMAIL` (boîte qui reçoit les messages)
- `SMTP_TIMEOUT`

Exemple:
```apache
SetEnv SMTP_HOST "smtp.gmail.com"
SetEnv SMTP_PORT "587"
SetEnv SMTP_USER "ton_email@gmail.com"
SetEnv SMTP_PASS "mot_de_passe_application"
SetEnv SMTP_ENCRYPTION "tls"
SetEnv SMTP_FROM_EMAIL "ton_email@gmail.com"
SetEnv SMTP_FROM_NAME "Freshy Industries"
SetEnv CONTACT_TO_EMAIL "ton_email_test@gmail.com"
SetEnv SMTP_TIMEOUT "10"
```

Puis **redémarrer Apache**.

## Sécurité appliquée
- Requêtes SQL préparées (PDO)
- CSRF token obligatoire
- Validation email RFC (`filter_var`)
- Limites de taille des champs
- Honeypot anti-bot
- Rate limit anti-spam
- Encodage UTF-8
- Gestion d'erreurs JSON claire

## Vérification rapide
1. Ouvrir `contact.php`
2. Envoyer un message valide
3. Vérifier:
   - Toast succès
   - Ligne créée dans `contact_messages`
   - Email reçu dans `CONTACT_TO_EMAIL`

## Dépannage
- **Erreur 502**: SMTP mal configuré ou indisponible
- **Erreur 422**: validation formulaire échouée (voir erreurs inline)
- **Erreur 419**: CSRF invalide (recharger la page)

## Fichiers clés
- `contact.php`
- `handlers/contact_submit.php`
- `includes/validation.php`
- `includes/csrf.php`
- `config/smtp.php`
- `config/database.php`
- `database/schema.sql`
