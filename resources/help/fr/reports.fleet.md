---
title: "Parc automobile, carnet de bord et temps de conduite"
topic: reports.fleet
version: 3
keywords:
    - analyse des véhicules
    - kilométrage
    - frais de carburant
    - coût au kilomètre
    - carnet de bord fiscal
    - trajets privés
    - avantage en nature
    - règle du 1 pour cent
    - voiture de fonction
    - temps de conduite
    - temps de repos
    - pause de conduite
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - assets.fleet
    - travel-expenses.manage
    - fleet.license-checks
    - reports.arbzg-compliance
    - admin.organization-settings
    - reports.overview
---

Ces rapports concernent les véhicules et les trajets : kilomètres et coûts
énergétiques par véhicule, le carnet de bord fiscal d’un véhicule, la
comparaison entre la méthode du carnet de bord et la règle du 1 % ainsi que
le justificatif des temps de conduite et de repos. Ils reposent sur les
trajets du **Carnet de bord** (**Déplacements et frais** → **Carnet de
bord**), les justificatifs du **Journal carburant et recharge** (**Parc
automobile** → **Journal carburant et recharge**) et les données des
véhicules sous **Parc automobile** → **Véhicules**.

## Période et export

- Vous choisissez la période avec le sélecteur de période dans l’en-tête. La
  comparaison 1 % calcule en revanche sur une année civile.
- **PDF** télécharge une version imprimable ; **CSV** et **Excel** se trouvent
  sous **Export**. Les exports reprennent les filtres choisis ; chaque export
  est consigné dans le journal d’audit.

## Parc automobile

**Rapports** → **Ressources** → **Parc automobile** ouvre le **Rapport du parc
automobile** avec les trajets, pleins, coûts énergétiques et remboursements
par véhicule.

- Tuiles : **Véhicules**, **Σ km** (avec le nombre de trajets), **Pleins /
  recharges** (avec les litres et les kWh), **Coûts énergétiques** (avec le
  total des remboursements) et **Ø €/km**.
- Graphiques : **Kilomètres par véhicule (top 15)** et les kilomètres dans le
  temps.
- Tableau par véhicule : **Véhicule**, **Motorisation**, **Trajets**, **km**,
  **Remboursement**, **Pleins**, **Litres**, **kWh**, **Coûts énergétiques**,
  **€/km** et **Kilométrage**, avec une ligne de total.

Voici comment les valeurs sont obtenues :

- **Trajets**, **km** et **Remboursement** proviennent des trajets du carnet
  de bord auxquels un véhicule est attribué et dont la date se situe dans la
  période.
- **Pleins**, **Litres**, **kWh** et **Coûts énergétiques** proviennent des
  justificatifs de carburant et de recharge commencés dans la période.
- **€/km** divise les coûts énergétiques par les kilomètres ; sans kilomètres
  ou sans coûts, le champ reste vide.
- **Kilométrage** est le dernier relevé issu des justificatifs de carburant et
  de recharge de la période, sinon le relevé enregistré pour le véhicule.

Filtres : **Zone** (**Uniquement mes trajets** ou **Tout le parc
automobile**, uniquement pour les administrateurs) et **Employé**. Tous les
autres ne voient que leurs propres trajets et justificatifs. Export en PDF,
CSV et Excel.

## Justificatif carnet de bord

**Rapports** → **Ressources** → **Justificatif carnet de bord** montre le
carnet de bord fiscal d’un véhicule : relevés kilométriques, type de trajet,
destination, objet et conducteur, totaux par type de trajet et part privée.

- Choisissez le **Véhicule**. Les véhicules en **Mode carnet de bord**
  figurent en tête et sont signalés comme tels. Sans droits
  d’administrateur, la liste contient les véhicules sans **Conducteur par
  défaut** et ceux dont vous êtes le conducteur par défaut ; les
  administrateurs voient tous les véhicules.
- Si le véhicule n’est pas en mode carnet de bord, un avertissement rappelle
  que des trajets sans relevés kilométriques et sans verrouillage ne
  constituent pas un carnet de bord au sens fiscal. Vous activez le mode sur
  le véhicule avec **Mode carnet de bord (fiscal)**.
- Tuiles : **Trajets** (avec le nombre de trajets verrouillés), **Σ km**, les
  kilomètres par type de trajet (**Professionnel**, **Domicile–travail**,
  **Privé**) et **Part privée** (kilomètres privés rapportés à l’ensemble des
  kilomètres).
- Tableau : **Date**, **Km départ**, **Km fin**, **km**, **Type de trajet**,
  **Destination**, **Objet**, **Conducteur** et **Statut** (**verrouillé**,
  **ouvert** ou **contrepassé**, ainsi que **signé** et **Trajet
  d’annulation**). Le pied de tableau indique les kilomètres par type de
  trajet.

