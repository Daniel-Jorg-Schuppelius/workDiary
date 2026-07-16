---
title: "Gestion des domaines"
topic: domains.overview
version: 1
audience: []
related:
    - admin.domain-provider
    - contacts.manage
---

Le module gère les domaines d'un compte DomainReselling connecté comme un
portefeuille traçable : de l'affectation client et l'échéance aux serveurs
de noms/DNS, jusqu'au renouvellement, au transfert et aux écritures. La
connexion elle-même se configure sous « DomainReselling » dans
l'administration.

**Portefeuille :** La vue d'ensemble liste chaque domaine avec le client,
l'expiration, le mode de renouvellement, le bureau d'enregistrement, le
verrou de transfert et l'actualité des données. Les indicateurs en haut
montrent l'expiration sous 90 jours, les modes à risque
(autoexpire/autodelete), les domaines sans affectation client et les cas de
synchronisation/conflit. On filtre par nom de domaine, TLD, actualité, mode
de renouvellement et corridor d'expiration.

**Affectation client :** Chaque domaine peut être affecté à un client (en
interne via son identifiant Sqid). Les domaines non affectés restent
visibles dans l'indicateur afin de maintenir le portefeuille complet.

**Vue de détail :** La page du domaine réunit synthèse, serveurs de noms et
DNS, factures, chronologie et actions. « Actualiser » réconcilie l'état du
fournisseur pour ce domaine précis.

**DNS :** La zone est lue à la demande ; les enregistrements peuvent être
remplacés ou modifiés de manière ciblée. Après une écriture, le système
détecte les écarts (conflit DNS) et les rend visibles au lieu de les
écraser. Les enregistrements MX/SRV exigent une priorité.

**Enregistrement :** La disponibilité est vérifiée avant l'enregistrement.
Un enregistrement nécessite un client, un handle de contact propriétaire, au
moins deux serveurs de noms et une confirmation de prix explicite.

**Échéance et transfert :** Définir le mode de renouvellement, renouveler
manuellement, poser ou lever le verrou de transfert et lancer un transfert
entrant s'exécutent comme des commandes journalisées avec un historique
d'état (brouillon → envoyé → confirmé).

**Actions à haut risque :** La suppression, le push vers un autre
utilisateur, le trade (changement de titulaire), le transfert sortant et
l'affectation d'objet sont verrouillés : ils exigent de retaper le nom du
domaine et une validation à quatre yeux. Les actions soumises apparaissent
pour validation ou refus ; l'état du fournisseur est réconcilié après
exécution (les conflits sont signalés).

**Écritures et rapports :** La vue des écritures est un journal en lecture
seule, pas une facture fiscale. Les rapports réunissent le corridor
d'expiration, la prévision des coûts de renouvellement, l'affectation
client, les modes à risque et la couverture des factures.

**Revendeurs/sous-utilisateurs :** La vue des revendeurs montre la
hiérarchie des sous-utilisateurs avec portefeuille, soldes et niveau, et
permet l'affectation client par sous-utilisateur.
