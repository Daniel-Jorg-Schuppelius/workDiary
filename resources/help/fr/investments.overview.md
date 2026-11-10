---
title: "Planification des investissements"
topic: investments.overview
version: 3
keywords:
    - demande d'investissement
    - demande de budget
    - dépenses d'investissement
    - CapEx
    - validation d'investissement
    - comparaison des offres
    - dépassement de budget
    - prévu réalisé
    - analyse de rentabilité
    - retour d'expérience
audience: []
modules:
    - module.investments
related:
    - documents.manage
---

Le dossier d'investissement suit un investissement de l'idée jusqu'à
l'évaluation a posteriori : motif, objectif/bénéfice, risque, comparaison
de variantes avec offres (coûts uniques et récurrents, délai de
livraison, qualité, recommandation), demande de budget versionnée et
chaîne de validation.

**Validations :** En dessous du seuil, une validation commerciale
suffit ; à partir du seuil (configurable, 10 000 € par défaut), le
principe des quatre yeux s'applique avec un niveau de management
supplémentaire. Le demandeur ne peut jamais valider lui-même. Les budgets
approuvés sont figés sous forme de snapshot — une augmentation passe
toujours par un écart budgétaire approuvé et un avenant (nouvelle
demande, l'ancien état est conservé comme « remplacé »).

**Boîte des approbations :** Les étapes ouvertes d'une demande de budget
apparaissent sous « Approbations » et dans la file de travail pour le
rôle que votre organisation attribue au type d'étape (par défaut :
commercial → comptabilité, direction → direction). À la soumission et au
passage à l'étape suivante, les responsables sont notifiés. On y décide
comme dans le dossier ; seules les personnes autorisées à approuver des
investissements peuvent valider.

**Décider depuis le dossier :** La même responsabilité que dans la boîte
s'applique. « Valider » et « Refuser » ne s'affichent que pour la personne
responsable de l'étape ouverte qui peut la trancher — ni pour le
demandeur, ni pour qui a tranché une étape antérieure du même tour. Les
autres voient ce que la demande attend, par exemple « En attente de
l’étape 2 : Direction (rôle Direction). » Les administrateurs eux aussi ne
décident qu'avec le rôle de l'étape ; vous définissez ce rôle dans les
paramètres de l'organisation sous « Approbations ».

**Report et refus :** Pendant la planification, le dossier peut être mis
en attente avec « Reporter » ; « Reprendre » le ramène exactement à la
phase où il se trouvait. Le refus d'une demande de budget est
définitif : un dossier refusé reste refusé et ne peut être ni reporté ni
soumis à nouveau — une nouvelle tentative commence par un nouveau
dossier.

**Réalisation & valeurs réelles :** Après la validation, on relie le
projet, la commande, l'actif, la facture entrante ou le document. La vue
prévu/réel montre le budget approuvé, les moyens engagés (commandes) et
les coûts réels (factures entrantes liées, coûts d'acquisition des
actifs, valeurs manuelles) sous forme de projection — les modules maîtres
restent maîtres, WorkDiary ne remplace aucune comptabilité.

**Évaluation a posteriori :** Après la clôture ou l'abandon, on documente
le bénéfice réel, la rentabilité, les enseignements tirés (lessons
learned) et les mesures de suivi.
