---
title: "Configurer l'authentification à deux facteurs"
topic: account.two-factor
version: 2
keywords:
    - 2FA
    - MFA
    - double authentification
    - authentification multifacteur
    - validation en deux étapes
    - code à usage unique
    - OTP
    - passkey
    - clé de sécurité
    - codes de récupération
    - codes de secours
    - compte piraté
audience: []
related:
    - admin.security
---

L'authentification à deux facteurs protège votre compte en plus du mot de
passe : à la connexion, vous confirmez votre identité par une seconde
méthode indépendante. Trois méthodes sont disponibles : **application
d'authentification** (scanner le code QR et confirmer avec le code à usage
unique), **code par e-mail** et **clé d'accès ou clé de sécurité**. Après
la première activation, des **codes de récupération** s'affichent :
conservez-les en lieu sûr hors de WorkDiary, chaque code n'étant utilisable
qu'une seule fois. Vous pouvez enregistrer plusieurs méthodes et en retirer ;
si votre organisation exige la double authentification, le dernier facteur
actif ne peut pas être supprimé. Ne partagez jamais le code QR, la clé
manuelle, les codes à usage unique ni les codes de récupération.

Si vous ne reconnaissez pas une connexion ou un appareil, utilisez « Ce
n’était pas moi – sécuriser le compte » : toutes les sessions prennent fin, le
mot de passe et les clés d’accès deviennent invalides, et vous recevez par
e-mail un lien pour définir un nouveau mot de passe.

**Connexion avec une passkey** : avec une passkey, vous vous connectez
sans mot de passe (« Se connecter avec une passkey ») ; elle compte comme
deux facteurs. Donnez un nom à chaque passkey lors de sa configuration :
la liste affiche le nom et la dernière utilisation et permet de la
renommer. La supprimer exige votre mot de passe ou une connexion récente.
Si votre organisation exige une passkey pour votre rôle, chaque page mène
à la configuration jusqu’à ce qu’une passkey soit enregistrée ; ensuite,
la connexion ne fonctionne qu’avec elle, un code de récupération restant
la voie de secours.
