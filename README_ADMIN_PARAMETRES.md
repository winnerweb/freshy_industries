# README — Page Admin `parametres.php`

## URL concernée
`http://localhost/site_test/Admin/pages/parametres.php`

## Ce que l’admin peut faire actuellement

La page **Paramètres** contient 3 blocs fonctionnels connectés à `Admin/api/admin_settings.php`.

### 1) Paramètres généraux
Formulaire `#settingsGeneralForm`:
- `Nom de la boutique` (`shop_name`)
- `Email support` (`support_email`)

Action possible:
- Enregistrer ces deux valeurs dans la table `app_settings`.

Validation actuelle:
- `shop_name` obligatoire
- `support_email` doit être un email valide

Résultat:
- Sauvegarde via `action = save_general`
- Toast succès: `Parametres generaux enregistres.`

---

### 2) Gestion profil
Formulaire `#settingsProfileForm`:
- `Nom administrateur` (`admin_name`)
- `Nouveau mot de passe` (`admin_password`)

Actions possibles:
- Mettre à jour le `admin_name` dans `app_settings`
- Mettre à jour un hash de mot de passe dans `app_settings` (`admin_password_hash`) **si un mot de passe est saisi**

Validation actuelle:
- `admin_name` obligatoire
- mot de passe optionnel, mais si saisi: minimum 8 caractères

Résultat:
- Sauvegarde via `action = save_profile`
- Le champ mot de passe est vidé côté UI après succès
- Toast succès: `Profil admin mis a jour.`

---

### 3) Configuration système
Formulaire `#settingsSystemForm`:
- Mode de paiement (`payment_mode`): `simulateur` ou `psp_reel`
- Fuseau horaire (`timezone`)
- Driver stockage média (`media_storage_driver`): `local` ou `cloudinary`
- Paramètres Cloudinary:
  - `cloudinary_cloud_name`
  - `cloudinary_upload_preset`
  - `cloudinary_folder`

Actions possibles:
- Sauvegarder toute la configuration système via `action = save_system`

Validation actuelle:
- `payment_mode` doit être dans la liste autorisée
- `timezone` obligatoire
- `media_storage_driver` doit être `local` ou `cloudinary`
- si `cloudinary` est choisi: `cloud_name` + `upload_preset` obligatoires
- `cloudinary_folder` retombe sur `freshy/products` si vide

Résultat:
- Toast succès: `Configuration systeme appliquee.`

---

## Chargement des données (pré-remplissage)
À l’ouverture de la page:
- JS appelle `GET ../api/admin_settings.php`
- Les champs sont remplis depuis `app_settings`
- Valeurs par défaut si clé absente:
  - `shop_name`: `Freshy Industries`
  - `support_email`: `support@freshy.local`
  - `admin_name`: `Admin`
  - `payment_mode`: `simulateur`
  - `timezone`: `Africa/Porto-Novo`
  - `media_storage_driver`: `local`
  - `cloudinary_folder`: `freshy/products`

---

## Sécurité actuellement en place
- Accès API protégé par session admin + rôle `admin`
- Requêtes d’écriture (`POST`) protégées par token CSRF (`X-CSRF-Token`)
- Requêtes SQL en prepared statements
- Réponses JSON structurées + codes HTTP (`422`, `400`, `405`, `500`…)

---

## Impact réel dans le projet

### Paramètres qui ont un impact direct confirmé
- `media_storage_driver` + Cloudinary sont réellement utilisés par:
  - `Admin/includes/media_storage.php`
  - donc impactent le pipeline d’upload média admin (local vs cloud)

### Paramètres stockés mais impact non branché partout
- `shop_name`, `support_email`, `payment_mode`, `timezone`, `admin_name`, `admin_password_hash`
- Ils sont bien sauvegardés/lus via `app_settings`, mais leur exploitation fonctionnelle dépend d’autres pages/services.

Important:
- Le login admin actuel vérifie les comptes depuis `admin_users.password_hash` (pas `app_settings.admin_password_hash`).
- Donc changer le mot de passe dans cette page **ne remplace pas automatiquement** le mot de passe de connexion du compte `admin_users` existant, sauf logique additionnelle ailleurs.

---

## Erreurs possibles côté admin
- `Invalid payload` (données manquantes/invalides)
- `Password too short`
- `Invalid payment mode`
- `Invalid timezone`
- `Invalid media storage driver`
- `Cloudinary configuration incomplete`
- `Table app_settings missing` (si schéma DB non appliqué)

---

## Fichiers impliqués
- Page: `Admin/pages/parametres.php`
- JS: `Admin/js/admin_settings_live.js`
- API: `Admin/api/admin_settings.php`
- Sécurité API: `api/_helpers.php`
- Consommation stockage média: `Admin/includes/media_storage.php`

---

## Résumé rapide
La page `parametres.php` permet aujourd’hui à l’admin de:
- sauvegarder des paramètres généraux
- sauvegarder des infos profil (nom + hash mot de passe dans `app_settings`)
- configurer le mode de stockage média (local/cloudinary) avec validations

Le bloc **le plus opérationnel immédiatement** est la configuration de stockage média (driver Cloudinary/local), qui est branchée sur le système d’upload.
