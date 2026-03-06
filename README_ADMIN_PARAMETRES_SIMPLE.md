# Guide Simple — Page Paramètres Admin

## Page concernée
`http://localhost/site_test/Admin/pages/parametres.php`

## À quoi sert cette page ?
Cette page permet à l’administrateur de configurer les réglages principaux de l’espace admin.

## Ce que vous pouvez faire

### 1) Paramètres généraux
Vous pouvez modifier :
- le nom de la boutique
- l’email de support

Quand vous cliquez sur **Enregistrer**, les nouvelles valeurs sont sauvegardées.

### 2) Gestion profil
Vous pouvez :
- changer le nom affiché de l’administrateur
- saisir un nouveau mot de passe dans ce formulaire

Quand vous cliquez sur **Mettre à jour**, les données sont sauvegardées.

### 3) Configuration système
Vous pouvez régler :
- le mode de paiement (`simulateur` ou `PSP réel`)
- le fuseau horaire
- le mode de stockage des médias :
  - `local` (sur le serveur)
  - `cloudinary` (dans le cloud)

Si vous choisissez Cloudinary, vous devez renseigner les champs Cloudinary demandés.

## Ce qui se passe après clic
- Un message de succès s’affiche si tout est correct.
- Un message d’erreur s’affiche si une information est invalide.

## Règles importantes
- L’email support doit être valide.
- Le nom admin ne peut pas être vide.
- Si vous mettez un mot de passe, il doit avoir au moins 8 caractères.
- En mode Cloudinary, les champs Cloudinary obligatoires doivent être remplis.

## Sécurité
La page est protégée :
- seuls les admins autorisés peuvent sauvegarder
- les envois sont sécurisés (anti-falsification)

## En résumé
Sur cette page, vous gérez :
- l’identité de la boutique
- les informations de profil admin
- la configuration système, surtout le stockage des médias

C’est la page centrale pour piloter les réglages globaux de l’administration.
