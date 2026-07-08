---
title: "Sécurité et durcissement"
topic: admin.security
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.backups
    - isms.software
---

La page **« Sécurité »** regroupe en lecture seule l'état pertinent :
sessions actives, jetons API (métadonnées uniquement), intégrations
externes, derniers exports, accès support, couverture 2FA et statut du
chiffrement au repos. L'authentification à deux facteurs permet
plusieurs méthodes en parallèle (**TOTP**, **code e-mail**,
**WebAuthn**) — recommandez-en au moins deux. La commande
`php artisan security:encrypt-existing` chiffre les champs sensibles
existants de façon idempotente ; attention, le chiffrement dépend de
l'**APP_KEY** — sauvegardez-le séparément, sinon les données sont
irrécupérables. `php artisan audit:verify` valide les chaînes de
hachage des journaux d'audit (à garder au vert), `php artisan
system:health` vérifie l'état du système, et l'aperçu des composants
génère une **SBOM** (CycloneDX 1.5) pour les audits.
