---
title: "Design des documents"
topic: admin.document-design
version: 1
audience:
    - admin
related:
    - admin.branding
    - invoices.manage
---

Le design des documents adapte les PDF générés à l'apparence de
votre organisation : papier à en-tête, zones d'impression et zones
bloquées, blocs d'information et préréglage de style de tableau.

Déroulement :

1. **Téléverser un papier à en-tête** (PDF, JPG ou PNG, A4 portrait) — un
   asset pour la première page et, en option, un pour les pages suivantes.
   Les PDF sont réduits à une page matricielle sûre et non interactive ;
   l'original est conservé comme preuve.
2. **Créer un profil** et définir dans l'éditeur les zones d'impression,
   la fenêtre d'adresse, la ligne d'expéditeur et les zones bloquées en
   millimètres — visuellement ou numériquement, clavier compris.
3. **Déclarer les blocs d'information** : `dynamique` (WorkDiary imprime),
   `fourni par le papier à en-tête` (avec confirmation par version de
   profil) ou `non applicable`. Les blocs obligatoires des types de
   documents attribués et les données variables sont protégés.
4. **Générer un document de test** par type de document avec textes longs,
   nombreuses positions et plusieurs taux de taxe ; le preflight montre les
   chevauchements, les blocs obligatoires manquants et les problèmes de
   contraste.
5. **Activer la version** — uniquement avec un preflight sans erreur. Les
   versions activées sont immuables ; les modifications passent par un
   nouveau brouillon. Les documents finalisés conservent leur état figé.

Sans profil, la valeur système par défaut (sortie actuelle) s'applique.
Les factures ZUGFeRD/PDF-A-3 restent valides après application du design —
la facture structurée reste déterminante.
