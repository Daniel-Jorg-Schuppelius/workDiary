---
title: "Comptabiliser et boîte de saisie"
topic: accounting.posting
version: 5
keywords:
    - passer une écriture
    - écriture comptable
    - comptabiliser des pièces
    - imputation comptable
    - proposition de comptabilisation
    - règles de comptabilisation
    - extourne
    - contre-passation
    - principe des quatre yeux
    - validation
    - devise étrangère
    - taux de change
    - journal comptable
    - postes ouverts
    - écriture récurrente
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

La **boîte de saisie** est le point d'entrée : elle montre pièces, notes de
frais, opérations de caisse et paiements de la période avec leur statut.
Les éléments bloqués figurent en tête.

**Proposition avant comptabilisation.** Même une proposition univoque ne
devient qu'un brouillon vérifié. Avec le principe des quatre yeux, celui qui
prépare ne comptabilise pas lui-même.

**Principe des quatre yeux pour les écritures directes.** Certaines opérations
créent elles-mêmes leur écriture : **Escompte** et **Passage en perte** via
**Solder** dans les postes ouverts, **Comptabiliser en compte d’attente** dans
le rapprochement bancaire, le **Virement interne**, **Importer les soldes
initiaux**, **Comptabiliser l'acompte spécial** et **Extourner** dans le
journal. Sans principe des quatre yeux, elles sont comptabilisées
immédiatement. S'il est actif, un brouillon vérifié est créé : un message et
une remarque dans la boîte de dialogue l'indiquent, et la boîte de saisie le
liste avec son type (**Escompte/passage en perte**, **Écriture d’attente**,
**Virement interne**, **Soldes initiaux**, **Acompte spécial**, **Extourne**)
et le statut **Prêt** – indépendamment de la période de l'en-tête, tant que
**Toutes les sources** est sélectionné. L'opération ne prend effet que
lorsqu'une deuxième personne la comptabilise avec **Comptabiliser** ou **Tout
accepter et comptabiliser** : alors seulement le poste ouvert est soldé,
l'opération bancaire comptabilisée et l'acompte spécial imputé dans la dernière
déclaration de l'année. Dans le journal, **Comptabiliser immédiatement** reste
bloqué pour la personne qui crée l'écriture.

**Rejeter le brouillon.** Un brouillon en attente issu de ces opérations se
supprime avec **Rejeter le brouillon** dans la boîte de saisie ou sur sa page
de détail (autorisation **Comptabiliser les écritures**, avec confirmation).
L'étape est journalisée et l'opération est de nouveau ouverte : le poste peut
de nouveau être soldé, l'opération bancaire, les soldes initiaux et l'acompte
spécial peuvent être comptabilisés à nouveau, l'écriture d'une extourne
rejetée peut de nouveau être extournée, et un virement interne disparaît avec
le lien de ses pièces. Les écritures comptabilisées et les brouillons issus de
propositions ne peuvent pas être rejetés.

**Bloqué plutôt que deviné.** Si une règle manque, la proposition nomme le rôle
et les critères. Un compte par défaut deviné n'apparaîtrait qu'à l'analyse.

**Correction uniquement par contre-écriture.** Une écriture comptabilisée est
immuable ; l'extourne crée une contre-écriture avec motif obligatoire.

**Pièces en devise.** Les factures clients et fournisseurs ainsi que les notes
de frais en devise sont converties au taux mensuel de leur mois
(§ 16 al. 6 UStG). Gérez les taux sous « Taux de change » (à côté des règles de
comptabilisation), un par un ou ligne par ligne, par exemple à partir de la
publication du ministère des Finances. Sans taux, la pièce reste dans la boîte
de saisie avec une indication. Le taux et le montant d'origine figurent dans la
preuve de comptabilisation. Les paiements, la caisse et les immobilisations en
devise ne sont toujours pas comptabilisés ; les écarts de change au règlement
se passent à la main.

## Journal

Le **Journal** s'ouvre sous **Ventes et facturation** → **Comptabilité** →
**Journal**. Il affiche toutes les écritures préparées et comptabilisées de la
période choisie dans l'en-tête. Les entrées **Journal**, **Postes ouverts** et
**Récurrent** apparaissent dès que votre organisation tient ou a tenu la
comptabilité locale ; si un autre système tient actuellement le grand livre,
une note au-dessus de la liste le signale.

