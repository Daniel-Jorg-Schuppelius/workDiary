---
title: "Candidatures & appels d'offres"
topic: applications.overview
version: 1
audience: []
modules:
    - module.applications
related:
    - documents.manage
---

Le module gère deux dossiers en amont, avant que ne naissent des commandes
opérationnelles ou des données de collaborateurs :

**Candidatures à des marchés (appels d'offres) :** Dossier avec échéances,
potentiel de valeur, décision go/no-go, check-list des pièces et paquets de
soumission versionnés (snapshot avec hachage SHA-256). Les appels d'offres
gagnés sont transformés de manière contrôlée en projet ; les appels
d'offres perdus restent exploitables avec leur motif de perte.

**Candidatures de personnel :** Besoin de poste → publication → dossier de
candidature avec entretiens, évaluations et décision. Les données des
candidats sont stockées chiffrées et ne sont visibles que par le service
des ressources humaines (droits recruiting). Les refus déclenchent
automatiquement la programmation de la suppression (par défaut six mois
après le délai de recours AGG, configurable) ; le vivier de talents exige
un consentement explicite et limité dans le temps. Les acceptations créent
un brouillon de collaborateur — un compte actif ne naît que par
l'invitation délibérée.

**Négociations contractuelles :** étape dédiée et versionnée entre la
décision de gain ou d'embauche et le transfert. Les points bloquants
ouverts et les validations manquantes (commerciale + métier,
auto-validation verrouillée) empêchent la conclusion.

Mention légale : WorkDiary documente le processus, mais ne remplace aucun
conseil juridique — en particulier aucune appréciation de la licéité ou de
la pertinence économique des conditions contractuelles.
