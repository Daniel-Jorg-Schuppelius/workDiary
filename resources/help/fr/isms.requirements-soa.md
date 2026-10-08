---
title: "Exigences & DdA (SoA)"
topic: isms.requirements-soa
version: 3
keywords:
    - déclaration d'applicabilité
    - applicabilité
    - catalogue d'exigences
    - exigences normatives
    - référentiel normatif
    - Annexe A
    - ISO 27001
    - ISO 9001
    - ISO 27701
    - importer un catalogue
    - justification de non-applicabilité
audience: []
modules:
    - module.isms
related:
    - isms.overview
    - isms.controls
    - isms.conformity
    - glossary.core
---

Ici, vous gérez le catalogue d’exigences et la **déclaration
d’applicabilité (DdA)** par périmètre. La page se trouve sous **SMSI** →
**Pilotage** → **Exigences & DdA**.

Déroulement type :

1. **Charger le catalogue normatif** : choisir et charger un **Profil de
   norme** – ISO/IEC 27001:2022 avec l’annexe A complète, ainsi que
   ISO/IEC 27701, ISO 9001, ISO 22301, ISO 45001, ISO 37301 et ISO/IEC
   42001 avec leurs chapitres principaux 4 à 10, et le NIST Cybersecurity
   Framework 2.0. Seuls le numéro et le titre court sont chargés, aucun
   texte normatif. Un nouveau chargement n’écrase jamais les exigences
   existantes ni les déclarations DdA renseignées. Sinon, **Importer
   OSCAL** reprend un catalogue depuis un fichier JSON.
2. Ajouter facultativement vos propres exigences avec **Ajouter une
   exigence** ; leur **Source** est alors « Exigence propre » au lieu de
   « Catalogue de référence ».
3. **Créer les déclarations DdA** : crée les déclarations manquantes pour
   toutes les exigences du périmètre choisi ; les déclarations existantes
   restent inchangées. Renseignez ensuite chaque déclaration avec
   **Modifier la déclaration DdA**.
4. Utiliser la vue imprimable **DdA** (**Imprimer / enregistrer en PDF**)
   pour les preuves et les audits ; **Export (CSV)** et **Export (JSON)**
   fournissent les données sous forme de fichier.

Champs importants par exigence : **Norme**, **Édition**, **N° de réf.**
(p. ex. « A.5.1 ») et un **Titre** propre – volontairement aucun texte
normatif.

Par déclaration DdA :

- **Applicable** oui/non – en cas de « non », une **Justification** est
  obligatoire et le **Statut de mise en œuvre** devient automatiquement
  **« Non applicable »**.
- **Statut de mise en œuvre** : « Ouvert », « Partiellement mis en
  œuvre », « Mis en œuvre », « Non applicable ».
- **Note de preuve** : renvoi vers une preuve ou un document.

Autorisations : le droit **Voir les registres SMSI (risques, mesures,
DdA)** permet la consultation. L’import du catalogue et la gestion
requièrent **Gérer le SMSI (risques, mesures, import du catalogue)**.

Étapes suivantes : reliez les exigences à des **Mesures** neutres
vis-à-vis des normes (colonne **Mesures liées**) – vous créez ainsi le
pont entre le « quoi » de la norme et le « comment » de votre mise en
œuvre.
