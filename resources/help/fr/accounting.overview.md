---
title: "Comptabilité locale"
topic: accounting.overview
version: 2
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Objectif et contexte

La comptabilité locale tient un grand livre propre dans WorkDiary —
pour les organisations sans logiciel comptable séparé. Elle ne
remplace ni les plugins comptables ni leur souveraineté sur les
données. Trois questions restent strictement séparées : la
**souveraineté de facturation** (qui émet les factures ?), la
**souveraineté des données de base** (qui tient clients et
fournisseurs ?) et la **souveraineté d'écriture** (qui tient le grand
livre ?) — par période, c'est WorkDiary ou exactement un système
externe.

## Prérequis

- Le rôle **comptabilité** ou l'administration.
- Le choix d'un profil : comptabilité de trésorerie (EÜR) ou partie
  double.
- Devise de base, exercice et début des écritures (date pivot).
- Aucun système externe avec souveraineté d'écriture sur la même
  période.

## Déroulement recommandé

1. Ouvrir **Finances → Configurer la comptabilité** et choisir le
   profil.
2. Définir devise de base, exercice et début des écritures.
3. Dérouler le **préflight** : il vérifie que l'organisation peut
   écrire sans lacune à partir de la date pivot.
4. N'**activer** la comptabilité locale que lorsqu'aucun point n'est
   plus rouge.
5. Les écritures passent ensuite par le journal (voir « Écritures »),
   la clôture par la page de clôture.

![Configuration de la comptabilité locale avec choix de profil et préflight](media/buchhaltung/buchhaltung-einrichtung.png)
*La configuration : profil comptable à gauche, préflight à droite — activation seulement sans points rouges.*

## Exemple pratique

Un petit artisan résilie son logiciel comptable au changement
d'année : en décembre, le profil EÜR est configuré, le préflight
déroulé et le début des écritures fixé au 1er janvier. Les pièces de
décembre restent dans l'ancien système — à partir de janvier,
WorkDiary écrit.

## Erreurs fréquentes

- **Vouloir écrire rétroactivement :** les pièces antérieures à la
  date pivot restent de l'historique et ne sont pas reprises.
- **Double souveraineté d'écriture :** écrire en parallèle dans
  l'ancien système et WorkDiary crée deux vérités — le préflight
  l'empêche volontairement.
- **Forcer l'activation malgré des points rouges** — les lacunes vous
  rattrapent à la première clôture.

## Effets et prochaines étapes

Avec l'activation, WorkDiary devient le grand livre directeur à
partir de la date pivot : journal, postes ouverts et clôture s'y
appuient. Ensuite : découvrir la logique d'écriture et l'entrée des
pièces (« Écritures ») et planifier la première clôture mensuelle.
