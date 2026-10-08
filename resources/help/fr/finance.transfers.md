---
title: "Transfert de facturation"
topic: finance.transfers
version: 3
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
