---
title: "Connecter DomainReselling"
topic: admin.domain-provider
version: 2
keywords:
    - gestion des domaines
    - noms de domaine
    - revendeur de domaines
    - DNS
    - enregistrements DNS
    - enregistrement de domaine
    - renouvellement de domaine
    - registraire
    - portefeuille de domaines
audience:
    - admin
    - geschaeftsfuehrung
modules:
    - module.domain
related:
    - admin.plugins
    - admin.integrations
---

WorkDiary connecte un **compte DomainReselling** par organisation et gère
ses domaines de manière contrôlée : lire le portefeuille, affecter des
clients, entretenir les échéances et le DNS, et soumettre les actions à
haut risque à une validation. Cette page configure la connexion ; le
travail sur les domaines se fait ensuite dans le module « Domaines ».

**Choisir l'environnement :** Chaque connexion fonctionne soit en *OT&E*
(l'environnement de test/pilote), soit en *production*. Les nouveaux comptes
démarrent en OT&E ; la production n'est débloquée qu'après un pilote réussi
et réellement confirmé, afin qu'aucun enregistrement réel ne se retrouve par
erreur dans un test.

**Identifiants :** L'identifiant et le mot de passe sont stockés chiffrés et
n'apparaissent jamais dans les URL, journaux ou diagnostics. En option,
indiquez un utilisateur par défaut (s_user) : le contexte sous lequel
s'exécutent les commandes d'un sous-utilisateur autorisé.

**Tester et synchroniser :** « Tester la connexion » vérifie les
identifiants auprès de l'API sans rien modifier. « Synchroniser » importe le
portefeuille actuel (domaines, échéances, modes de renouvellement,
revendeurs/sous-utilisateurs) dans les projections locales. La
synchronisation est en lecture seule et idempotente.

**Confirmer le pilote :** Après un test réel réussi, vous confirmez le
pilote ; ce n'est qu'ensuite que la connexion peut passer en production.
Tant que le pilote reste ouvert, le contrôle d'état indique « pilote
ouvert ».

**Renouveler les identifiants :** L’icône de clé ouvre la boîte de
dialogue « Renouveler les identifiants ». Vous saisissez un nouveau mot de
passe ; l’identifiant et l’utilisateur par défaut restent inchangés si vous
laissez les champs vides. WorkDiary vérifie immédiatement les nouveaux
identifiants — la connexion est conservée avec tous les domaines,
affectations et justificatifs.

**Déconnecter :** « Déconnecter » ne supprime rien. Le mot de passe est
retiré, le statut passe à *déconnecté*, et plus aucune synchronisation,
récupération d’événements ou commande n’atteint le fournisseur. Les
domaines, contacts, zones DNS, le journal des commandes (qui a modifié quoi
chez le fournisseur), les écritures et les liens de domaine de vos
abonnements de revente et lignes d’achat sont conservés comme justificatifs.
Pour vous reconnecter, enregistrez de nouveaux identifiants via
« Renouveler les identifiants » ; une fois la vérification réussie, la
connexion est de nouveau active.

**États :** Une connexion est en *brouillon*, *active*, *bloquée* ou
*déconnectée*. Les connexions bloquées apparaissent clairement comme
bloquées dans le contrôle de santé — jamais comme une erreur silencieuse. Le
contrôle de santé ne signale pas une connexion déconnectée comme un
incident ; elle est volontairement hors service.
