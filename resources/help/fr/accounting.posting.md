---
title: "Comptabiliser et boîte de saisie"
topic: accounting.posting
version: 10
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

**Rejeter le brouillon.** Tout brouillon – issu de l'une de ces opérations,
d'une proposition de la boîte de saisie, d'un modèle d'écriture ou saisi à la
main – se supprime avec **Rejeter le brouillon** dans la boîte de saisie ou sur
sa page de détail (avec confirmation). Les brouillons issus des opérations
citées sont rejetés par qui peut **Comptabiliser les écritures** ; pour tous
les autres, **Préparer les écritures** suffit. L'étape est journalisée et
l'opération est de nouveau ouverte : le poste peut de nouveau être soldé,
l'opération bancaire, les soldes initiaux et l'acompte spécial peuvent être
comptabilisés à nouveau, l'écriture d'une extourne rejetée peut de nouveau être
extournée, un virement interne disparaît avec le
lien de ses pièces, un mouvement bancaire mis de côté par **Comptabiliser sur un
compte général** est de nouveau ouvert, et la
pièce d'une proposition réapparaît non comptabilisée dans la boîte. L'opération
d'un modèle d'écriture est considérée comme **Ignorée** ; la période suivante
se déroule normalement. Les écritures comptabilisées ne peuvent pas être
rejetées.

**Modifier le brouillon.** Tant qu'une écriture n'est pas comptabilisée, vous
la modifiez sur sa page de détail avec **Modifier le brouillon** : dates
d'écriture et de pièce, libellé, pièce, contrepartie et les lignes avec compte,
montant, code de taxe, centre de coûts et libellé de ligne. Cela vaut pour les
écritures manuelles, les brouillons issus de modèles et ceux issus de
propositions. La personne qui modifie devient le préparateur – avec le principe
des quatre yeux, une autre personne comptabilise. La modification est
journalisée. Les brouillons issus d'un escompte, d'un passage en perte, d'une
écriture d'attente, d'un virement interne, d'un mouvement bancaire sans pièce,
des soldes initiaux, de l'acompte spécial et d'une extourne suivent leur
opération et ne peuvent pas être réécrits ; sur tout brouillon, **Centres de
coûts** permet toutefois de compléter le centre de coûts par ligne – par
exemple lorsqu'un compte avec **Centre de coûts obligatoire** empêche la
comptabilisation. Les écritures comptabilisées restent inchangées.

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

**Factures entrantes par taux.** Si une facture entrante comporte plusieurs
taux – dans la ventilation de la facture électronique ou en lignes dans la
boîte de dialogue **Saisir les valeurs** –, la proposition comptabilise charge
et TVA déductible par taux, chacune avec le code de TVA déductible de ce taux.
La case 66 et l’escompte par taux restent ainsi exacts. Si la ventilation ne
donne pas le montant brut, une seule ligne issue de l’en-tête est conservée.

**Autoliquidation par le destinataire.** Si les factures entrantes indiquent
comme **cas fiscal** le § 13b UStG ou une acquisition intracommunautaire, la
facture est hors taxe. La proposition comptabilise la charge contre la dette,
plus la TVA autoliquidée au crédit et la même TVA déductible au débit – avec le
code de ce cas fiscal : cases 46/47 pour les prestations d’un autre pays de
l’UE, 84/85 pour les autres cas du § 13b, 89 ou 93 pour l’acquisition, la TVA
déductible en 67 ou 61. Le taux est celui de la pièce, à 0 % le taux normal.
Si la pièce indique malgré tout une taxe ou si le code manque, elle reste
bloquée avec un message. Un escompte sur une telle facture est hors taxe : il
réduit la base d’imposition, la TVA collectée et la TVA déductible baissent du
même montant (§ 17, al. 1, phrase 5 UStG) ; si le compte d’escompte est un
compte automatique DATEV, l’escompte réduit le compte de charge de la pièce.
Les factures entrantes comptabilisées dont la pièce indique une
autoliquidation mais dont l’écriture ne porte pas de taxe figurent dans le
rapport **Qualité comptable** ; rien n’est comptabilisé automatiquement.

