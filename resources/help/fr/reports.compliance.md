---
title: "Justificatifs : activité d’audit, conformité et salaire minimum"
topic: reports.compliance
version: 1
keywords:
    - analyse d’audit
    - qui a modifié quoi
    - tracer les exports
    - aperçu de conformité
    - infractions au temps de travail
    - acquitter des infractions
    - accepter une infraction
    - cas non clarifiés
    - justificatif salaire minimum
    - contrôle douanier
    - relevé du temps de travail
    - obligation d’enregistrement
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
related:
    - reports.arbzg-compliance
    - audit.log
    - corrections.requests
    - attendance.manage
    - reports.fleet
    - admin.organization-settings
---

Ces pages servent de justificatifs auprès des contrôleurs et des autorités :
qui a fait quoi dans le système, où en sont les infractions à la loi
allemande sur le temps de travail (ArbZG) et comment elles ont été traitées,
ainsi que le relevé du temps de travail selon la loi sur le salaire minimum
pour la douane. Les règles contrôlées par la conformité du temps de travail
et la liste détaillée sont décrites dans le sujet consacré à la conformité du
temps de travail.

## Période et export

- Vous choisissez la période avec le sélecteur de période dans l’en-tête.
  L’**Historique des infractions** affiche en revanche toutes les infractions
  enregistrées.
- Lorsqu’un export existe, il est mentionné dans la section concernée. Les
  exports PDF et CSV sont consignés dans le journal d’audit.

## Activité d’audit

**Rapports** → **Finances et audit** → **Activité d’audit** résume les
entrées du journal d’audit de la période. La page ne s’ouvre que pour les
administrateurs ; l’accès est refusé à tous les autres.

- Tuiles : **Events Σ** (toutes les entrées de la période), **Utilisateurs
  actifs** et **Types d’entités**. Les deux dernières comptent les entrées des
  listes top 20 et affichent donc au maximum 20.
- Graphiques : **Événements dans le temps**, **Principaux acteurs (top 15)**
  et les événements dans le temps par type d’événement.
- Tableaux : **Par événement**, **Par type d’entité (top 20)**, **Par
  utilisateur (top 20)** et **100 derniers événements** avec **Moment**,
  **Utilisateur**, **Event**, **Type**, **ID** et **IP**. Les événements et
  types apparaissent avec leur nom lisible lorsqu’il en existe un.
- Filtre : **Employé**.

L’export de rapports crée lui aussi une entrée avec le rapport, le format et
les filtres ; on peut ainsi retracer qui a téléchargé quel rapport. Les
entrées individuelles avec tous les détails figurent dans le **Journal
d’audit**. Export en PDF, CSV et Excel.

## Tableau de bord de conformité

Vous ouvrez le **Tableau de bord de conformité** par l’onglet **Tableau de
bord** de la page **Conformité du temps de travail** (**Rapports** →
**Finances et audit** → **Conformité du temps de travail**). Les onglets
**Tableau de bord**, **Rapport détaillé** et **Historique des infractions**
relient les trois vues. Le droit **Consulter la conformité du temps de
travail** est nécessaire.

Le tableau de bord détermine les constats de la période à partir des temps
de travail saisis, de la même manière que le rapport détaillé ; si les
règles de temps de conduite sont activées, les constats de temps de conduite
et de repos s’y ajoutent.

- Tuiles : **Total des constats** (un clic ouvre le rapport détaillé),
  **Collaborateurs concernés**, **Ouvert (sans correction)** et **Avec
  correction approuvée** (constats des jours avec une correction de temps
  approuvée).
- Une tuile par type d’infraction avec le nombre ; un clic ouvre le rapport
  détaillé filtré sur ce type.
- Graphiques : constats ouverts dans le temps et constats dans le temps par
  type d’infraction.
- **Infractions par règle et par mois** : pour chaque mois, les constats de
  chaque type d’infraction avec **Total**.
- **Constats par équipe** : volontairement par équipe et non par personne.
  Une personne appartenant à plusieurs équipes compte dans chacune ; les
  personnes sans équipe figurent sous **Sans équipe**.

Filtre : **Équipe**. Le tableau de bord ne propose pas d’export.

## Historique des infractions

L’onglet **Historique des infractions** ouvre la page **Infractions de
conformité** avec les infractions enregistrées et leur état de traitement.
Un rapprochement régulier, par défaut une fois par jour pendant la nuit,
enregistre les nouveaux constats. Si un constat n’est plus détecté lors de ce
rapprochement, il passe à **Résolu** ; s’il réapparaît, il repasse à
**Ouvert**.

- Tuiles par statut : **Ouvert**, **Acquitté**, **Résolu** et **Accepté**,
  comptées sur toute l’organisation. Un clic filtre la liste.
- Graphique **Constats nouveaux vs acquittés par mois** pour les 24 derniers
  mois comportant des données.
- Liste : **Employé**, **Date**, **Type**, **Valeur**, **Seuil**, **Gravité**
  et **Statut** ; pour les infractions traitées, le nom, la date et le motif
  apparaissent en dessous.
- Filtres : **Employé**, **Équipe**, **Statut** et **Catégorie** (**ArbZG**,
  **Cas non clarifiés**, **Temps de conduite**).

Voici comment traiter une infraction au statut **Ouvert** ou **Acquitté** :

1. Si nécessaire, saisissez un motif dans le champ **Motif (obligatoire pour
   « accepté »)**.
2. Choisissez **Acquitter** pour prendre acte de l’infraction, ou
   **Accepter** pour la tolérer en connaissance de cause. Le motif est
   obligatoire pour **Accepter**.

Chaque changement de statut est consigné dans le journal d’audit. Pour les
**Cas non clarifiés**, une icône supplémentaire ouvre une **Demande de
correction** pour le jour du constat. La liste n’est pas limitée à la
période et affiche 50 entrées par page. La page ne propose pas d’export.

## Justificatif MiLoG (douane)

Vous téléchargez le **Justificatif MiLoG (douane)** sur la page **Conformité
du temps de travail** dans le menu **Export**. Il sert d’enregistrement au
sens de l’article 17, alinéa 1, de la loi allemande sur le salaire minimum
(MiLoG) et exige le droit **Consulter la conformité du temps de travail**.

- Le fichier CSV contient, par salarié et par jour civil, **Salarié**,
  **Matricule**, **Date**, **Début**, **Fin**, **Pauses (min)** et **Durée**.
- Il repose sur les pointages terminés ; les pointages annulés et encore
  ouverts ne comptent pas. **Début** est le premier début et **Fin** la
  dernière fin de la journée, les pauses sont additionnées, et **Durée** est
  le temps de travail après déduction des pauses.
- Le tri se fait par nom et par date. Le téléchargement reprend la période et
  le filtre d’employé de la page et est consigné dans le journal d’audit.

Le **Justificatif temps de conduite** du même menu est décrit dans le sujet
consacré au parc automobile, au carnet de bord et aux temps de conduite.
