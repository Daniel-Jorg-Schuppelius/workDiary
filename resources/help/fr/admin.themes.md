---
title: "Thèmes"
topic: admin.themes
version: 5
keywords:
    - mode sombre
    - thème sombre
    - mode clair
    - palette de couleurs
    - couleurs personnalisées
    - apparence
    - charte graphique
    - identité visuelle
    - contraste
    - habillage
audience:
    - admin
modules:
    - module.theming
related:
    - admin.handbook
    - admin.license
    - navigation.interface
---

Les thèmes sont des préréglages de design de votre organisation pour
l'interface. Ils définissent la palette de couleurs et de géométrie
(mode de base clair ou sombre). Outre les **Thèmes prédéfinis**, vous
pouvez créer vos propres thèmes sous **Thèmes personnalisés** (douze au
maximum).

Avec **Nouveau thème** ou **Modifier**, vous définissez pour chaque
thème :

- **Données de base** : **Clé** (minuscules, chiffres, trait d'union ;
  non modifiable après la création), nom et **Mode de base** (**Clair**
  ou **Sombre**).
- **Couleurs** : couleurs de base, d'accent et de statut (par ex.
  arrière-plan, principal, secondaire, accent, neutre ainsi
  qu'info/succès/avertissement/erreur). Les couleurs de texte sont
  dérivées automatiquement du contraste.
- **Géométrie** : rayons des coins et **Largeur de bordure**.

L'**Aperçu** dans la boîte de dialogue montre immédiatement le
résultat. Un contraste minimal (neutre par rapport au texte neutre) est
imposé afin que la barre latérale et les panneaux restent lisibles.

Définir la valeur par défaut :

- Dans la zone **Thème par défaut de l'organisation**, vous choisissez
  un thème pour le **Mode clair** et un pour le **Mode sombre**, puis
  enregistrez avec **Appliquer**. Ce choix s'applique à tous les
  membres qui n'ont pas choisi leur propre thème dans leur profil ; les
  thèmes personnalisés portent alors la mention **Clair par défaut** ou
  **Sombre par défaut**.
- L'entrée **Par défaut (Corporate)** ou **Par défaut (Corporate
  Dark)** annule à nouveau votre sélection ; ce sont alors les thèmes
  fournis Corporate (clair) et Corporate Dark (sombre, mêmes couleurs
  sur fond sombre) qui s'appliquent.

Licence/modules : les thèmes personnalisés font partie du module
**Thèmes personnalisés** et sont disponibles dans les plans supérieurs.
En cas de rétrogradation, un thème actif est conservé (purement
cosmétique) ; la page **Thèmes**, avec l'éditeur et le choix par
défaut, est alors bloquée. Détails dans le chapitre **Licence**.

Autorisation : la gestion des thèmes est réservée aux administrateurs
de l'organisation.

Risques : la suppression d'un thème utilisé rétablit un thème de repli
pour les utilisateurs concernés ; s'il était défini par défaut, c'est
de nouveau le thème fourni qui s'applique. Vérifiez la lisibilité des
modifications de couleurs avant de définir un thème comme thème par
défaut.
