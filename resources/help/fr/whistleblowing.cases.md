---
title: "Cellule de signalement – traitement des cas"
topic: whistleblowing.cases
version: 3
keywords:
    - lanceur d'alerte
    - alerte éthique
    - dispositif d'alerte
    - canal interne
    - traiter un signalement
    - accusé de réception
    - dossier de conformité
    - conflit d'intérêts
    - accès d'urgence
    - protection des lanceurs d'alerte
audience: []
modules:
    - module.compliance
related:
    - whistleblowing.portal
    - whistleblowing.report
    - admin.security
    - privacy.overview
---

Ici, vous traitez les signalements reçus de lanceurs d’alerte internes
et externes. La liste **Signalements lanceurs d'alerte** se trouve dans
le menu **Conformité** → **Point de signalement**. L’autorisation du
service de signalement (rôle **Point de signalement**) est volontairement
**séparée** de l’administration : même les administrateurs n’ont aucun
accès sans attribution personnelle au dossier. Chaque accès exige le
droit correspondant **et** l’attribution au dossier concerné ; il
n’existe aucune exception pour les administrateurs.

L’accès requiert votre propre authentification à deux facteurs ; sans
elle, WorkDiary vous redirige vers sa configuration.

**Liste des dossiers** : la vue d’ensemble n’affiche que les données de
base (**Numéro de dossier**, **Catégorie**, **Statut**, **Priorité**,
**Réception jusqu'au**, **Réponse avant le**) – volontairement **sans
aperçu du contenu**. Catégorie et priorité n’apparaissent que lorsque le
dossier vous est attribué (« Visible après l'attribution »). Le contenu de
chaque dossier est chiffré avec une clé propre.

**Détail du dossier** : le dossier affiche **Informations sur le
dossier**, **Contenu du signalement**, **Responsable** et
**Communication et notes**. Selon vos autorisations, vous pouvez

- **Confirmer la réception** (délai **Réception jusqu'au** : 7 jours
  après réception),
- sous **Changer le statut**, choisir le statut suivant autorisé et
  l’appliquer avec **Définir le statut** – par exemple « Déposé » →
  « Réception confirmée » → « Examen préliminaire » → « En cours de
  traitement » (entre-temps « En attente du lanceur d'alerte » ou
  « Transmis ») → « Clôturé – … » ; une clôture exige une
  **Justification**, enregistrée comme note interne,
- sous **Attribuer un responsable**, ajouter une personne via son **ID
  utilisateur** avec un **Rôle** (**Attribuer**),
- saisir une **Note interne** (**Enregistrer la note** ; jamais visible
  pour la personne signalante),
- envoyer un **Message à la personne signalante** (**Envoyer**) ; il
  apparaît dans sa boîte de réception protégée.

Les pièces jointes téléversées par la personne signalante sont stockées
chiffrées ; le dossier ne propose actuellement pas de téléchargement.

**Confidentialité et conflits** :

- **Déclarer un conflit d'intérêts** (justification facultative) vous
  exclut vous-même du dossier : votre attribution prend fin
  immédiatement et vous ne pouvez pas lever le blocage vous-même.
- Le dossier ne propose actuellement ni le marquage des personnes
  concernées ni un accès d’urgence pour d’autres personnes.
- Chaque étape du dossier est consignée de manière infalsifiable dans le
  journal du dossier.

**Suppression** : lorsqu’un dossier est au statut « Examen de
conservation », la carte **Suppression contrôlée** apparaît. **Supprimer
le dossier** et la confirmation **Supprimer définitivement** détruisent
la clé du dossier : contenu du signalement, messages, pièces jointes et
attributions sont alors irrémédiablement perdus ; seule subsiste une
preuve de suppression sans contenu. C’est irréversible. Si une procédure
ou une obligation de conservation s’y oppose, choisissez plutôt le
statut « Suspension de suppression (legal hold) ».
