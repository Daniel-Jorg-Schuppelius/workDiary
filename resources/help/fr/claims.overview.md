---
title: "Réclamations & garantie"
topic: claims.overview
version: 4
keywords:
    - plainte client
    - réclamation client
    - garantie légale
    - geste commercial
    - retour produit
    - RMA
    - SAV
    - défaut produit
    - recours fournisseur
    - taux de réclamation
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

**Relier les objets concernés :** Dans la boîte de dialogue « Nouvelle
réclamation » et via « Modifier » sur le dossier, vous choisissez la
facture, la
commande, le projet, le ticket de service, l’actif, le procès-verbal et le fournisseur, ainsi que l’article et la commande d’achat avec
le module de stock. Lorsqu’un client est choisi, les listes n’affichent
que ses objets. Un numéro de série connu en stock est rattaché
automatiquement au dossier. Sur
la facture, la commande, l’actif et le ticket de service, « Ouvrir une réclamation » ouvre la boîte de dialogue
avec l’objet et le client préremplis.

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

**Référence de stock du retour :** L’annonce et la réception reprennent la
variante d’article, la quantité, l’entrepôt, le numéro de série et le lot,
préremplis à partir de l’article et du numéro de série du dossier. Avec la
variante, l’entrepôt et la quantité, la réception comptabilise le stock en
quarantaine ; la décision d’affectation le transfère ou le sort du stock.
Sans variante ou sans module de stock, le retour est seulement documenté
et le message indique « enregistrée sans écriture de stock ». Les retours
du portail client apportent la variante et le numéro de série ; vous
complétez l’entrepôt à la réception.

**Mesures et commande de suivi :** Vous créez reprise, réparation,
remplacement ou intervention sous « Mesures » avec un délai et une personne
responsable. Chaque mesure peut indiquer sa commande de suivi — une commande du
client du dossier que vous êtes autorisé à ouvrir. L'intervention passe par
la planification et les tournées via cette commande ; le dossier la relie, et
vous pouvez la modifier ou la retirer en même temps que le statut de la mesure.

**Conséquences commerciales :** Réduction de prix, avoir, annulation,
correction, facture de remplacement ou remboursement sont proposés,
validés selon le principe des quatre yeux et exécutés seulement ensuite.
Les pièces naissent dans le module de facturation (avoir/annulation comme
brouillon) avec un code de motif structuré — il n'existe pas de type de
pièce dédié.

**Facture de la conséquence :** Dans la boîte de dialogue « Proposer une
conséquence », vous choisissez la facture, préremplie avec celle du
dossier. Si vous émettez vous-même les factures, chaque conséquence avec
document a besoin de cette facture : l’annulation suppose une facture
émise et impayée, l’avoir une facture payée. Si un système externe gère
les factures, le document correctif y est créé et vous saisissez ensuite
son numéro.

**Recours fournisseur :** Créance propre envers le fournisseur amont,
avec référence à la commande ou à la facture entrante, délai de réponse
et reflux des coûts.

La boîte de dialogue « Créer un recours » reprend
le fournisseur, la commande d’achat, la facture fournisseur, l’article, le numéro de série, les
conditions de garantie, le montant réclamé et un délai de réponse propre
en jours. Sans indication, le délai standard s’applique ; il court à
partir du statut « soumis ».

**Analyse :** Le rapport qualité montre le taux, les causes, les articles
concernés, les fournisseurs, les coûts, la durée de traitement et les
défauts récurrents ; les états du rapport peuvent être figés comme
justificatifs.

**Portail client :** Les clients voient le statut de leurs propres cas et
peuvent transmettre des compléments — les évaluations internes et les
montants restent invisibles.

**Lot sur le dossier :** pour les articles gérés par lot, choisissez le lot concerné dans la boîte de dialogue de réclamation. Sans article choisi, le dossier reprend l’article du lot ; la détection de motifs « même lot » exploite ce champ.

## Refuser une proposition

La personne qui approuve les suites commerciales peut aussi **refuser** une proposition. Elle se termine sans pièce et ne peut être ni approuvée ni exécutée ; une nouvelle proposition reste possible.
