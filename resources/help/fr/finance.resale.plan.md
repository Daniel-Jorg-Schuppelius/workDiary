---
title: "Modèles récurrents (plan)"
topic: finance.resale.plan
version: 1
keywords:
    - modèles récurrents
    - facture récurrente
    - proposition de modèles
    - contrôle du plan
    - état d’exécution
    - abonnement sans modèle
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.accounts
    - finance.resale.periods
---

Les **modèles récurrents** sont les factures récurrentes du logiciel de
comptabilité ; le registre les reflète, affecte leurs lignes aux abonnements
et contrôle le stock par rapport au plan. La page « Proposition de modèles »
indique quels modèles restent à créer.

**Modèles récurrents (plan) :** Si un programme de comptabilité émet les
factures, les factures récurrentes y naissent comme modèles récurrents. Le
registre les reflète (Lexoffice : chaque jour et après la synchronisation des
pièces, en lecture seule) et montre dans le compte de licences, par modèle,
statut, rythme, prochaine exécution et les lignes de licence avec les
abonnements qu'elles facturent. L'affectation abonnement ↔ ligne de modèle est
proposée par le traitement — d'abord via la facture que le modèle a créée en
dernier et qui est affectée à une période, sinon via même destinataire,
article et quantité (avec plusieurs clients finaux d'un partenaire seulement
s'ils sont nommés) : vous confirmez,
affectez à la main ou retirez. Les périodes facturées par un modèle actif
sont exclues de la proposition de facture ; ce qui se termine avant sa première
exécution y reste. Les anciennes années peuvent ainsi être facturées même si le
modèle existe déjà. Les **contrôles stock ↔ plan**
signalent avant la facture : abonnement sans modèle, modèle sans abonnement
(« Créer un abonnement depuis le modèle » reprend article, quantité, rythme et
prix), quantité différente, modèle qui continue après la fin de l'abonnement,
modèle en pause ou terminé alors que l'abonnement court, prochaine exécution à
plus de sept jours du début de période, dernière exécution échouée — dans la
tuile, le résumé hebdomadaire et la file de travail. Chaque facture reflétée
porte la référence à son modèle.

**Proposition de modèles et état d'exécution :** La page « Proposition de
modèles » (depuis la liste des périodes) liste, par destinataire en
facturation externe, les abonnements sans modèle affecté avec article, texte
de ligne, quantité, unité, prix unitaire, intervalle et première exécution ;
les destinataires couverts par le traitement récurrent du registre sont
marqués. Vous créez le modèle dans le logiciel de comptabilité, le prochain
reflet propose l'affectation. Par modèle, le compte de licences montre ce
qu'est devenue la dernière exécution — facture payée, ouverte (vérifier
l'envoi dans le logiciel) ou manquante — et, en cas d'échec, le message du
logiciel en infobulle ; l'API Lexoffice ne sait pas si une facture est partie
par e-mail. Une ligne de modèle accueille au plus autant de licences qu'elle
facture ; les abonnements de domaine ne sont affectés que si le domaine est
nommé exactement.
