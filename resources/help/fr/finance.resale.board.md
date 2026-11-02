---
title: "Classeur de travail et tableau de rapprochement"
topic: finance.resale.board
version: 1
keywords:
    - classeur de travail
    - tableau de rapprochement
    - frise
    - concordance
    - reprise
    - créance irrécouvrable
    - insolvabilité
    - changement de commande
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.reconcile
    - finance.resale.subscription
---

Le **classeur de travail** d'un destinataire de facture est l'espace où l'on
affecte : en haut ses abonnements, en dessous, par produit, le tableau de
rapprochement avec les périodes et les lignes de facture sur une même frise.
Les tuiles au-dessus du tableau le filtrent sur les produits concernés.

**Classeur de travail et tableau de rapprochement :** le rapprochement d’un
destinataire est le classeur de travail : en haut les abonnements avec
modification, reprise, cession de licences et retour, en dessous le tableau
de rapprochement par produit. À gauche toutes les périodes par date avec
prévu et couverture, à droite les lignes de facture : affectées avec leur
période de prestation, pour les périodes ouvertes les lignes libres
correspondantes, les factures à d’autres clients et les annulations. Les
lignes sans période correspondante apparaissent sur une ligne propre « sans
période » à leur date. Chaque facture libre ne figure qu’à la période qui
lui correspond le mieux, et « Affecter … » l’affecte d’un clic ; la quantité
est ce dont la période a encore besoin. Un décalage de quelques semaines
entre la date de commande chez le fournisseur et la date de facture est
normal et n’est pas affiché ; une facture groupée couvrant toute la période
correspond. Les factures sans période correspondante apparaissent sur une
ligne « sans période » avec « Créer l’abonnement » ou « Affecter à une autre
période ». Les affectations douteuses demandent une confirmation avant
l’enregistrement ; dans la boîte de dialogue de lien et dans l’affectation
rapide de la page d’abonnement, vous les confirmez avec la case à cocher.
Les liens qui ne correspondent pas dans le temps sont marqués en jaune —
vérifiez-les et supprimez-les si nécessaire. « Traiter le prochain
destinataire » dans la vue d’ensemble parcourt tous les destinataires avec
un montant ouvert ; un destinataire est terminé lorsqu’aucune période échue
n’est ouverte et qu’aucune constatation de modèle ne subsiste. Si le
fournisseur renouvelle un abonnement sous forme de nouvelle commande,
l’exécution des propositions abandonne la courte période résiduelle de
l’ancienne commande et transfère ses liens à la première période de la
nouvelle. La période résiduelle apparaît alors comme « Changement de commande »,
pas comme un abandon : les factures du successeur couvrent la période.

**Concordance :** Chaque ligne de facture porte une concordance avec la
période — d'après l'écart entre le début de la période et le début de cycle
le plus proche de sa période de prestation, ou avec la date de facture sans
période de prestation. Jusqu'à 14 jours compte comme exacte, jusqu'à 90 jours
comme décalée ; les lignes qui ne font que chevaucher, sont très éloignées ou
en dehors, ou correspondent nettement mieux à une autre période ouverte du
même produit demandent confirmation avant l'affectation : dans le classeur
comme question sur le bouton, dans la boîte de dialogue de lien et dans
l'affectation rapide de la page d'abonnement comme case à cocher. Si la
période de prestation couvre toute la période, la ligne concorde sans
question.

**Insolvabilité et reprise :** si une facture a été annulée parce que le
client est insolvable, comptabilisez la période dans le tableau avec «
Comptabiliser comme perte » — la boîte de dialogue de renonciation reprend
le motif avec le numéro de facture. Si une autre société poursuit les
licences, utilisez « Reprise » sur l’abonnement : nouveau titulaire et date
d’effet. Si la date d’effet correspond au début de la première période
ouverte, le nouveau titulaire est aussi facturé pour les périodes ouvertes ;
si elle est plus tardive, le registre abandonne sur demande les périodes
ouvertes antérieures comme créances irrécouvrables. Une date d’effet au
début de l’abonnement transfère tout l’abonnement au nouveau titulaire,
sinon une cession commence à la date d’effet. Si une facture va déjà à la
société qui reprend, le tableau propose la reprise directement sur cette
facture.
Si des périodes sont déjà facturées à l’ancien titulaire, le registre refuse
une date d’effet au début de l’abonnement — elle doit alors partir de la
première période ouverte.
