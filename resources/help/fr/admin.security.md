---
title: "Sécurité et durcissement"
topic: admin.security
version: 2
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

## Blocage IP temporaire et export SIEM

Sans fail2ban sur le serveur, WorkDiary peut bloquer lui-même
temporairement des adresses après des échecs répétés (variable
d'environnement `SECURITY_IP_BAN`, désactivée par défaut) : 15 minutes, une
heure en cas de récidive, puis 24 heures. Les sessions connectées et les
réseaux privés ne sont jamais concernés ; vous voyez et levez les blocages
actifs sous « Détection d’attaques ». Comme de nombreux utilisateurs
peuvent partager une adresse (réseaux mobiles, réseaux d'entreprise),
fail2ban reste le premier choix. Pour un SIEM, WorkDiary écrit en outre
chaque événement de sécurité au format CEF ou JSON dans un fichier distinct
ou via syslog (`SECURITY_SIEM_FORMAT`, `SECURITY_SIEM_TARGET`).

## Sécuriser un compte après une prise de contrôle

S’il est confirmé qu’une personne a pris le contrôle d’un compte, la
déconnexion ne suffit pas : quiconque connaît le mot de passe se reconnecte.
« Sécuriser le compte » dans la gestion des sessions (pour les membres de
votre organisation) et « Confirmer la prise de contrôle du compte » sur un
événement de sécurité (administration de la plateforme) mettent fin à toutes
les sessions et à tous les jetons API, invalident le mot de passe et toutes
les clés d’accès et envoient à la personne un lien pour définir un nouveau mot
de passe. Les méthodes à deux facteurs par application sont conservées.
L’opération apparaît comme événement de sécurité et dans le journal d’audit.
Vous sécurisez votre propre compte sur votre page d’authentification à deux
facteurs.
