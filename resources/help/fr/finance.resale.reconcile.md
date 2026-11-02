---
title: "Rapprochement par destinataire"
topic: finance.resale.reconcile
version: 1
keywords:
    - rapprochement
    - destinataire de facture
    - liste de travail
    - montant ouvert
    - jamais facturé
    - facture à un autre client
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.board
    - finance.resale.periods
---

Le **rapprochement** met en regard, par destinataire de facture, les périodes
échues et les lignes de licence de ses factures. La liste indique par
destinataire le montant ouvert, les constats de modèles et s'il est terminé ;
le filtre par défaut masque les destinataires terminés, et « Traiter le
destinataire suivant » ouvre le premier classeur ouvert.

**Rapprochement par destinataire de facture :** lorsque des périodes restent
ouvertes sans que l’on sache s’il manque une facture ou seulement le
rattachement, utilisez le rapprochement (bouton dans la liste des
abonnements, sur la page des périodes et sur le client). Par destinataire —
le client avec ses clients finaux — il confronte les périodes échues de
tous les abonnements aux lignes de licence de ses factures, en mois de
licence par produit : *attendu* d’après les périodes, *facturé* d’après les
lignes. Le constat dit quoi faire : « seulement non rattaché » (les lignes
libres suffisent — rattacher), « jamais facturé » (plus de périodes que de
lignes — facture de rattrapage via le brouillon ou renonciation) ou « sans
période » (plus de lignes que de périodes — abonnement manquant dans le
registre ou double facturation). Par période ouverte, les lignes du même
produit sont listées avec leur distance au début de période : les libres
avec rattachement, les consommées avec leur détenteur pour contrôle. La date de référence est la **période
de prestation** de la facture, sinon la date de facture ; licences et mois
sont affichés séparément (« 5 × 12 mois » = cinq licences pour un an). Plus
trois pièges invisibles par abonnement : **facture à un autre client** (le
compte fournisseur n’est pas le client, ou le client final est facturé
directement au lieu du partenaire ; reconnu à un élément de nom commun) —
la solution est « Détenteur → client » : l’abonnement passe à ce client et
la proposition s’applique aussitôt. Les factures **annulées** près du début
de période expliquent une période vide. Les abonnements de la **boîte de
réception** dont la société apparaît dans les textes de facture attendent
leur détenteur. Une ligne libre qui ne touche plus aucune période de son
produit signale un contrat absent du registre (l’export fournisseur ne le
connaît pas) : la ligne produit indique « Ligne sans abonnement à partir
du … » et « Créer l’abonnement depuis la ligne » ouvre le dialogue avec
article, quantité, début et prix issus de la facture. Le rattachement peut
viser une période d’un autre abonnement du même destinataire — jamais une
période d’un autre client.
