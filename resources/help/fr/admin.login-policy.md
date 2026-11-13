---
title: "Politique de connexion : horaires, réseaux du bureau, passkey et arrêt d’urgence"
topic: admin.login-policy
version: 2
keywords:
    - politique de connexion
    - horaires de connexion
    - plage horaire
    - réseau du bureau
    - plage IP
    - CIDR
    - télétravail
    - approuver un appareil
    - passkey
    - passkey obligatoire
    - arrêt d’urgence
    - déconnecter tout le monde
audience:
    - admin
related:
    - admin.security
    - account.two-factor
---

La politique de connexion définit **quand** et **d’où** les membres de
votre organisation peuvent se connecter. Les administrateurs de
l’organisation sont toujours exemptés des horaires et des réseaux afin que
l’organisation ne puisse pas se bloquer elle-même ; l’exploitation de la
plateforme et les comptes du portail client ne sont jamais concernés.

**Horaires de connexion** : une plage horaire par jour de la semaine dans
le fuseau horaire de l’organisation, éventuellement pour certains rôles
seulement. En dehors de la plage, aucune nouvelle connexion n’aboutit – le
message indique la prochaine ouverture – et les sessions en cours se
terminent à la fin de la plage. Une plage peut passer minuit
(22:00–06:00). Les jetons d’API personnels suivent les horaires ; les
pointeuses et les intégrations non.

**Réseaux du bureau** : une liste de réseaux autorisés (IPv4/IPv6, aussi en
CIDR) et de noms DNS de connexions dynamiques ; le serveur résout les noms
toutes les cinq minutes. Sans le droit « Se connecter hors des réseaux du
bureau », la connexion n’est possible que depuis ces réseaux. Avec ce
droit, elle l’est aussi depuis l’extérieur, mais uniquement depuis un
**appareil approuvé** : un nouvel appareil dépose une demande
d’approbation lors de la connexion, que les administrateurs de
l’organisation approuvent ou refusent ici. L’appareil est reconnu grâce à
un cookie signé, pas à son adresse IP. Votre propre appareil est approuvé
par une autre personne.

**Effet « Journaliser uniquement »** : horaires et réseaux peuvent d’abord
être seulement journalisés. Le bas de la page montre alors pendant une
semaine qui la règle aurait bloqué. Ne passez à « Appliquer » que lorsque
la liste est correcte – sinon, des réseaux mal saisis bloqueraient des
bureaux entiers.

**Passkey obligatoire** : pour certains rôles, l’organisation exige la
connexion par passkey. Qui n’en a pas encore en configure une après la
prochaine connexion sous « Authentification à deux facteurs ». Un code de
récupération reste la voie de secours si l’appareil manque.

**Arrêt d’urgence** : en cas d’urgence, les titulaires ou les
administrateurs de l’organisation mettent fin, en indiquant un motif et en
ressaisissant leur mot de passe, à toutes les sessions et à tous les jetons
d’API personnels de l’organisation sauf leur propre session. Ensuite,
seuls les administrateurs de l’organisation et les personnes ayant le droit
« Arrêt d’urgence » peuvent se connecter, jusqu’à ce qu’une personne ayant
ce droit ou l’exploitation de la plateforme lève l’arrêt. Tous les
administrateurs de l’organisation sont informés ; pointeuses, appareils de
localisation et intégrations continuent de fonctionner, les autres
organisations ne sont pas concernées.

**Accès personnels** : les jetons d’API et les connexions IA (MCP) ne
portent pas de cookie d’appareil. Hors des réseaux du bureau, ils ne
fonctionnent qu’avec le droit distinct « Utiliser les accès personnels aussi
hors des réseaux du bureau » ; les horaires et l’arrêt d’urgence s’appliquent
toujours.

**Appareils et noms DNS en détail** : une approbation refusée ou retirée
reste enregistrée – le même appareil ne redemande pas tant que vous n’avez
pas « oublié » le refus. Chaque personne a au plus trois demandes ouvertes.
Chaque organisation peut saisir au plus dix noms DNS ; ils sont résolus en
arrière-plan avec une limite de temps, une résolution réussie reste valable
24 heures au plus, et les adresses internes des réponses DNS ne comptent que
si l’exploitant l’autorise. « Appliquer » exige au moins une plage horaire
ou un réseau.

**Passkey obligatoire et SSO** : si quelqu’un se connecte avec un code de
récupération malgré la passkey obligatoire, la personne et tous les
administrateurs de l’organisation sont informés ; la dernière passkey ne
peut être supprimée qu’une fois une nouvelle configurée. Les sessions
ouvertes via SSO ne sont pas soumises à la passkey obligatoire : le
fournisseur d’identité y décide du facteur, le levier est l’obligation SSO.
