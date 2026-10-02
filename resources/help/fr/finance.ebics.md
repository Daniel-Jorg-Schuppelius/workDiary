---
title: "Accès bancaire EBICS"
topic: finance.ebics
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.reconciliation
---

Avec EBICS (version 3.0), workDiary récupère les relevés quotidiens
directement auprès de la banque et envoie les lots de paiements — sans
télécharger ni téléverser de fichiers dans la banque en ligne.

**Mise en place :** Sous comptes bancaires, le symbole de banque ouvre
l’accès EBICS du compte. Saisissez l’URL EBICS, l’ID hôte, l’ID client et
l’ID abonné figurant dans la lettre d’accès de la banque. Ensuite, dans
cet ordre : générer les clés, les envoyer à la banque (INI et HIA),
télécharger la lettre d’initialisation, la signer et l’envoyer à la
banque. Une fois l’accès activé par la banque, récupérez les clés de la
banque — l’accès n’est actif qu’à ce moment-là.

**Relevés quotidiens :** Un accès activé récupère chaque matin les relevés
(camt.053) et les intègre au rapprochement des paiements ; « Récupérer les
relevés maintenant » le fait immédiatement. Les relevés déjà importés sont
reconnus et ignorés.

**Lots de paiements :** Un lot validé peut être envoyé à la banque avec «
Envoyer par EBICS » — le même fichier que celui proposé au téléchargement,
et une seule fois. Le signataire autorisé valide ensuite le paiement
(signature électronique) auprès de la banque.

**Sécurité :** Les clés sont stockées chiffrées et protégées en plus par
une phrase secrète. Chaque étape et chaque ordre figurent dans
l’historique de l’accès. En cas de soupçon d’abus, « Bloquer l’accès »
bloque les clés auprès de la banque ; la mise en place reprend ensuite
avec de nouvelles clés.
