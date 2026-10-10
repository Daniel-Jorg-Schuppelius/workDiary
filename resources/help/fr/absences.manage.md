---
title: "Congés & maladie"
topic: absences.manage
version: 3
keywords:
    - absence
    - demande de congé
    - poser des congés
    - valider un congé
    - arrêt maladie
    - congé maladie
    - certificat médical
    - gestion des absences
    - remplaçant
    - maintien du salaire
    - rechute de maladie
audience: []
related:
    - planning.shifts
    - time-accounts.flex
    - reports.overview
---

Les absences influencent la planification des services, la comparaison
prévu/réalisé et la validation mensuelle. Les congés suivent un processus
de demande et d'approbation ; les arrêts maladie sont documentés avec la
période et les justificatifs requis. Les collaborateurs saisissent la
période complète, puis les responsables vérifient les chevauchements, le
remplacement et le solde restant avant d'approuver, de refuser ou de
demander une correction. Les données de santé étant particulièrement
sensibles, ne saisissez que les informations nécessaires et respectez les
règles d'accès et de conservation prévues.

## Arrêt maladie et maintien du salaire

Dans la boîte de dialogue **Saisir un arrêt maladie**, choisissez d'abord le
**Type** : **Certificat initial** pour une nouvelle incapacité de travail ou
**Certificat de prolongation** lorsqu'un certificat est prolongé – choisissez
alors l'**Arrêt maladie précédent**.

Avec le type **Certificat initial**, les administrateurs et les personnes
disposant du droit **Gérer les arrêts maladie** voient en plus le champ
**Rechute de la maladie du**. Choisissez-y l'arrêt maladie antérieur de la même
personne si la caisse d'assurance maladie confirme qu'il s'agit de la même
maladie. Les jours de maladie sont alors imputés sur le même droit au maintien
du salaire. Sans cette confirmation, **Aucune — nouvelle maladie** reste
sélectionné.

Voici comment WorkDiary calcule le maintien du salaire selon le § 3 EntgFG
(loi allemande sur le maintien du salaire) :

- Dans le réglage standard, le droit est de six semaines, soit 42 jours
  calendaires d'incapacité de travail. Seuls les jours de maladie comptent,
  jamais les jours travaillés entre deux arrêts maladie.
- Les arrêts maladie qui se chevauchent, se suivent sans interruption ou sont
  des certificats de prolongation forment un seul cas de maladie – même si une
  nouvelle maladie survient pendant une maladie en cours.
- Une nouvelle maladie qui ne commence qu'après des jours travaillés ouvre un
  droit complet.
- En cas de rechute, un nouveau droit naît pour la même maladie si la personne
  – dans le réglage standard – n'a pas été en incapacité de travail pour cette
  maladie pendant six mois, ou si douze mois se sont écoulés depuis le début de
  la première incapacité.

La liste de travail affiche la situation dans la rubrique **Maladie** sous
**Maintien du salaire**, avec les jours utilisés et restants. **Fin prévue**
n'apparaît que tant que l'arrêt maladie est en cours ; une fois le droit
consommé, la date depuis laquelle il est épuisé s'affiche à la place. Le
rapport **Maladies** montre la situation par personne. Toutes les indications
servent d'orientation et ne constituent pas un conseil juridique.

Comme les congés, les jours d’arrêt maladie n’ont pas de temps prévu : **Compte de temps de travail**, **Bilan de travail**, **Présence** et **Plan/réel** ne les comptent pas comme un déficit. Les arrêts maladie annulés ne sont comptés nulle part, pas même dans les comptes de temps.
