---
title: "Transfert de facturation"
topic: finance.transfers
version: 8
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

**Forfaits :** si la règle de facturation du projet ou la prestation par défaut
de l'organisation indique l'unité « forfait » ou « Pauschale », chaque commande
donne une ligne de quantité 1 au prix de la prestation ; les temps de la commande
restent les sources de la ligne. Toutes les autres unités, par exemple heure, jour
ou pièce, comptent selon la durée. L'arrondi et les heures convenues ne
s'appliquent pas aux forfaits.

## Cas fiscal

Le transfert transmet le **cas fiscal** au système cible comme type de taxe.
Il est déterminé comme lors de la facturation à partir du client (pays,
numéro de TVA, consommateur, marqueur § 13b) et du lieu de prestation des
positions ; le matériel compte comme marchandise. Jusqu'à l'envoi, le
transfert l'affiche comme **Cas fiscal**.

| Cas fiscal | Lexoffice | sevDesk (mise à jour 2.0) | easybill |
| --- | --- | --- | --- |
| imposable | réglage par défaut (net/gross) | règle configurée | – |
| § 13b national | travaux de construction § 13b | règle 5 | autoliquidation § 13b (national) |
| prestation à une entreprise de l’UE | prestations externes § 13b UE | règle 21 (§ 18b) | non imposable, UE avec n° de TVA |
| livraison intracommunautaire | livraison intracommunautaire | règle 3 | livraison intracommunautaire |
| livraison à l’exportation | livraison à l’exportation | règle 2 | livraison à l’exportation |
| non imposable en Allemagne | prestation vers pays tiers | règle 17 | non imposable (pays tiers ou UE) |
| petite entreprise (§ 19) | exonéré | règle 11 | petite entreprise |

Les positions sans taxe partent à 0 %, la mention comme note fiscale du
document. Le transfert est **bloqué** avant l'envoi, avec le motif sur la
page et lors de **Transférer maintenant** :

- **orgaMAX** ne gère pas de type de taxe dans son interface ; hormis
  « imposable » et le § 19, le transfert reste ici.
- **Lexoffice** ne connaît que les pays tiers pour les prestations non
  imposables en Allemagne, pas par exemple un bien immobilier dans un autre
  pays de l’UE.
- **sevDesk** avec la comptabilité 1.0 ne connaît ni le § 13b, ni
  l'autoliquidation envers des entreprises de l’UE, ni l'exportation, ni le
  § 4.
- Si les positions donnent **plusieurs cas fiscaux** (par exemple des
  prestations de catalogue et générales à un consommateur d'un pays tiers),
  annulez le transfert et transférez séparément, ou facturez dans WorkDiary.

Le **paquet de fichiers** indique un cas fiscal sans taxe dans une ligne
d'en-tête.

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
