---
title: "Rapprochement des paiements"
topic: finance.reconciliation
version: 5
keywords:
    - rapprochement bancaire
    - importer un relevé
    - lettrage
    - encaissements
    - facture payée
    - opérations bancaires
    - CAMT
    - MT940
    - escompte
    - paiement partiel
    - référence RF
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Le **Rapprochement des paiements** importe des relevés bancaires au format
**CAMT.053** (de préférence) ou **MT940** (solution de repli), normalise
les opérations bancaires dans une **zone de vérification** et propose des
factures ouvertes ou des notes de frais validées pour l'affectation.
**L'import seul ne modifie aucune pièce** – seule la **confirmation** fait
passer `Facture → payée` (avec la date de paiement) ou marque une note de
frais comme remboursée.

## Déroulement

1. **Importer :** téléverser le fichier bancaire (en option, choisir un de
   vos propres comptes bancaires ; sinon, il est affecté automatiquement
   via l'IBAN). Les fichiers identiques sont rejetés comme doublons grâce
   au hash du fichier ; les opérations déjà connues sont ignorées lors d'un
   nouvel import.
2. **Vérifier :** dans le détail du relevé, chaque opération affiche un
   statut (Ouvert/Affecté/Mis de côté/Non affectable) et – si elle est
   ouverte – des **Suggestions d’affectation** avec un score de
   correspondance et une justification (numéro de facture, montant,
   escompte, correspondance IBAN, proximité de date).
3. **Confirmer :** avec *Confirmer*, l'affectation est créée et son effet
   est appliqué à la pièce. Sinon, *Mettre de côté* (par ex. frais
   bancaires) ou *Non affectable*.
4. **Annuler :** une affectation confirmée est **réversible** – elle est
   levée et l'effet sur la pièce (payée/remboursée) n'est annulé que si
   cette opération constituait le paiement. **L'opération bancaire
   elle-même n'est jamais modifiée.**

## Cas pratiques

- **Rechercher une pièce :** si aucune suggestion ne convient, **Rechercher
  une pièce** sur le mouvement ouvert affiche la liste de toutes les factures
  ouvertes, des frais approuvés et des comptes clients. Vous choisissez la
  pièce, le montant (inférieur = paiement partiel, supérieur = trop-perçu),
  éventuellement le type et une note.
- **Mouvement sans pièce :** avec la comptabilité locale, vous comptabilisez
  des frais bancaires, des intérêts, un prélèvement privé ou une compensation
  directement banque à compte général avec **Comptabiliser sur un compte
  général**, éventuellement avec code de taxe (le mouvement est le montant
  TTC) et centre de coûts. Le mouvement est ensuite considéré comme mis de
  côté et comptabilisé. **Comptabiliser sur le compte d'attente** et le
  virement interne restent disponibles.
- **Escompte :** un paiement insuffisant dans la limite de la tolérance
  d'escompte est considéré comme un paiement complet avec escompte. Si la
  facture comporte ses propres conditions d'escompte, leur montant et leur
  délai s'appliquent ; sinon la tolérance d'escompte de l'organisation (3 % par
  défaut).
- **Tolérance de centimes :** des écarts d'arrondi jusqu'à la tolérance de
  centimes de l'organisation (0,02 par défaut) n'empêchent pas une suggestion.
  Vous réglez les deux tolérances dans les paramètres de l'organisation sous
  **Rapprochement bancaire**.
- **Frais :** les prélèvements ne sont proposés pour le remboursement que de
  frais validés payés à titre privé ; carte d'entreprise, caisse et virement
  bancaire sont des fonds de l'entreprise.
- **Paiement partiel/trop-perçu :** ils sont gérés comme un type
  d'affectation distinct ; en cas de paiement partiel, la facture reste
  ouverte.
- **Chaîne des soldes :** le solde d'ouverture + la somme des opérations
  est contrôlé par rapport au solde de clôture ; les écarts sont signalés
  par un avertissement.
- **Devise étrangère :** les opérations dans une autre devise sont
  uniquement détectées et marquées pour une clarification manuelle.

## Protection des données

Les données bancaires à caractère personnel (nom, IBAN, motif du paiement
de la contrepartie) sont stockées sous forme **chiffrée**. Le
rapprochement s'effectue exclusivement à partir de dérivés non chiffrés
(hash de l'IBAN, numéros de facture extraits, montants, dates). Chaque
action d'affectation est consignée de manière probante dans une chaîne
de hachage.

## Autorisations

- **Importer un fichier bancaire** et **confirmer/annuler des
  affectations :** rôle *Comptabilité* (ainsi que l'administration).
- **Gérer ses propres comptes bancaires :** administration uniquement.

## Référence de paiement RF

Lorsque la référence de paiement RF est activée dans les paramètres de
l'organisation, chaque facture porte une référence créancier RF
(ISO 11649) issue de son numéro — dans les informations de paiement et
dans le GiroCode sous forme de référence structurée. Si le client effectue
son virement avec cette référence, le rapprochement des paiements
reconnaît la facture grâce à elle, même écrite par groupes de quatre.

## Relevés provisoires et lien avec le relevé précédent

**CAMT.052** (relevé intrajournalier), **CAMT.054** (avis d’opération), **MT942** (relevé intermédiaire) ainsi que vos propres ordres de paiement **PAIN.001/PAIN.008** sont importés comme **provisoires**. Leurs opérations figurent dans le relevé à titre d’aperçu, mais ne sont ni rapprochées ni comptabilisées et ne comptent ni dans la trésorerie ni dans les indicateurs. À l’arrivée du relevé définitif (**CAMT.053** ou **MT940**) du même compte, les relevés provisoires jusqu’à sa date de fin sont considérés comme **remplacés** ; seul le relevé définitif est rapproché.

Chaque relevé définitif est en outre contrôlé par rapport à son **relevé précédent** du même compte : le solde d’ouverture doit correspondre au solde de clôture du précédent, et aucun jour ouvré ne doit manquer entre les deux. La liste l’indique sous **Lien avec le relevé précédent** (suit, lacune, ne correspond pas, premier relevé). Une lacune signifie généralement qu’un relevé n’a pas encore été importé ; une fois ajouté, le lien du relevé suivant est de nouveau contrôlé.
