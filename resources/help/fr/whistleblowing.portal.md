---
title: "Configurer le portail de signalement"
topic: whistleblowing.portal
version: 2
keywords:
    - portail lanceur d'alerte
    - activer le portail
    - autoriser les signalements anonymes
    - signalements confidentiels
    - lien du portail
    - régénérer le lien
    - durée de conservation
    - texte d'introduction
    - mettre en place un dispositif d'alerte
    - langue par défaut
audience:
    - admin
modules:
    - module.compliance
related:
    - whistleblowing.cases
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Vous configurez ici le portail public de signalement de votre
organisation (`/compliance/portal`) — un seul portail par organisation ;
la gestion exige la permission **whistleblowing.settings.manage** et la
double authentification de la cellule. Les réglages comprennent
l'activation du portail, l'autorisation des signalements **anonymes**
et **confidentiels**, le texte d'introduction, la langue par défaut et
la durée de conservation (en mois) pour la suppression contrôlée des
cas clos. Le lien public contient un slug aléatoire non déductible du
nom de l'organisation ; **« Faire pivoter le lien »** génère un nouveau
slug. Attention : après rotation, les liens déjà diffusés deviennent
immédiatement invalides — communiquez ensuite activement le nouveau
lien.

**Langues** : la **langue par défaut** détermine la langue d'affichage du portail. Sous **Autres langues**, vous choisissez les langues vers lesquelles l'auteur du signalement peut basculer ; sans sélection, il n'y a pas de changement de langue.

**Canaux de signalement externes** : le portail rappelle toujours que des autorités de signalement externes sont accessibles en plus du service interne. Indiquez celles compétentes pour votre organisation avec leur désignation et leur adresse Internet ; elles apparaissent sous forme de liste dans le portail.

**Version de l'avis de confidentialité** : l'indication apparaît sous le texte d'introduction afin que la version applicable soit identifiable.

**Conservation** : les mois définis ici s'appliquent à chaque dossier clôturé à partir de maintenant. Les dossiers déjà clôturés conservent le délai calculé à leur clôture.
