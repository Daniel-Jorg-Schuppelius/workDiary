---
title: "Transfert de facturation"
topic: finance.transfers
version: 5
keywords:
    - transfert vers Lexoffice
    - transfert DATEV
    - brouillon de facture
    - transférer les prestations
    - facturer le matériel
    - facturer les heures
    - export de facturation
    - logiciel de facturation
    - lignes de facture
    - documents liés
    - chaîne de documents
    - facture chez le prestataire
    - envoyer des justificatifs
    - dossier d’envoi
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

La remise de facturation transmet les **temps** et **matériaux**
facturables au système de facturation principal. Vous la trouvez dans
le menu sous **Remise de facturation** ; la page **Justificatifs de
transfert** liste tous les transferts.

Principe de base de la souveraineté de facturation : **la facture est
établie dans le programme externe principal** (par ex. Lexoffice,
orgaMAX, sevDesk, easybill ou DATEV) – WorkDiary se contente de fournir
des positions vérifiées accompagnées d'un justificatif de transfert. Une
facture locale dans WorkDiary n'existe que si aucun logiciel de
facturation externe n'est utilisé. Un seul **Canal de facturation**
s'applique par organisation ou par client.

Déroulement type :

1. **Préparer le transfert** (statut **Brouillon**) : choisir le
   **Client**, le **Canal de transfert** – **Prestations/temps** ou
   **Produits/matériel**, séparément –, la **Cible de transfert** et la
   **Période de prestation**. La cible est présélectionnée à partir du
   canal de facturation du client : **Lexoffice** (brouillon de
   facture), **orgaMAX (commande)**, **sevDesk (brouillon de facture)**
   ou **easybill (brouillon de facture)** ; l'**Export de fichier**
   reste toujours disponible. Si DATEV pilote, la remise se fait sous
   forme de paquet fichier (CSV) via l'export de fichier.
2. Vérifier les positions et **Confirmer le transfert** (statut
   **Confirmé**). Ce n'est qu'ensuite que le libellé et le texte de
   prestation peuvent être modifiés et que des positions peuvent être
   fusionnées ou retirées.
3. **Transférer maintenant** → statut **Transféré** (définitif). En cas
   d'**Échoué**, **Réessayer** remet le transfert au statut
   **Confirmé** ; vous transférez ensuite à nouveau.
4. Les transferts au statut **Brouillon** ou **Confirmé** peuvent être
   annulés avec **Annuler le transfert** – les positions concernées
   sont de nouveau libérées.

Risques et actions irréversibles :

- **« Transféré » est définitif** – les positions concernées sont
  verrouillées contre toute modification.
- Les corrections passent par des opérations traçables, jamais par une
  réinitialisation silencieuse : **Extourner le transfert** libère de
  nouveau les sources, **Créer une correction** génère un transfert de
  correction avec les mêmes temps. Un brouillon créé chez la cible
  n'est pas supprimé pour autant – retirez-le là-bas à la main.

Autorisations : les transferts de temps et de matériel sont protégés
séparément (**Préparer et transférer les temps facturables** ou
**Préparer et transférer le matériel facturable**). Les personnes
disposant de **Consulter les justificatifs de transfert** voient la
liste ; extourner et corriger exigent en plus **Gérer la configuration
financière**.

## Documents liés

En bas de page, **Documents liés** liste tous les documents rattachés à
celui-ci : le devis et ses versions, les factures, acomptes et la facture
finale, avoirs et annulations, bons de livraison, ordres de fabrication et
transmissions. Si un logiciel de comptabilité a repris un document, son
équivalent y figure avec le numéro du logiciel. Les liens signalés par le
logiciel lui-même, par exemple un acompte créé dans Lexoffice, apparaissent
aussi. La colonne **Lien** indique ce que le document représente pour
celui-ci ; « via … » nomme le document par lequel le lien existe.

## Facture chez le prestataire : justificatifs et envoi par WorkDiary

Si Lexware Office, sevDesk, easybill ou orgaMAX gèrent la facture, le numéro
et le contenu y restent. WorkDiary peut tout de même joindre des
justificatifs de prestations à la facture finalisée et l’envoyer, sans
facture propre.

1. Finalisez le brouillon dans le système de facturation.
2. Dans le justificatif de transfert, cliquez sur **Récupérer la facture
   depuis …** dans la carte **Facture chez le prestataire**. WorkDiary
   récupère le numéro, l’état et l’original et affecte les positions du
   transfert. Pour les factures scindées, regroupées ou créées directement
   dans le système de facturation, utilisez **Affecter une autre facture**.
3. Si les positions chez le prestataire diffèrent, confirmez l’écart sur la
   page de la facture avec un motif. Sans confirmation, l’envoi est bloqué.
4. Créez ou téléversez des justificatifs dans la carte **Pièces
   justificatives** et cliquez sur **Envoyer**.

WorkDiary complète l’original dans son format : un PDF part inchangé avec les
justificatifs en pièces jointes, une XRechnung les porte intégrés, un PDF
ZUGFeRD est réécrit avec le XML complété. Les originaux signés ne sont jamais
modifiés. Le **dossier d’envoi** conserve chaque version avec empreintes,
justificatifs et tentatives d’envoi. **Réessayer** envoie les mêmes
fichiers ; si une tentative reste sans réponse, vérifiez avec le destinataire
s’il l’a reçue. Les justificatifs créés plus tard partent via **Envoyer des
justificatifs complémentaires**.

Cela suppose le canal d’envoi « WorkDiary envoie » dans la fiche client ou
les paramètres de l’organisation. Désactivez alors l’envoi automatique dans
le système de facturation, sinon le client reçoit la facture deux fois.
