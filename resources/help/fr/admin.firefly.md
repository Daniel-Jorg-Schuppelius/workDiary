---
title: "Firefly III comme source de rapprochement"
topic: admin.firefly
version: 1
keywords:
    - Firefly III
    - mouvements de compte
    - encaissement
    - rapprochement
    - libellé
    - jeton d’accès personnel
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
    - admin.scheduler
---

La page **Firefly III** lit les entrées et sorties de votre installation Firefly III
et indique à quelle facture ou facture entrante un mouvement appartient. Vous trouvez
la page dans le menu système (la roue dentée **Système**) sous **Plugins** →
**Firefly III**, dès que le plugin est actif. WorkDiary n’écrit rien dans Firefly III
et ne comptabilise aucun paiement à partir des mouvements.

## Configurer l’accès

1. Dans Firefly III, créez un **jeton d’accès personnel** sous **Options** →
   **Profil** → **OAuth**.
2. Sur la page, ouvrez **Identifiants** dans le menu ⋯ et saisissez l’**adresse**
   (sans /api) et le jeton. Si Firefly III tourne dans votre propre réseau, activez
   aussi **Autoriser les adresses privées** – cela n’a d’effet que si l’exploitant
   l’autorise.
3. Cliquez sur **Vérifier la connexion**. WorkDiary affiche l’instance, la version
   et le compte.

## Ce qui est rapproché

Chaque passage lit les mouvements de la **période** (60 jours jusqu’à aujourd’hui
par défaut, réglable sous **Identifiants**). Les virements entre vos propres comptes
sont ignorés.

- **Entrée :** si le libellé, les notes, la référence ou l’identifiant SEPA de bout en bout contiennent exactement un
  numéro de facture de votre organisation, la ligne affiche cette facture.
- **Sortie :** idem avec le numéro d’une facture entrante.
- Une opération ventilée (split) apparaît en une ligne par partie.
- Si plusieurs pièces correspondent, le mouvement est **Ambigu** ; sans
  correspondance, il est **Sans rattachement**. Le montant et la date seuls ne
  rattachent jamais.

Ce qui manque dans Firefly III dans une période entièrement lue disparaît de la
liste. Un passage interrompu est marqué **incomplet** et ne supprime rien.

## Quand la synchronisation a lieu

Toutes les heures ; modifiez la cadence sous **Jobs planifiés**, **Synchroniser
maintenant** lance tout de suite. **Déconnecter** arrête la synchronisation.

## Erreurs fréquentes

- La vérification échoue avec 401 : le jeton est erroné ou expiré.
- La vérification signale une adresse interne : l’adresse pointe vers votre réseau,
  mais **Autoriser les adresses privées** est désactivé ou non autorisé par
  l’exploitant.
