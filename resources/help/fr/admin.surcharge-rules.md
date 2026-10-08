---
title: "Règles de majoration"
topic: admin.surcharge-rules
version: 3
keywords:
    - majoration de nuit
    - prime de nuit
    - majoration du dimanche
    - majoration jours fériés
    - prime de week-end
    - prime de poste
    - code de paie
    - export vers la paie
    - DATEV
    - Lexware
    - heures de nuit
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Les règles de majoration définissent les majorations de nuit, de
week-end, de jours fériés et de plages horaires personnalisées, ainsi
que les majorations pour astreinte sur site, astreinte téléphonique et
heures supplémentaires. Lors de l'export des temps, les temps sont
évalués en conséquence et présentés sur des lignes distinctes pour
chaque rubrique de paie.

Déroulement type :

1. **Créer** ouvre la boîte de dialogue **Créer une règle de
   majoration**. Sous **Données de base**, saisissez le **Code**
   (unique, par ex. « night »), le **Libellé** (par ex. « Majoration de
   nuit »), le **Type** et la **Majoration (%)** (0–999,99).
2. Choisir le **Type** : **Nuit** (plage horaire, y compris au-delà de
   minuit, par ex. 22:00–06:00), **Samedi**, **Dimanche**, **Jour
   férié** (jours fériés légaux automatiquement), **Personnalisé**
   (plage horaire libre), **Astreinte sur site**, **Astreinte
   téléphonique** ou **Heures supplémentaires**. Pour Nuit et
   Personnalisé, vous définissez la **Plage horaire** avec **Plage de**
   et **Plage à**.
3. Sous **Transfert de paie**, renseigner en option la **Rubrique de
   paie** pour DATEV/Lexware (par ex. « 2010 ») et la **Priorité**. Avec
   **Exonéré jusqu'à (%)** et **Type de salaire part imposable**, vous
   répartissez une majoration dépassant la limite exonérée sur deux
   rubriques.
4. Sous **Validité**, définir en option **Valable à partir
   du**/**Valable jusqu'au** et activer **La règle est active** ; sous
   **Conditions**, vous limitez la règle à des **Équipes**, des
   **Sites** ou des **Types de poste**.

Règles importantes :

- En cas de règles qui se chevauchent parmi les types Nuit, Samedi,
  Dimanche, Jour férié et Personnalisé, c'est le **pourcentage le plus
  élevé** qui l'emporte – les majorations ne s'additionnent pas. En cas
  d'égalité, la priorité tranche.
- **Astreinte sur site** évalue les heures des astreintes saisies,
  **Astreinte téléphonique** les saisies de temps du type Astreinte et
  **Heures supplémentaires** les heures au-delà de l'objectif mensuel.
  Ces trois types n'ont pas besoin de plage horaire, ne se combinent
  pas avec les autres majorations et apparaissent sur des lignes
  distinctes. L'export n'évalue actuellement ni la validité ni les
  conditions pour eux.
- Les conditions restreignent une règle : vide = s'applique à tous ;
  plusieurs conditions sont reliées par un ET, et au sein d'une liste
  une seule correspondance suffit. Le site est reconnu grâce aux
  pointages sur terminal — sans contexte déterminable, une règle
  conditionnelle ne s'applique pas. Les sites peuvent avoir leur propre
  région de jours fériés (majoration de jour férié sur le lieu
  d'intervention).
- Les modifications s'appliquent aux **exports futurs** ; les exports
  déjà générés restent inchangés (correction par un nouvel export).
  Seul un recalcul audité effectué par l'exploitation réévalue les
  périodes passées — jamais une modification silencieuse des règles.

Autorisations : **Voir les règles de majoration** affiche la liste ;
seules les personnes disposant du droit **Gérer les règles de
majoration** peuvent créer, modifier et supprimer des règles.
