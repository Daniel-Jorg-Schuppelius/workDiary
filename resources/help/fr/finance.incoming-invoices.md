---
title: "Factures reçues"
topic: finance.incoming-invoices
version: 2
keywords:
    - facture fournisseur
    - facture d'achat
    - factures entrantes
    - boîte des factures
    - factures reçues
    - recevoir XRechnung
    - ZUGFeRD
    - Factur-X
    - valider une facture électronique
    - attribuer une facture
    - fournisseur collectif
    - client collectif
    - document non reconnu
    - validation des factures
    - comptes fournisseurs
    - libération du paiement
    - EN 16931
    - transfert vers Lexware
    - catégorie comptable
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

Les **factures reçues** (menu Facturation → Factures reçues) réceptionnent
les factures, les attribuent à des fournisseurs ou des clients et les font
passer par la vérification et la libération du paiement, sans toucher à la
souveraineté de facturation de votre logiciel de comptabilité ou de
facturation principal.

**Canaux de réception :** Les factures arrivent par la boîte des factures, le
téléversement de fichiers, Peppol ou le stockage cloud. Tous les canaux
suivent le même traitement : contrôle des doublons, contrôle de sécurité,
lecture de la facture électronique ou reconnaissance à partir du PDF ou de
l’image, validation et écarts. L’original inchangé est conservé comme
document de type facture dans la GED.

**Boîte des factures :** Sous Administration → Réception d'e-mails, une boîte
devient partie des factures reçues avec l’interrupteur « Boîte des
factures ». Chaque e-mail est évalué par facture, non par pièce jointe :

- Une XRechnung (XML) est l’original. Un PDF de la même facture envoyé avec
  elle est joint comme fichier d’accompagnement.
- Les autres pièces jointes, comme les conditions générales ou les bons de
  livraison, deviennent des fichiers d’accompagnement.
- Les logos intégrés et les images de signature ne sont pas traités.
- Si un e-mail ne contient aucune facture reconnaissable, la pièce jointe
  devient un **document non reconnu** : l’original est conservé et vous
  saisissez vous-même les valeurs.
- Les e-mails sans aucune facture jointe (par exemple seulement avec un lien
  de téléchargement) arrivent dans la boîte d’attribution de
  l’Administration, marqués comme boîte des factures.

**Reconnaissance :** Une facture électronique (XRechnung ou
ZUGFeRD/Factur-X) fournit des valeurs contraignantes. Pour le PDF et la
photo, les valeurs sont reconnues et constituent une proposition que vous
vérifiez sur l’original. Si une facture des échanges B2B nationaux n’est pas
une facture électronique, un avis s’affiche : cela n’est admis qu’à titre
transitoire, jusqu’à fin 2026 ou, pour les petits émetteurs, jusqu’à fin
2027. L’avis ne bloque rien ; les factures de petit montant jusqu’à 250 € en
sont exclues.

**Direction :** Si vous êtes l’acheteur, il s’agit d’un document entrant avec
un fournisseur comme contrepartie. Si vous êtes le vendeur — par exemple pour
des copies de factures d’une boutique ou d’une caisse, ou pour des avoirs en
autofacturation —, il s’agit d’un document sortant avec un client comme
contrepartie. Les documents sortants n’apparaissent jamais dans les
propositions de paiement, les lots de paiement ou les retenues. Si une
facture indique un acheteur tiers, la vérification signale « non adressée à
nous » ; la copie d’une de vos propres factures déjà enregistrée est
également signalée.

**Liste de travail :** Les onglets « À attribuer » et « À vérifier » affichent
tous les documents ouverts, « Tous » la période choisie. L’entrée de menu
compte les documents à attribuer. La comptabilité reçoit une notification le
matin tant qu’il reste quelque chose à attribuer.

**Attribution :** Un document n’est attribué automatiquement que si une seule
partie correspond à un identifiant exact du document : numéro de TVA,
numéro fiscal ou IBAN. Si plusieurs correspondent, le document reste à
attribuer et la vérification indique les candidats. Les identifiants propres
à votre organisation ne comptent jamais comme correspondance. Avec
« Attribuer », vous choisissez vous-même :

- une partie existante (les suggestions apparaissent en premier),
- le fournisseur collectif ou le client collectif,
- une nouvelle partie, préremplie à partir des données du document,
- « Pas une facture » — le document est refusé avec un motif.