**Factures sortantes selon le § 13b.** Si un client national est enregistré
comme destinataire selon le § 13b (p. ex. travaux de construction pour des
entreprises du bâtiment), la facture part sans TVA avec la mention
« Autoliquidation par le destinataire ». L’écriture porte le code de la case
60, avec le modèle de plan comptable sur le compte de produits 8337 (SKR03) ou
4337 (SKR04) ; les livraisons intracommunautaires vont sur 8125 ou 4125.

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
- **Saisir une écriture :** le dialogue comptabilise autant de lignes que
  nécessaire. Sont obligatoires la **Date d'écriture**, le **Libellé** et au
  moins deux lignes avec **Compte** (numéro de compte, avec liste de
  suggestions) et un montant au **Débit** ou au **Crédit** ; **Date de
  pièce**, **Pièce**, **Contrepartie**, **Code de taxe**, **Centre de coûts**
  et **Libellé de ligne** sont facultatifs. Les lignes vides sont ignorées.
  Avec un **Code de taxe**, vous saisissez le montant hors taxe ; la ligne de
  taxe sur le compte de taxe du code est créée à l'enregistrement, et
  l'écriture apparaît dans les cases de la déclaration. La **Contrepartie**
  (client ou fournisseur) s'applique aux lignes sur des comptes à postes
  ouverts et aux lignes avec code de taxe – le poste ouvert porte ainsi le
  client. Seuls les comptes actifs et les codes de taxe valables à la date
  d'écriture sont proposés. Sans **Comptabiliser immédiatement**, un brouillon
  est créé.
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
- **Solder** saisit une déduction sans paiement : **Escompte**, **Retenue** ou
  **Passage en perte**, avec **Montant** et **Note** facultative. Le montant
  ne doit pas dépasser le reste ouvert. Pour l'escompte et le passage en
  perte, WorkDiary comptabilise en même temps une contre-écriture au journal –
  sur le **compte d'escompte** ou le **compte de passage en perte** des
  paramètres DATEV (vide = valeur par défaut du plan comptable : 8736 et 2400 en SKR03, 4736 et
  6930 en SKR04). Une dette utilise ses propres comptes : escomptes obtenus
  (3736 ou 5736) et, pour l'annulation, un autre produit (2709 ou 4839).
  L'escompte réduit aussi la taxe (§ 17 UStG) : WorkDiary répartit le montant
  selon le taux de la pièce entre net (compte d'escompte, avec code de taxe)
  et part de taxe (compte de TVA collectée ou déductible) – au prorata s'il y
  a plusieurs taux, chacun sur le compte d'escompte de son taux tant qu'aucun
  compte propre n'est paramétré. Les cases de la déclaration et l'aperçu de
  TVA montrent la réduction. Si l'écriture du poste ne porte aucune taxe
  (petite entreprise, exonéré, solde d'ouverture), l'escompte
  reste entier ; le dialogue indique ce qui s'applique. Il en va de même pour
  l'escompte issu du rapprochement bancaire. Les escomptes comptabilisés
  auparavant sans correction de taxe sont cités dans le rapport **Qualité des
  écritures** avec leur part de taxe calculée ; rien n'est comptabilisé
  automatiquement. Si ce compte manque dans le plan
  comptable, le lettrage est refusé avec un message et le poste reste ouvert ;
  le dialogue avertit au préalable. Une retenue ne crée aucune écriture. Avec
  le principe des quatre yeux, la contre-écriture est créée comme brouillon
  dans la boîte de saisie, et le poste reste ouvert jusqu'à ce qu'une seconde
  personne la comptabilise. D'ici là, la liste affiche **Brouillon en attente
  de validation**, **Afficher le brouillon en attente** mène à l'écriture, et
  tout autre lettrage du poste – même une retenue – est refusé. Si le poste
  est soldé autrement avant la validation, la comptabilisation échoue car le
  montant dépasse le reste ouvert. Les lettrages par escompte ou passage en
  perte antérieurs, clos sans écriture, sont cités dans le rapport **Qualité
  des écritures** ; ils doivent être comptabilisés manuellement.
- **Marquer comme litigieux** signale un poste ouvert comme **Litigieux**,
  avec motif obligatoire. Il reste ouvert et peut être soldé, même après un
  lettrage partiel. Sur la facture de vente, WorkDiary pose un blocage de
  relance s'il n'en existe pas encore – le poste n'est relancé ni
  individuellement ni dans le cycle de relance – et, dans l'aperçu et la
  prévision de trésorerie, une créance litigieuse ne compte pas comme encaissement attendu. La facture
  fournisseur d'une dette litigieuse est bloquée dans la proposition de
  paiement (**Poste ouvert litigieux**) jusqu'à la levée de la mention. **Lever le litige** supprime la mention et le blocage
  de relance qu'elle a créé ; un blocage posé manuellement demeure. Le filtre
  au-dessus de la liste affiche **Ouvert**, **Partiellement soldé** ou
  **Litigieux**. Les deux étapes figurent dans le journal des modifications.

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
  les lignes du modèle, daté du jour d'échéance. Il ne comptabilise jamais lui-même ; vous le faites à la main dans
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
**Fin**, pour les modèles d'écriture aussi les lignes d'écriture, ainsi
que **Responsable** et une **Note**. Un modèle d'écriture sans au moins deux
lignes avec compte et montant n'est pas enregistré. En modification, les lignes
enregistrées sont préremplies et la boîte de dialogue affiche les
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

**Lignes du modèle.** Un modèle d'écriture accepte les mêmes lignes que
l'écriture manuelle : plusieurs lignes, **Code de taxe**, **Centre de coûts**
et **Contrepartie**. Débit et crédit doivent être équilibrés, sinon le modèle
n'est pas enregistré. Si **Attendu** reste vide, le total de l'écriture
s'applique. Si le taux d'un code de taxe change, enregistrez de nouveau le
modèle. Si vous rejetez le brouillon d'un modèle, son opération est considérée
comme **Ignorée**.

**Autorisation :** consulter avec **Consulter la comptabilité** ; créer,
modifier, suspendre, reprendre et terminer des modèles avec **Configurer la
comptabilité** ; **Exécuter** et **Attribuer la pièce** avec **Préparer les
écritures**.

## Ventes sans taxe et dépenses

Les modèles de plan comptable ont leurs propres comptes de produits pour les codes
RC21 (case 21), AUSF (case 43) et NSTB (case 45) ; les plans issus du modèle les
reçoivent avec la mise à jour. Les **dépenses** comptabilisent la TVA déductible au
taux du justificatif. S'il n'existe pas de code de TVA déductible pour ce taux —
généralement de la TVA étrangère, non déductible —, la proposition s'arrête :
saisissez la dépense en brut sans taux. Les factures en autoliquidation relèvent des
factures entrantes, qui connaissent le régime fiscal.
