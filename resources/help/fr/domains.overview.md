---
title: "Gestion des domaines"
topic: domains.overview
version: 5
keywords:
    - enregistrer un domaine
    - renouveler un domaine
    - enregistrements DNS
    - zone DNS
    - serveurs de noms
    - enregistrement MX
    - transfert de domaine
    - verrou de transfert
    - changement de titulaire
    - expiration de domaine
    - revendeur de domaines
    - nom de domaine
audience: []
modules:
    - module.domain
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

**DNS :** La zone est lue à la demande. Dans l'interface, on peut ajouter et
supprimer des enregistrements individuels. « Remplacer la zone » n'apparaît
qu'une fois la zone lue : la boîte de dialogue est préremplie avec son état et
demande confirmation avant l'envoi, car un remplacement complet supprime tout
enregistrement absent du formulaire. Les enregistrements dont le formulaire ne
peut pas modifier le type sont conservés tels quels ; un formulaire vide est
refusé. Après une écriture, le système détecte les écarts
(conflit DNS) et les rend visibles au lieu de les écraser. Les enregistrements
MX/SRV exigent une priorité.

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

**Revendeurs/sous-utilisateurs :** La vue des revendeurs montre la hiérarchie
des sous-utilisateurs avec portefeuille, soldes et niveau, et permet
l'affectation client par sous-utilisateur. Pour chaque sous-utilisateur, vous
pouvez saisir le numéro client chez le fournisseur ; les pièces mensuelles
construites à partir du journal comptable dans le registre de revente portent
alors les numéros de facture du portail (« numéro client-MM-AAAA »).

**Commandes incertaines :** si la réponse du fournisseur reste incomplète, la commande passe à « incertaine » et n’est jamais répétée. La prochaine synchronisation lit l’état du domaine chez le fournisseur : enregistrement, mode de renouvellement, verrou de transfert et renouvellement sont ainsi confirmés, reconnus comme échoués ou marqués en conflit. Les transferts, le DNS et les actions à haut risque n’ont pas de consultation d’état — vérifiez-les chez le fournisseur et clôturez-les manuellement avec un motif dans le journal des commandes.

**Coûts et solde :** dans les paramètres de l’organisation, vous fixez un seuil de coût pour les renouvellements et un solde minimal. La gestion des domaines signale les renouvellements à venir au-dessus du seuil et les comptes fournisseur dont le solde, limite de crédit comprise, ne couvre pas les renouvellements des prochains jours — sans couverture, le fournisseur supprime les domaines à leur date d’échec.

**Mode de renouvellement :** vous pouvez choisir les trois modes documentés
par le fournisseur : renouvellement auto, expiration auto et suppression auto. Une
valeur enregistrée auparavant, « Renouveler une fois », devient inconnue après la
mise à jour ; la synchronisation suivante lit le mode réel chez le fournisseur.

## État de synchronisation d'un domaine

Pendant qu'une commande au fournisseur est en cours, le domaine affiche **En attente**. Si son résultat reste incertain ou si le fournisseur diverge après l'écriture, il affiche **Incertain** ou **Conflit** et apparaît dans la tuile des problèmes de synchronisation ; une synchronisation ne l'écrase pas tant que la commande est ouverte. Après la clôture – automatique ou manuelle – le domaine affiche **Obsolète** jusqu'à ce que la prochaine synchronisation le relise.
