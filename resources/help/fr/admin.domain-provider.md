---
title: "Connecter DomainReselling"
topic: admin.domain-provider
version: 1
audience:
    - admin
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

**Renouveler les identifiants et déconnecter :** L'identifiant/mot de passe
peuvent être réinitialisés à tout moment (rotation) sans recréer la
connexion. La déconnexion supprime la connexion ; les données de projection
déjà lues sont conservées comme preuve.

**États :** Une connexion est en *brouillon*, *active* ou *bloquée*. Les
connexions bloquées affichent un état bloqué visible dans le contrôle
d'état, jamais une défaillance silencieuse.
