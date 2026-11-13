---
title: "Gérer les modèles de formulaires"
topic: forms.templates
version: 4
keywords:
    - créer un formulaire
    - éditeur de formulaires
    - concepteur de formulaires
    - créer une check-list
    - champs du formulaire
    - types de champs
    - liste déroulante
    - champ obligatoire
    - activer un formulaire
    - archiver un formulaire
    - formulaires personnalisés
    - sections
    - autoriser un complément
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

Les modèles de formulaire définissent des check-lists et des saisies sans
code – par définition de champs. Vous les trouvez sous **Système** →
**Règles et processus** → **Modèles de formulaire** ou via le bouton
**Modèles de formulaire** dans la vue d’ensemble **Formulaires**.

Déroulement type :

1. **Créer un modèle** : **Nom**, **Description**, facultativement
   **Valable à partir du** et **Valable jusqu'au** ainsi que
   **Affectation : type de mission** et **Affectation : client** (avec
   « tous », le modèle s’applique partout). Viennent ensuite les
   **Champs** ; **Ajouter un champ** en ajoute un autre. Pour chaque
   champ : **Libellé du champ**, **Type de champ** et **Obligatoire**,
   selon le type les **Options** (séparées par des virgules), l’**Unité**
   ou la **Plage de valeurs** (Min, Max), facultativement un **Texte
   d’aide** et une condition **Visible si**, qui n’affiche un champ que
   lorsqu’un autre champ a une valeur donnée.
2. **Activer** : les nouveaux modèles commencent au statut « Brouillon » ;
   seul le statut « Actif » rend le modèle remplissable.
3. **Archiver** : retire le modèle de la sélection de remplissage – les
   formulaires remplis restent lisibles. Un modèle archivé peut être
   réactivé.

Types de champ : « Texte », « Texte multiligne », « Nombre », « Case à
cocher », « Choix », « Choix multiple », « Date », « Date et heure »,
« Échelle », « Photo », « Fichier », « Signature », « Section » et
« Mesure ». Vous ne saisissez pas de clé de champ ; chaque libellé de
champ ne peut apparaître qu’une seule fois par modèle.

Statuts importants : « Brouillon » → « Actif » → « Archivé ».

Principe de l’instantané : chaque formulaire rempli fige la définition
des champs au moment du remplissage. Les modifications de champs
n’affectent donc **que les formulaires remplis ensuite** – les anciens
restent inchangés et exploitables. Même **Supprimer** un modèle ne rend
pas les formulaires remplis illisibles.

Autorisations : peut créer, modifier, activer, archiver et supprimer des
modèles de formulaire toute personne disposant du droit **Gérer les
modèles de formulaire** (par défaut les chefs d’équipe).

Formulaires longs : structurez les modèles longs avec des champs de type
« Section » – lors de la saisie, chaque section devient une carte avec
sa progression et, à partir de quatre sections, une table des matières ;
jusqu’à six questions, le formulaire reste une seule carte. Pour
« Choix » et « Choix multiple », **Autoriser un complément** ajoute un
champ supplémentaire pour une remarque sur la réponse. Les choix
comportant jusqu’à six options apparaissent sous forme de boutons, les
plus longs sous forme de liste déroulante. Si vous modifiez le type de
réponse d’une question pendant que quelqu’un remplit un brouillon, sa
réponse antérieure y est conservée comme complément.

Conseil : le système déduit l’affectation interne d’un champ de son
libellé. Conservez donc les libellés de champ si vous souhaitez comparer
des formulaires remplis sur plusieurs versions d’un modèle.
