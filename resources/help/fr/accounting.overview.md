---
title: "Comptabilité locale"
topic: accounting.overview
version: 3
keywords:
    - grand livre
    - tenue de comptabilité
    - comptabilité générale
    - configurer la comptabilité
    - comptabilité en partie double
    - comptabilité de trésorerie
    - date de début comptable
    - remplacer logiciel comptable
    - comptabilité intégrée
    - plan comptable
    - SKR03
    - SKR04
audience:
    - admin
    - geschaeftsfuehrung
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

1. Ouvrir **Ventes et facturation** → **Comptabilité** → **Configuration**
   et choisir le profil.
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

## Plan comptable

Les comptes de la comptabilité locale se gèrent sous **Ventes et facturation**
→ **Comptabilité** → **Plan comptable**. L'entrée apparaît dès que votre
organisation tient ou a tenu la comptabilité locale.

- **Plan comptable depuis un modèle :** choisissez sous **Modèle** un extrait
  du SKR03 ou du SKR04 et cliquez sur **Appliquer le modèle**. Comptes, codes
  de taxe et règles de comptabilisation correspondantes sont créés, de sorte
  que la boîte de saisie est immédiatement utilisable ; les comptes et règles
  existants restent inchangés. Le modèle est un point de départ pour
  l'Allemagne – le choix des comptes et la correspondance fiscale doivent être
  validés avant la première écriture.
- **Créer un compte** et **Modifier le compte :** **Compte** (le numéro de
  compte, unique par organisation), **Libellé**, **Type de compte**, **Sens du
  solde** (prérempli selon le type de compte), **Compte DATEV** (uniquement pour
  l'export), les caractéristiques **Postes ouverts**, **Banque**, **Caisse**,
  **Attente** et **Centre de coûts obligatoire**, pour la comptabilité de
  trésorerie la **Ligne recettes-dépenses** et la **Part déductible (%)**,
  ainsi qu'une **Description**. Les écritures sur des comptes portant la
  caractéristique **Postes ouverts** apparaissent dans la liste des postes
  ouverts.
- **Désactiver** au lieu de supprimer : un compte désactivé conserve ses
  écritures, mais n'est plus proposé pour de nouvelles. La liste affiche par
  défaut les comptes **actifs uniquement** ; la recherche (numéro, libellé) et
  le filtre par type de compte affinent encore.
- **Importer le plan comptable :** un fichier CSV avec ligne d'en-tête et les
  colonnes `number`, `name` et `type`, en option `normal_balance`,
  `is_open_item`, `datev_account`, `euer_category` et `deductible_percent`. Les
  numéros existants sont mis à jour, les nouveaux comptes créés, rien n'est
  supprimé ; les lignes erronées sont ignorées et comptées.
- **Codes de taxe :** dès que des codes de taxe existent, la page les liste avec
  leurs cases de la déclaration de TVA allemande. Via **Modifier**, vous
  attribuez une case à **Base imposable** et une à **Montant de taxe** – une
  aide au rapprochement, pas le formulaire.

**Autorisation :** consulter avec **Consulter la comptabilité** ; modèle,
import et toute modification des comptes et codes de taxe avec **Configurer la
comptabilité**.