- **Liste :** **N°**, **Date de comptabilisation**, **Libellé**, **Comptes**,
  **Montant** et **Statut** (**Brouillon**, **Vérifiée**, **Comptabilisée**,
  **Extournée**). La recherche porte sur le libellé et la pièce, le filtre de
  statut affiche un seul état. WorkDiary n'attribue le numéro de journal qu'à
  la comptabilisation, de manière continue et sans trou.
- **Nouvelle écriture :** la boîte de dialogue passe un montant d'un compte au
  **Débit** vers un compte au **Crédit**. **Date de comptabilisation**,
  **Libellé**, les deux comptes et **Montant** sont obligatoires ; **Date de la
  pièce**, **Pièce** et – si des centres de coûts existent – un **Centre de
  coûts** pour les deux lignes sont facultatifs. Seuls les comptes actifs sont
  proposés. Sans **Comptabiliser immédiatement**, un brouillon est créé.
- **Consulter une écriture :** la page de détail montre l'**En-tête de
  l'écriture** et les **Lignes de l'écriture** avec les totaux au débit et au
  crédit, ainsi que des indications sur une extourne et sur des budgets
  mensuels dépassés (sans blocage). Les brouillons et les écritures vérifiées
  se comptabilisent ici avec **Comptabiliser**.
- **Extourner :** une écriture comptabilisée se corrige avec **Extourner** : le
  **Motif** est obligatoire, la **Date de la contre-écriture** facultative.
  Laissée vide, c'est le jour d'origine qui s'applique tant que sa période est
  ouverte, sinon la date du jour. **Créer la contre-écriture** comptabilise
  l'écriture inversée et annule aussi les postes ouverts issus de l'original.
  Avec le principe des quatre yeux actif, la contre-écriture est créée comme
  brouillon : l'écriture reste comptabilisée et les postes ouverts inchangés
  jusqu'à ce qu'une deuxième personne comptabilise l'extourne. D'ici là, une
  deuxième extourne de la même écriture est impossible ; sa page de détail
  renvoie au brouillon avec **Voir le brouillon en attente**. L'extourne
  automatique lors de la suppression d'une affectation dans le rapprochement
  bancaire est toujours comptabilisée immédiatement.

À la comptabilisation, WorkDiary vérifie : une période ouverte existe pour la
date de comptabilisation et la comptabilité locale tient le grand livre ce
jour-là ; débit et crédit sont égaux ; tous les comptes sont actifs, et un
compte marqué **Centre de coûts obligatoire** a un centre de coûts. Avec le
principe des quatre yeux actif, la personne qui a créé l'écriture ne peut pas
la comptabiliser – pas même via **Comptabiliser immédiatement**.

**Autorisation :** consulter avec **Consulter la comptabilité**, saisir avec
**Préparer les écritures**, comptabiliser et extourner avec **Comptabiliser les
écritures**.

## Postes ouverts

**Ventes et facturation** → **Comptabilité** → **Postes ouverts** affiche les
créances et les dettes issues d'écritures comptabilisées qui ne sont pas encore
soldées – indépendamment de la période de l'en-tête. Un poste ouvert naît
lorsqu'une écriture est comptabilisée sur un compte portant la caractéristique
**Postes ouverts** ; les paiements le soldent via le rapprochement bancaire.

- Les onglets **Créance** et **Dette** séparent les deux sens.
- Les vignettes totalisent les montants ouverts par ancienneté depuis
  l'échéance : **Non échu**, **1–30 jours**, **31–60 jours**, **61–90 jours**
  et **plus de 90 jours**.
- La liste, triée par échéance, montre **Pièce**, **Tiers**, **Date de la
  pièce**, **Échéance** (avec la mention « en retard de … jours »),
  **Origine**, **Ouvert** et **Statut** (**Ouvert**, **Partiellement soldé**,
  **Litigieux**). **Voir l'écriture** ouvre l'écriture sous-jacente.
- **Solder** enregistre une déduction sans paiement : **Escompte**,
  **Retenue** ou **Passage en perte**, avec **Montant** et une **Note**
  facultative. Le montant ne peut pas dépasser le reste ouvert. Pour l'escompte
  et le passage en perte, WorkDiary comptabilise en même temps une
  contre-écriture au journal – sur le compte d'escompte ou de perte des
  paramètres DATEV, à condition que ce compte existe dans le plan comptable.
  Une retenue ne crée aucune écriture.
  Avec le principe des quatre yeux actif, la contre-écriture est créée comme
  brouillon dans la boîte de saisie et le poste reste ouvert jusqu'à ce
  qu'une deuxième personne la comptabilise. D'ici là, la liste affiche
  **Brouillon en attente de validation**, **Voir le brouillon en attente**
  mène à l'écriture, et tout autre règlement du poste – y compris une
  retenue – est refusé. Si le poste a été soldé autrement entre-temps, la
  comptabilisation échoue car le montant dépasse le reste ouvert. Une retenue
  et un règlement sans compte de contrepartie existant ne créent aucune
  écriture et s'appliquent immédiatement.

