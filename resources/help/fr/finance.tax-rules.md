---
title: "Matrice des règles fiscales"
topic: finance.tax-rules
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
---

La matrice des règles fiscales est le catalogue versionné à partir duquel
la facturation locale détermine ses taux de taxe. WorkDiary fournit un
catalogue de base ; les lignes propres de l'organisation le surchargent —
le catalogue livré lui-même reste inchangé.

**Structure :** Chaque règle vaut pour un pays (avec une région en
option), une catégorie (services, goods, shipping, materials, expenses,
construction, media, other) et un type de taux (standard, reduced, zero,
exempt, reverse_charge, export) — avec pourcentage, dates de validité
(de/à), indication de la source et note.

**Logique de date de référence :** C'est la date de prestation qui fait
foi, pas la date de facture. Est appliquée la règle active la plus
récente valable à la date de référence ; les lignes de l'organisation
priment sur le catalogue. S'il n'existe rien de spécifique pour une
catégorie, la catégorie services s'applique en repli.

**Avertissements :** À la création et à l'import, le contrôle de
chevauchement empêche que deux règles actives du même périmètre se
chevauchent dans le temps. La vue d'ensemble avertit en outre des
lacunes dans les chaînes de règles actives — des périodes pour lesquelles
aucune règle ne s'applique.

**Import CSV :** Fichier séparé par des points-virgules avec les colonnes
country, category, rate_type, rate, valid_from, valid_to, source, note
(ligne d'en-tête autorisée). Les lignes avec une catégorie ou un type de
taux inconnu, ou avec un chevauchement, sont signalées et ignorées ; le
reste est importé.

**Désactiver plutôt que supprimer :** Les règles ne sont jamais
supprimées, mais désactivées — ensuite, le catalogue ou les règles plus
anciennes s'appliquent à nouveau. La création et la désactivation sont
auditées ; seules les lignes propres de l'organisation peuvent être
désactivées.

**Gel à l'émission :** À l'émission d'une facture, le contexte fiscal
réellement utilisé (taux, source de la règle, date de référence,
catégorie, ventilation de la taxe) est figé sur la pièce. Les
modifications ultérieures de règles n'agissent ainsi que sur les
nouvelles pièces, jamais sur celles déjà émises.

**Cas particuliers prioritaires :** Le paramètre de micro-entrepreneur
(« § 19 UStG ») désactive complètement la mention de la taxe. Un taux de
taxe par défaut fixé au niveau de l'organisation prime sur la matrice
pour le marché intérieur. Les clients de l'UE avec un numéro de TVA
intracommunautaire formellement valide reçoivent automatiquement
l'autoliquidation (Reverse Charge, 0 %), les clients de pays tiers la
mention d'export (0 %) — les lignes correspondantes de la matrice
fournissent pour cela le texte d'information sur la pièce.
