---
title: "orgaMAX comptabilité"
topic: admin.orgamax
version: 2
keywords:
    - API orgaMAX
    - intégration comptable
    - logiciel de comptabilité
    - facturation
    - transfert de factures
    - synchroniser les clients
    - statut de paiement
    - données de base
    - synchronisation
audience:
    - admin
modules:
    - module.finance
related:
    - admin.integrations
    - admin.plugins
---

orgaMAX comptabilité est connecté comme plugin par organisation via
l'OpenAPI officielle (pas orgaMAX ERP). orgaMAX reste le système pilote pour
les capacités activées.

Connexion :

1. **Démarrer une intention de connexion** (mode pilote privé avec clé/secret
   API ou extension publiée avec secret d'exploitant). WorkDiary génère une
   URL de rappel avec jeton d'état.
2. Enregistrer l'URL comme URL d'extension dans orgaMAX et l'ouvrir — orgaMAX
   ajoute l'`iid`. Un `iid` étranger sans intention valide n'est jamais lié.
3. **Confirmer explicitement** le compte détecté ; le preflight des portées
   bloque en cas d'autorisations manquantes au lieu d'activer partiellement.

Gouvernance des données par capacité (clients, fournisseurs, articles,
facturation, paiements, dépenses, documents) : un seul système pilote ; la
valeur sûre est la revue manuelle. Les données de base sont rapprochées via
la boîte d'intégration — pas de données fantômes.

Facturation : les transferts validés (Finances → Transferts, cible orgaMAX)
créent au plus UNE commande orgaMAX (marqueur source + rapprochement au lieu
de répétitions aveugles). Conversion en facture, verrouillage irréversible,
envoi et enregistrement des paiements sont des actions séparées, avec
permissions propres et auditées. Numéro, statut, paiement et PDF proviennent
visiblement d'orgaMAX.

Le polling est budgété avec points de contrôle (horaire, configurable) ;
« Synchroniser maintenant » respecte les mêmes limites. Le transfert des
dépenses/justificatifs reste bloqué jusqu'à la validation du pilote receipt.

Miroir des pièces : si la facturation ou les paiements sont actifs, la
synchronisation reflète les factures orgaMAX avec leurs positions et, avec la
capacité dépenses, aussi les dépenses comme pièces entrantes. Elles
apparaissent dans le flux des pièces, dans le dossier client et fournisseur,
dans le chiffre d'affaires, les postes ouverts et les commissions ainsi que
dans les achats d'abonnements et de licences ; les modèles récurrents figurent
dans le rapprochement des abonnements et licences (l'API orgaMAX ne fournit ni
rythme ni prochaine exécution). Avec la capacité articles, les articles
orgaMAX font partie du catalogue d'articles. Les données sont seulement lues —
WorkDiary ne réécrit pas les dépenses.

WorkDiary ne transfère les fournisseurs que si la capacité fournisseurs est
active et que « WorkDiary pilote » est choisi ; orgaMAX attribue alors le
numéro fournisseur.