**Autorisation :** consulter avec **Consulter la comptabilité**, solder avec
**Comptabiliser les écritures**.

## Récurrent

Sous **Ventes et facturation** → **Comptabilité** → **Récurrent** (page
**Opérations récurrentes**), vous planifiez ce qui revient régulièrement. Il
existe deux types de modèles :

- **Attente de pièce :** pour une pièce qui doit arriver régulièrement, comme un
  loyer ou un leasing. Elle ne crée ni pièce ni écriture, mais à l'échéance une
  opération ouverte au statut **Pièce attendue** – ainsi, il reste visible que
  l'original manque encore.
- **Modèle d'écriture :** à l'échéance, il crée un brouillon d'écriture avec
  compte au débit, compte au crédit et montant attendu, daté du jour
  d'échéance. Il ne comptabilise jamais lui-même ; vous le faites à la main dans
  la boîte de saisie ou dans le journal.

La page se compose des **Opérations ouvertes** (**Modèle**, **Période**,
**Échéance**, **Attendu**, **Statut** ; pour **Bloqué**, le motif figure
dessous, pour **Brouillon créé**, **Voir l'écriture** mène au brouillon, pour
**Pièce attendue**, **Attribuer la pièce** attribue l'original), des
**Modèles** (**Libellé**, **Type**, **Rythme**, **Prochaine échéance**,
**Responsable**, **Statut** avec numéro de version) et des **Plans de
facturation** : plans de facturation actifs, pour information seulement,
modifiés via **Ouvrir les plans**.

**Créer un modèle :** **Type**, **Libellé**, **Rythme** (**Mensuel**,
**Trimestriel**, **Semestriel**, **Annuel**), **Jour d'échéance** (1–28, pour
que chaque mois comporte ce jour), **Attendu**, **Début** et, en option,
**Fin**, pour les modèles d'écriture aussi **Débit** et **Crédit**, ainsi
que **Responsable** et une **Note**. Un modèle d'écriture sans les deux
comptes et sans montant n'est pas enregistré. En modification, les comptes
enregistrés sont présélectionnés et la boîte de dialogue affiche les
prochaines échéances ; chaque changement enregistre une nouvelle version, et
les opérations déjà créées restent inchangées.

**Déroulement et règles :**

- Un traitement quotidien crée les opérations échues tant que la comptabilité
  locale tient le grand livre à la date de référence. Au plus une opération est
  créée par modèle et par période.
- **Exécuter** crée immédiatement l'opération de la prochaine échéance, sans
  attendre le traitement quotidien.
- Si un brouillon ne peut pas être créé, par exemple faute de période pour la
  date, l'opération apparaît comme **Bloqué** avec son motif.
- **Attribuer la pièce** satisfait une attente de pièce : vous choisissez la
  facture reçue dans le champ **Facture électronique entrante**. Sont
  proposées les factures de **Factures électroniques entrantes** que vous
  pouvez voir, qui ne sont pas rejetées et qui ne sont encore attribuées à
  aucune opération – une facture satisfait au plus une opération. L'opération
  passe ensuite au statut **Satisfait**.
- Lorsque le brouillon d'un modèle d'écriture est comptabilisé, son opération
  passe aussi au statut **Satisfait**.
- Si une opération au statut **Pièce attendue** ou **Brouillon créé** est en
  retard, WorkDiary le signale une seule fois via les notifications, par
  défaut à la comptabilité et à la personne indiquée sous **Responsable**.
- **Suspendre** arrête un modèle ; **Reprendre** continue avec la prochaine
  échéance à partir d'aujourd'hui, sans rattraper les échéances manquées.
  **Terminer** arrête définitivement le modèle ; les opérations déjà créées
  subsistent.

**Autorisation :** consulter avec **Consulter la comptabilité** ; créer,
modifier, suspendre, reprendre et terminer des modèles avec **Configurer la
comptabilité** ; **Exécuter** et **Attribuer la pièce** avec **Préparer les
écritures**.
