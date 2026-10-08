---
title: "Demandes clients"
topic: customer.intakes
version: 1
keywords:
    - demande client
    - demande du portail
    - commande d'impression
    - demande informatique
    - traiter une demande
    - rejeter une demande
    - question au client
    - lier un devis
    - lien de dépôt
    - Nextcloud
    - fichiers du client
audience: []
related:
    - customer.queries
    - print.orders
---

Les clients soumettent des demandes d'impression ou informatiques avec fichiers dans le portail client, sous **Demandes et commandes**. Chaque demande reçoit un numéro de dossier et apparaît ici avec le client, le type de prestation, le statut, la date souhaitée et la personne responsable. Les demandes ouvertes sont toujours listées ; les demandes clôturées sont limitées à la période de l'en-tête.

Pour traiter une demande :

- **Attribuer** prend la demande en charge ; elle passe à « En cours ».
- **Poser une question** envoie au client une question avec fichiers. La demande attend alors sa réponse ; une réponse ou des fichiers complémentaires relancent le traitement.
- **Note interne** reste dans l'entreprise — le client ne voit jamais la note ni ses fichiers.
- **Devis** : créez un nouveau devis ou liez un devis existant du client. Après validation et envoi, le client décide dans le portail ; liez à nouveau une version révisée — un accord antérieur ne vaut jamais pour une autre version.
- **Transférer** crée l'ordre d'impression ou le ticket après acceptation. Une acceptation partielle exige un rapprochement documenté du périmètre. Les transferts répétés ou simultanés ne créent pas de second dossier.
- **Refuser** exige un motif que le client lira. Ce n'est plus possible après l'acceptation d'un devis.

Après le transfert, le dossier opérationnel fait foi ; le client en voit l'état. **Ouvrir les envois** permet au client de joindre d'autres fichiers à la demande transférée. Si un e-mail au client échoue, la demande affiche un avertissement — les données et décisions enregistrées ne sont pas affectées.

**Lien de dépôt (Nextcloud) :** si le plugin Nextcloud est configuré avec ses propres identifiants pour le canal de dépôt, le client ouvre sur la demande un lien de dépôt protégé par mot de passe. WorkDiary reprend les nouveaux fichiers toutes les 15 minutes ou via « Récupérer maintenant », les contrôle comme les envois du portail et révoque le lien après la dernière reprise dès que la demande n'accepte plus de fichiers ou que le lien expire. Les erreurs figurent sur le lien et dans l'historique ; les fichiers restent également dans Nextcloud.
