---
title: "Cellule de signalement – traitement des cas"
topic: whistleblowing.cases
version: 5
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
chiffrées et analysées contre les logiciels malveillants avant d'être
libérées. La carte **Pièces jointes** indique le nom et la taille de chaque
fichier ; vous téléchargez les pièces libérées avec l'icône
**Télécharger**. Sinon, la carte affiche l'état de l'analyse : **Analyse en
cours** tant que le résultat est attendu, **Rejeté** ou **Analyse échouée**
– WorkDiary ne délivre pas ces fichiers. Sans antivirus configuré, les pièces
jointes restent en cours d'analyse.

**Confidentialité et conflits** :

- **Déclarer un conflit d'intérêts** (justification facultative) vous
  exclut vous-même du dossier : votre attribution prend fin
  immédiatement et vous ne pouvez pas lever le blocage vous-même.
- **Marquer la personne concernée** exclut une personne du dossier via son
  **ID utilisateur** (**Marquer**, note facultative) ; les
  attributions existantes prennent fin immédiatement.
- **Accès d'urgence** donne à une personne non attribuée un accès limité
  dans le temps, quatre heures par défaut (ID utilisateur et
  justification obligatoire, **Valider**). Seule une autre personne peut
  l'accorder.
- **Exporter le dossier** crée un paquet chiffré en AES-256 (justification
  et mot de passe d'au moins douze caractères, **Exporter**) ; transmettez le
  mot de passe séparément du paquet.
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

**Filtres de la liste** : la barre au-dessus de la liste filtre par **statut**, **catégorie**, **priorité**, personne attribuée, état des délais (« Délai dépassé », « Délai dans les 14 jours », « Retour d'information en attente ») et période de réception. Catégorie et priorité ne s'appliquent qu'aux dossiers qui vous sont attribués – sinon la liste des résultats révélerait les deux.

**Délais et retour d'information** : si vous cochez **Il s'agit du retour d'information prévu à l'article 17 HinSchG** lors de l'envoi d'un message, WorkDiary enregistre la date au dossier ; une simple question complémentaire ne compte pas. Le rappel du délai de retour s'arrête alors. Les gestionnaires attribués reçoivent des rappels sans contenu : 48 heures avant l'échéance de l'accusé de réception, 14 jours et 48 heures avant le délai de retour (une fois chacun), puis chaque jour tant qu'un délai est dépassé. Passer un dossier directement de « Reçu » à « Examen » vaut accusé de réception.

**Autres actions dans le dossier** :

- **Révoquer l'attribution** (icône à côté du gestionnaire) : la personne perd immédiatement l'accès.
- **Ajouter un document du service de signalement** : le fichier est chiffré comme une pièce jointe de l'auteur du signalement et n'est disponible qu'après l'analyse ; l'auteur du signalement ne le voit pas.
- Le statut « Blocage de suppression » exige une **base juridique**, un **responsable** et une **date de réexamen** ; à cette date, WorkDiary rappelle les gestionnaires.
- Une réouverture (de « Clôturé – … » à « En cours ») exige un **motif** ; le délai de conservation recommence à la prochaine clôture.
- **Journal des événements** : avec le droit de consultation du journal, vous voyez qui a ouvert, modifié ou exporté le dossier et quand – sans contenu du signalement. Chaque consultation du journal est elle-même journalisée.
