---
title: "Profils de branche"
topic: admin.branch-profiles
version: 3
keywords:
    - modèle sectoriel
    - corps de métier
    - pack de modèles
    - électricité
    - plomberie chauffage
    - nettoyage
    - types de commande
    - modèles de checklist
    - configuration initiale
    - variante de profil
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

Les profils de branche installent en une étape un paquet de modèles
adaptés à un corps de métier : types de commande, catégories, règles
obligatoires, listes de contrôle, exigences de locaux et étiquettes
standard. Cherchez le métier voulu dans le catalogue, consultez l'**aperçu
du contenu** sur la carte, puis choisissez **Installer** et confirmez.
L'installation est **idempotente** : une réinstallation ne crée pas de
doublons et n'écrase pas les données adaptées localement ; **Réappliquer**
remet les modèles importés à l'état du profil, sans jamais toucher aux
listes de contrôle déjà publiées. Chaque installation est journalisée de
manière infalsifiable et de nouveaux métiers peuvent être ajoutés sans
modification du code.

Les profils peuvent être **combinés** ; le premier installé est le **profil
principal**, qui détermine le focus de navigation et les valeurs par
défaut, tandis que la recommandation de modules réunit tous les profils
installés (« Définir comme profil principal » le change). **Désinstaller**
(administrateur de plateforme) supprime les types d'intervention,
catégories et étiquettes inutilisés, désactive les classifications
utilisées et efface les règles obligatoires du profil ; les modèles sont
conservés. Un indicateur de mise à jour sur la carte signale une version
plus récente ; **Importer** accepte un profil JSON du catalogue. Les types
d'intervention s'affichent dans la langue de l'utilisateur.

**Variantes spécifiques au client :** une variante se superpose à un profil
sectoriel – elle reprend le profil de base, retire des éléments et ajoute les
siens, sans modifier le profil. « Créer une variante » demande le profil de
base, un code (minuscules, chiffres et tirets) et un intitulé. Sous « Retirer
des éléments », vous cochez ce qui ne doit pas être créé à l'installation ;
les entrées existantes ne sont pas touchées. Les « Ajouts » sont un extrait
de profil au format JSON, structuré comme un profil sectoriel ; les éléments
de même nom remplacent ceux du profil de base. **Installer** applique la
variante, « Mettre à jour les entrées existantes » aligne les entrées déjà
installées ; chaque enregistrement incrémente la version. « Exporter en
JSON » transmet la variante ; la supprimer conserve les entrées installées.