Les kilomètres d’un trajet résultent du kilométrage de fin moins celui de
départ ; si les relevés manquent, c’est la distance saisie qui compte, en
double pour un aller-retour. La liste contient tous les trajets du véhicule
dans la période, y compris ceux d’autres conducteurs. Les trajets d’origine
contrepassés restent visibles barrés, mais ne comptent dans aucun total.

Export en PDF, CSV et Excel dès qu’un véhicule est choisi. CSV et Excel
contiennent en plus l’adresse de départ, les horodatages du verrouillage et
de la signature, l’indicateur d’annulation, le trajet corrigé avec le motif
de correction ainsi que les totaux et la part privée.

## Comparaison 1 %

Vous ouvrez la **Comparaison 1 %** avec le bouton du même nom sur la page
**Justificatif carnet de bord** ; elle n’a pas d’entrée de menu propre. Elle
oppose pour chaque véhicule l’avantage en nature selon la méthode du carnet
de bord à la règle du 1 %. Il s’agit d’un calcul simplifié et non d’un
conseil fiscal.

- **Année** : l’année en cours et les six années précédentes ; l’année
  précédente est présélectionnée.
- Seuls les véhicules en mode carnet de bord sont listés, avec la même
  sélection de véhicules que pour le justificatif carnet de bord.
- **mois** : mois comportant des trajets. **km au total**, **dont privés** et
  **dont domicile–travail** ne comptent que les trajets avec kilométrage de
  départ et de fin ; les trajets d’origine contrepassés ne comptent pas.
- **Coûts totaux** : coûts énergétiques issus des justificatifs de carburant
  et de recharge de l’année plus les autres coûts annuels ; l’infobulle
  montre les deux parts.
- **Méthode du carnet de bord** : coûts totaux multipliés par la part des
  kilomètres privés et domicile–travail dans l’ensemble des kilomètres.
- **Règle du 1 %** : **Prix catalogue brut (€)**, arrondi à la centaine
  d’euros inférieure, dont 1 % par mois d’utilisation plus 0,03 % par
  kilomètre de **Distance domicile–travail (km)** et par mois. Pour les
  véhicules électriques et les hybrides éligibles acquis à partir de 2019, la
  base est réduite à un quart ou à la moitié selon la **Date d’acquisition**
  et le prix catalogue ; la cellule affiche alors **Base** avec
  0,25 % ou 0,5 % au lieu de 1 %. Sans prix catalogue, elle affiche **Prix catalogue manquant**.
- **Plus avantageux** signale la méthode donnant la valeur la plus faible.

Le symbole euro ouvre la boîte de dialogue **Autres coûts annuels** pour le
leasing, l’assurance, la taxe sur les véhicules, l’entretien, les
réparations et l’amortissement, avec **Montant (€)** et **Remarque**. Il faut
pour cela le droit **Gérer les véhicules**. La page ne propose pas d’export.

## Justificatif temps de conduite

Le **Justificatif temps de conduite** est un téléchargement des temps de
conduite et de repos par conducteur et par jour civil. Vous le trouvez sur la
page **Conformité du temps de travail** (**Rapports** → **Finances et
audit**) dans le menu **Export**. Il n’apparaît que si les règles de temps de
conduite sont activées dans les paramètres de conformité de l’organisation
sous **Temps de conduite et de repos**, et il exige le droit **Consulter la
conformité du temps de travail**.

- Il analyse les trajets effectifs avec heure de départ et d’arrivée sur les
  véhicules pour lesquels **Appliquer les règles de temps de conduite et de
  repos** est coché. Les données du tachygraphe ne sont pas lues.
- Colonnes : **Conducteur**, **Matricule**, **Date**, **Véhicules**,
  **Premier départ**, **Dernière arrivée**, **Temps de conduite**, **Plus
  longue période de conduite sans pause**, **Pauses (min)**, **Repos
  précédent** et les **Constats** du jour.
- Le téléchargement reprend la période et les filtres d’employé et d’équipe
  de la page. **Justificatif temps de conduite** fournit un fichier CSV,
  **Justificatif temps de conduite (PDF)** les mêmes données en PDF au format
  paysage. Les deux sont consignés dans le journal d’audit.

Les constats reposent sur les limites du règlement (CE) 561/2006 et de la
FPersV : au maximum 9 h de conduite par jour (10 h deux fois par semaine),
56 h par semaine et 90 h sur deux semaines, une pause de 45 minutes après
4,5 h (fractionnable en 15 et 30 minutes), 11 h de repos journalier (au
maximum trois fois par semaine 9 h) et 45 h de repos hebdomadaire (24 h avec
compensation). Ceci n’est pas un conseil juridique ; il appartient à
l’entreprise de déterminer les règles applicables dans chaque cas.
