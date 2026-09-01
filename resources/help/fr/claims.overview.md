---
title: "Réclamations & garantie"
topic: claims.overview
version: 1
audience: []
modules:
    - module.claims
related:
    - documents.manage
---

Le module gère les réclamations, les cas de garantie, les décisions de
geste commercial et les retours sous forme de dossiers traçables — de la
réception à l'évaluation et à la décision, jusqu'aux conséquences sur le
stock, le service et la facturation.

**Dossier :** Chaque réclamation reçoit son propre numéro (REK-…), des
échéances, des responsables et des liens vers la commande, le projet,
l'actif, l'article, le numéro de série, la facture et le fournisseur. Les
modules métier restent maîtres — le dossier relie, il n'écrase rien.

**Évaluation & décision :** Type de droit invoqué (garantie commerciale,
garantie légale ou contractuelle, geste commercial, dommage de transport,
erreur de manipulation, faute du fournisseur) avec justification
obligatoire. L'état des faits (vérification du numéro de série,
échéances, date de réclamation selon « § 377 HGB » dans le cas B2B) est
figé sous forme de snapshot. Les décisions requièrent une évaluation
active et sont auditables ; il n'existe volontairement aucune décision
automatique sur le droit invoqué.

**Retours (RMA) :** Les retours reçoivent un numéro RMA, la réception de
marchandise est placée en quarantaine (stock bloqué/de contrôle), le
contrôle documente le constat et le rapprochement des numéros de série.
La décision d'utilisation (remise en stock, réparation, renvoi au
fournisseur, mise au rebut, élimination) est comptabilisée de manière
idempotente via le journal de stock.

**Conséquences commerciales :** Réduction de prix, avoir, annulation,
correction, facture de remplacement ou remboursement sont proposés,
validés selon le principe des quatre yeux et exécutés seulement ensuite.
Les pièces naissent dans le module de facturation (avoir/annulation comme
brouillon) avec un code de motif structuré — il n'existe pas de type de
pièce dédié.

**Recours fournisseur :** Créance propre envers le fournisseur amont,
avec référence à la commande ou à la facture entrante, délai de réponse
et reflux des coûts.

**Analyse :** Le rapport qualité montre le taux, les causes, les articles
concernés, les fournisseurs, les coûts, la durée de traitement et les
défauts récurrents ; les états du rapport peuvent être figés comme
justificatifs.

**Portail client :** Les clients voient le statut de leurs propres cas et
peuvent transmettre des compléments — les évaluations internes et les
montants restent invisibles.
