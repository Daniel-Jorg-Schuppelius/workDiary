---
title: "Exécuter une procédure"
topic: procedures.run
version: 4
keywords:
    - remplir une check-list
    - suivre une instruction
    - démarrer une procédure
    - étapes obligatoires
    - signaler un écart
    - contre-signature
    - double contrôle
    - preuve de sauvegarde
    - valider une étape
audience: []
related:
    - protocols.create
---

Une procédure (instruction de travail avec étapes obligatoires) se lance
sur une **intervention** ou un **actif**. Les **étapes obligatoires**
doivent être traitées dans l'ordre défini ; les **étapes de sauvegarde**
exigent une preuve (hash + taille ou lien de stockage externe) et les
**étapes à double contrôle** la confirmation d'une **seconde personne**
avec le rôle adéquat. Les **écarts** doivent être justifiés avec action
de suivi — impossible de sauter une étape sans motif. À la clôture, le
numéro de version du modèle est figé ; les modifications ultérieures du
modèle n'ont **pas** d'effet rétroactif.

## Étapes à double contrôle

La barre **Validation à quatre yeux** de l'étape indique l'état : pas
encore demandée, demandée, prise en charge ou contresignée.

1. La personne qui exécute l'étape clique sur **Demander une seconde
   personne**. Les responsables d'équipe reçoivent une notification avec
   un lien vers le déroulement.
2. Une autre personne autorisée ouvre le déroulement et clique sur
   **Contresigner en tant que seconde personne**. La personne qui a fait
   la demande ne peut pas contresigner elle-même.
3. Ce n'est qu'ensuite que l'étape peut être terminée.

Une contresignature peut être **révoquée** avec un motif (droit
« Révoquer l'approbation à quatre yeux », en général responsables ou
administration). L'étape repasse à « ouverte », la demande reste active
et une autre personne peut contresigner. Demande, contresignature et
révocation sont consignées avec la personne et l'heure dans l'historique.

## Preuve de sauvegarde

Une étape de type « Enregistrement de sauvegarde » affiche la carte **Preuve de
sauvegarde**.

1. **Saisir la preuve de sauvegarde** ouvre la boîte de dialogue : système
   sauvegardé, périmètre, date de la sauvegarde, stockage (**Pièce jointe**
   avec fichier ou **Stockage externe** avec emplacement et taille) et mode
   de vérification. Ce que le modèle fixe n'est pas proposé ; la taille
   minimale et l'âge maximal sont contrôlés à l'enregistrement.
2. Avec le mode **Comparaison de somme de contrôle**, vous indiquez la
   somme de contrôle attendue. Pour un fichier téléversé, le système
   recalcule la somme et confirme lui-même la preuve ; en stockage externe,
   la personne qui vérifie saisit la somme recalculée.
3. **Test de restauration** et **Confirmation de la direction** sont
   confirmés par une **seconde personne** disposant du droit « Vérifier un
   enregistrement de sauvegarde » via **Confirmer la preuve** – pas par la
   personne qui a saisi la preuve.
4. **Refuser la preuve** exige un motif ; vous saisissez ensuite une
   nouvelle preuve (**Remplacer la preuve**).

L'étape ne peut être terminée qu'avec une preuve confirmée. Une étape
ultérieure qui exige une sauvegarde préalable est alors débloquée. Si
aucune sauvegarde n'est possible, consignez-le comme **écart**.
L'emplacement d'une sauvegarde externe n'est visible qu'avec le droit
« Voir le stockage de sauvegarde externe ».

## Qualification

Si une étape indique une **Qualification**, seule une personne qui détient
aujourd'hui cette qualification en cours de validité (abréviation ou nom
tels qu'ils figurent dans les qualifications des employés) peut la
terminer. Il en va de même pour la seconde personne lorsque le modèle exige
une qualification pour la contresignature.

## Écart critique et acceptation du risque

Un écart figure sur l'étape avec son type, sa gravité, son motif et sa
mesure de suivi. Un écart **critique** bloque la clôture de l'exécution
tant qu'aucun point ouvert ni ordre de suivi n'en est issu. La personne qui
dispose du droit « Accepter un écart critique » ouvre **Accepter le
risque** sur l'écart et indique un motif. La personne, la date et le motif
figurent dans l'historique de l'exécution ; le rapport « Écarts de
procédure » affiche la décision.

## Procédures critiques

L'exécution d'un modèle de niveau de risque « Critique » ne peut être
clôturée que si au moins une étape à double contrôle est terminée et
contresignée.

## Démarrage sur un équipement

Les modèles que le concepteur attribue par catégorie d'équipement, produit
ou contrat SLA apparaissent sur la fiche de l'équipement dans la carte
**Procédures** sous « Procédures prévues pour cet équipement : ». Si
l'équipement est bloqué, seul un modèle portant l'option **Peut être
exécutée sur des équipements bloqués** démarre.

**Refaire l’exécution :** si vous choisissez cette action de suivi pour un écart, WorkDiary lance aussitôt une nouvelle exécution du même modèle sur la même commande ou le même équipement. L’écart y renvoie, et un écart critique ne bloque alors plus la clôture. Si l’équipement est bloqué, aucune exécution n’est créée ; le motif figure dans l’historique.
