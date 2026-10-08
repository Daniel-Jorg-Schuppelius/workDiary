---
title: "Candidatures & appels d'offres"
topic: applications.overview
version: 2
keywords:
    - recrutement
    - gestion des candidats
    - annonce de poste
    - entretien de recrutement
    - vivier de talents
    - refuser un candidat
    - embauche
    - marchés publics
    - soumission
    - négociation de contrat
    - dossier de candidature
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
l'invitation délibérée. Une décision est définitive : entretiens,
propositions de rendez-vous et nouvelles décisions sont ensuite bloqués.
Les annonces publiées passent chaque jour à « Expirée » après la date
d'expiration ou la date limite de candidature ; vous pouvez les republier
avec une nouvelle date ou les clôturer.

La décision met aussi fin aux entretiens et propositions de rendez-vous en
cours : les entretiens planifiés sont marqués comme annulés (la note est
conservée), les liens de rendez-vous non encore choisis expirent
immédiatement. Seule exception : le vivier de talents. Tant que le
consentement est valable, « Reprendre depuis le vivier de talents » replace
le dossier au début du pipeline ; la programmation de suppression et le
consentement sont supprimés, le délai de suppression est fixé à nouveau avec
la prochaine décision. Sans consentement valable, le dossier reste dans le
vivier de talents jusqu'à ce que la programmation de suppression s'applique.
**Négociations contractuelles :** étape dédiée et versionnée entre la
décision de gain ou d'embauche et le transfert. Les points bloquants
ouverts et les validations manquantes (commerciale + métier,
auto-validation verrouillée) empêchent la conclusion. Une validation vaut
pour la version présentée : si une nouvelle version est déposée après qu'un
niveau de validation a été accordé, la validation recommence avec un nouveau
tour ; le tour précédent reste visible dans le dossier comme historique.

Les étapes d'approbation apparaissent aussi sous « Approbations » pour le
rôle que l'organisation associe au type d'étape — par défaut : commercial →
Comptabilité, technique → Chef d'équipe, RH → Gestion du personnel ;
modifiable lors de l'édition de l'organisation, section « Approbations ».
Une décision prise là a le même effet que l'approbation depuis le dossier ;
une étape peut aussi y être refusée (avec motif) — une nouvelle version
lance alors le tour suivant.

Mention légale : WorkDiary documente le processus, mais ne remplace aucun
conseil juridique — en particulier aucune appréciation de la licéité ou de
la pertinence économique des conditions contractuelles.