Vous pouvez aussi y corriger la direction. Avec « Mémoriser l’expéditeur »,
le système attribue les futurs e-mails de cet expéditeur à la même partie,
tant que le document lui-même ne nomme pas une autre partie. Pour les
documents non reconnus et reconnus, saisissez numéro, date et montants avec
« Saisir les valeurs ». Attribuez ensemble dans la liste plusieurs documents
d’une même partie.

**Fournisseur collectif et client collectif :** Pour les fournisseurs et
clients occasionnels, chaque organisation dispose d’un contact collectif. Le
nom de la partie réelle reste sur le document. Un contact collectif n’est
jamais transmis à un système comptable comme contact propre, ne peut pas être
fusionné, ne reçoit ni accès au portail ni facture propre. Pour
l’autoliquidation (§ 13b), les opérations intracommunautaires et les pays
tiers, il est bloqué — il faut alors un vrai contact d’entreprise.

**Contrôle de l’IBAN :** Si l’IBAN de la facture diffère de toutes les
coordonnées bancaires enregistrées du fournisseur, la page le signale et le
paiement exige une confirmation. Les factures au fournisseur collectif
exigent toujours cette confirmation. Une facture ne modifie jamais les
données de base.

**Doublons :** Un contenu de fichier identique n’est enregistré qu’une seule
fois par organisation, y compris d’un canal à l’autre (un téléversement après
une réception par e-mail reste un doublon).

**Validation et cohérence :** Chaque facture électronique est validée par
rapport au schéma XML et, si configuré, aux règles KoSIT (EN 16931) ; la
disponibilité des contrôles est indiquée. En outre, le contrôle des écarts
avertit de manière visible — jamais silencieusement — d’un numéro de facture
déjà enregistré pour le même émetteur, de totaux contradictoires (HT + taxe ≠
TTC) et d’une taxe indiquée sans identifiant fiscal de l’émetteur.

**Workflow de vérification :** Un document est validé, mis en question ou
refusé (refus uniquement avec motif). La libération du paiement n’est
possible qu’après validation. Chaque décision est enregistrée avec la
personne et l’heure.

**Transfert à la comptabilité :** Si Lexware Office ou DATEV Unternehmen
online est connecté et que le transfert y est activé, un document part dès que
sa contrepartie est connue. La validation reste la condition du paiement, non
du transfert. Auparavant, le système vérifie : pas de document non reconnu
sans valeurs, pas de refus, adressé à nous, pas de copie d’une de vos propres
factures, totaux cohérents et pas de contact collectif pour l’autoliquidation,
les opérations intracommunautaires ou les pays tiers.

- Lexware Office reçoit le justificatif « à vérifier » avec l’original et,
  pour une XRechnung, aussi avec le PDF joint. Si un justificatif portant le
  même numéro existe déjà pour le même contact, il est seulement lié.
- Les montants ne sont envoyés qu’avec une catégorie comptable — sur le
  fournisseur ou le client, ou par défaut dans les paramètres du plugin —, en
  euros et avec les taux de TVA 0, 5, 7, 16 ou 19 %. Sinon, le justificatif
  part sans montants et la comptabilité les complète dans Lexware.
- DATEV Unternehmen online reçoit l’original comme image de justificatif.
- Un justificatif créé là-bas ne peut plus être supprimé par l’interface.
- N’envoyez pas en plus les mêmes factures à l’adresse e-mail des
  justificatifs de Lexware : les justificatifs reconnus là-bas n’ont d’abord
  pas de numéro et échappent au contrôle des doublons.

L’état par cible figure sur la page de détail ; les onglets « Transfert en
attente » et « Transfert échoué » regroupent ce qui bloque. Le système
réessaie les transferts échoués toutes les heures, jusqu’à cinq fois ;
« Réessayer » les relance à tout moment. Sans système comptable connecté,
« Transmettre à la comptabilité » consigne le transfert comme justificatif après
la validation ; un second appel ne change rien.

**Téléchargement XML :** Le XML de la facture peut être extrait de l’original
à tout moment (pour ZUGFeRD, de la pièce jointe PDF). Chaque téléchargement
est enregistré avec une somme de contrôle comme justificatif.
