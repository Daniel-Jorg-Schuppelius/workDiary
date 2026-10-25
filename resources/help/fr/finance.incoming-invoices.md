---
title: "Factures reçues"
topic: finance.incoming-invoices
version: 5
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
    - doublon
    - facture en double
    - dossier des factures
    - aperçu de la facture
    - corriger la reconnaissance
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

**Boîte des factures :** Sous Administration → Données et interfaces → Réception
d'e-mails, ouvrez la boîte via « Modifier » (ou créez-en une nouvelle) et
activez dans la boîte de dialogue, sous « Utilisation », l’interrupteur « Boîte
des factures ». La liste des boîtes affiche ensuite le badge « Factures ».
Chaque e-mail est évalué par facture, non par pièce jointe :

- Une XRechnung (XML) est l’original. Un PDF de la même facture envoyé avec
  elle est joint comme fichier d’accompagnement.
- Les autres pièces jointes, comme les conditions générales ou les bons de
  livraison, deviennent des fichiers d’accompagnement.
- Les logos intégrés et les images de signature ne sont pas traités.
- Si un e-mail ne contient aucune facture reconnaissable, la pièce jointe
  devient un **document non reconnu** : l’original est conservé et vous
  saisissez vous-même les valeurs.
- Si la reconnaissance de texte manquait au serveur à l’arrivée de l’e-mail,
  l’action groupée « Reconnaître à nouveau » dans la liste des documents à
  attribuer répète la reconnaissance des documents non reconnus sélectionnés
  à partir de l’original conservé. Les valeurs saisies, les documents transmis
  et refusés restent inchangés.
- Si le XML et la facture PDF d’une même facture arrivent dans un e-mail, le
  XML reste l’original et le PDF devient un fichier d’accompagnement, même si
  le XML porte un numéro plus long. Si le XML n’indique ni vendeur, ni numéro
  de TVA, ni coordonnées bancaires, le PDF fournit ces valeurs ; la page de
  détail nomme les champs complétés.
- « Vérifier à nouveau » répète la vérification du schéma et de KoSIT des
  factures électroniques sur l’original conservé, par exemple si le
  validateur manquait à la réception. Les valeurs, l’attribution et la
  transmission restent inchangées.
- « Approuver » comme action groupée dans l’onglet « À vérifier » approuve les
  documents sélectionnés. Les documents sans partie, avec un statut non admis
  ou bloqués par le contrôle de transmission (totaux, adressage, doublon
  possible) restent inchangés ; le message indique les raisons.
- Votre propre organisation n’est jamais la contrepartie : les expéditeurs et
  domaines de vos propres boîtes aux lettres, comptes utilisateurs et données
  de base de facturation électronique ne comptent ni comme suggestion ni comme
  fiche similaire, même pour les e-mails transférés.
- Les e-mails sans aucune facture jointe (par exemple seulement avec un lien
  de téléchargement) arrivent dans la boîte d’attribution de
  l’Administration, marqués comme boîte des factures.
- Un e-mail transféré en pièce jointe est ouvert ; la facture qu’il contient
  compte comme une pièce jointe directe.

**Relève et e-mails existants :** Les e-mails sont relevés à partir du jour
indiqué sous « Relever à partir du », selon l’état de relève — y compris ceux
déjà lus. Choisissez de préférence un dossier dédié dans lequel une règle
classe les factures (sous-dossiers séparés par « / », p. ex. Boîte de
réception/Factures). « Récupérer les anciens e-mails » dans l’administration
des boîtes récupère les factures plus anciennes ; une même facture n’est pas
créée deux fois. Si un e-mail de facture se trouve dans une autre boîte, «
Reprendre comme facture entrante » dans la boîte d’affectation reprend ses
pièces jointes.

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
documents non reconnus, saisissez numéro, date et montants avec « Saisir les
valeurs » ; modifiez les valeurs reconnues ou déjà saisies avec « Corriger les
valeurs », aussi directement dans la carte « Données de facture ». Attribuez
ensemble dans la liste plusieurs documents d’une même partie.

**Original à côté des valeurs :** la page de détail affiche l’original à côté
des données de facture, pour que les erreurs de reconnaissance sautent aux
yeux : PDF et photos (JPEG, PNG, GIF, WebP) tels que reçus, scans TIFF en PDF,
une XRechnung ou le XML d’une facture électronique sous forme de facture
lisible. L’original reste en place pendant le défilement ; « Ouvrir dans un
nouvel onglet » l’affiche en grand. La boîte de dialogue « Corriger les
valeurs » l’affiche aussi à côté des champs. Si un scan TIFF ne peut pas être
converti, une remarque s’affiche ; ouvrez alors l’original via « Original
(XML/PDF) ». La consultation d’un document confidentiel est journalisée comme
un téléchargement. Les valeurs d’une facture électronique ne peuvent pas être
modifiées ; son XML est l’original.

Avec « Traiter de la même façon les autres documents ouverts », vous réglez
tous les documents ouverts de la même contrepartie en une étape, à
l’attribution comme au refus. Sous « Pas une facture », vous décidez du sort
des futurs documents de cette contrepartie : « classer automatiquement comme
pas une facture » ou « ne pas prendre en charge (rejeter et supprimer) ». La
suppression retire le document, son fichier et ses pièces jointes ; l’entrée
d’audit avec somme de contrôle, numéro, vendeur, expéditeur et motif est
conservée. WorkDiary crée pour cela une règle de boîte de réception.

Les **règles de boîte de réception** sont des règles d’automatisation du
déclencheur « Facture entrante reçue ». Vous les consultez via « Règles » dans
le menu de la page, avec les règles d’expéditeur, les désactivez, les activez
et les supprimez ; les administrateurs modifient conditions et actions sur la
page des automatisations. Les conditions vérifient entre autres le vendeur
(`seller_name_key`, normalisé), le numéro de TVA (`seller_vat_id`),
l’expéditeur (`sender_email`, `sender_domain`), l’objet (`mail_subject`), les
noms des pièces jointes (`attachment_names`), le nom de fichier (`file_name`),
le pays (`country`), le montant TTC (`amount_gross`) et la reconnaissance
(`recognition`) ; les opérateurs comprennent « est », « contient », « commence
par », « se termine par » et « correspond à » (expression régulière). Actions :
rejeter et supprimer, classer comme pas une facture, attribuer à une partie. La
première règle correspondante l’emporte ; les règles s’appliquent à la
relève, à l’import et à la nouvelle reconnaissance.

Les documents refusés figurent dans l’onglet « Refusé », où vous pouvez les
supprimer définitivement. Après le délai configuré (30 jours par défaut), la
conservation propose leur destruction ; la suppression n’a lieu qu’après
validation.

**Reconnaissance des PDF et des scans :** WorkDiary lit les libellés et les
valeurs même lorsqu’ils se trouvent dans des colonnes séparées ou dans une
ligne d’en-tête au-dessus de la ligne de valeurs. Sans date de facture, « Lieu,
date » de l’en-tête ou un simple « Date » compte ; une date de livraison n’est
que proposée et signalée dans la boîte de dialogue « Saisir les valeurs ». Les
relances, bons de livraison, confirmations de commande, devis et étiquettes
d’expédition sont reconnus comme tels : la liste affiche « Ressemble à », la
boîte d’attribution présélectionne « Pas une facture », et les relances
nomment les factures concernées avec un lien. Les administrateurs mesurent la
reconnaissance avec `incoming-invoices:measure-recognition` sur les textes
enregistrés des documents.

**Repli IA :** si une connexion est configurée sous **Administration →
Services d'IA** et que la capacité « Factures entrantes : lire les champs de la
pièce » est activée, l'IA ne complète que les champs principaux manquants
(numéro, date, échéance, montants, taux de TVA, référence acheteur) —
vérifiés quant au format et à la plausibilité, jamais pour les factures
électroniques et jamais sans votre contrôle. Sans connexion, la
reconnaissance fonctionne comme avant. S'il manque l'index de texte d'une
pièce (par exemple après une panne des outils de texte),
`search:extract-texts --retry-failed` le relit.

**Modèles de document :** Via « Modèle » sur la page du document, vous tracez,
pour chaque champ, un rectangle sur le libellé et la valeur du document rendu
(numéro de facture, date, échéance, montants, numéro de TVA). Le libellé
devient l’ancre : la valeur est lue à côté, quelle que soit la page et la
hauteur, car les blocs de totaux se déplacent avec le nombre de lignes. Sans
libellé, la zone reste à position fixe. Le rectangle reste tel que tracé et se déplace vers l’ancre trouvée lors de
l’application ; désactivez l’ancre par zone si la position doit rester fixe.
Pour chaque zone, vous choisissez la portée (page fixe, première, dernière ou
toute page) et pouvez ajouter un motif de ligne pour les cas particuliers
(expression régulière sur le texte du rectangle, ligne par ligne ; le premier
groupe capturant est la valeur, par exemple pour extraire une valeur précise
d’un rectangle plus grand). L’éditeur
affiche les trois premières pages et la dernière. Dans le bloc
d’enregistrement, vous choisissez le modèle à modifier ou en créez un ; la
liste des zones devient l’état de ce modèle, les zones retirées disparaissent
à l’enregistrement, et le modèle mémorise la forme d’en-tête du document
comme variante supplémentaire. L’essai vérifie les zones
tracées sur les documents ouverts de même forme, sans enregistrer. Le
fournisseur du modèle est attribué à chaque document reconnu et en est le
vendeur. Le modèle est enregistré avec une empreinte de l’en-tête et du pied
de la première page ; il ne remplit que des factures, jamais des relances ou
bons de livraison du même expéditeur, et seulement des valeurs plausibles
(pas de pourcentage comme montant, pas de date comme numéro). Chaque
correction dans « Saisir les valeurs » apprend aussi : la valeur saisie est
localisée sur le document et mémorisée comme zone avec son libellé ; après la
deuxième correspondance, une zone apprise est considérée comme confirmée et
l’emporte sur l’heuristique. La page « Modèles de document » (via « Règles »)
liste tous les modèles avec champs, occurrences et dernier document reconnu ;
vous y renommez, définissez le fournisseur, ouvrez l’éditeur de zones, lancez
un essai, fusionnez les doublons et supprimez. Les zones n’existent que pour
les documents PDF. Si un document n’a aucun numéro de facture, le numéro de
commande le remplace avec un avertissement.

**Corriger une affectation et action groupée :** L’action groupée exige un
choix ; « Nouveau d’après le document (par contrepartie) » crée une fiche par
contrepartie d’après le premier document ou reprend une partie existante avec
le même numéro de TVA ou le même nom, le contact collectif vient en dernier et
demande confirmation avec les groupes de la sélection. Supprimez une
affectation dans la boîte de dialogue via « Supprimer l’affectation » ou en
action groupée tant que le document n’est pas transmis ; déplacer revient à
affecter de nouveau, aussi dans les onglets « À vérifier » et « Tous ». Sous
« Règles », « En attente au contact collectif » liste les documents du
fournisseur ou client collectif par contrepartie ; « Créer et déplacer » crée
ou reprend la partie et déplace les documents ouverts. Si un logiciel comptable
est connecté (Lexware Office, easybill, sevDesk, orgaMAX), la boîte de dialogue
y cherche par e-mail et nom et propose les correspondances comme « Reprendre de
la comptabilité » : la fiche est créée d’après le contact et liée avec son
numéro. sevDesk ne fournit ni e-mail ni adresse, les fournisseurs orgaMAX pas
de numéro de TVA. Avant « Nouveau d’après le document », la boîte de dialogue
signale les fiches similaires ; une partie existante avec le même numéro de
TVA bloque la création. Si la comptabilité gère les fiches, la boîte de
dialogue indique que la nouvelle fiche n’est pas transmise.

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

**Doublon possible :** Si la même facture arrive une seconde fois sous un
autre fichier — par exemple en PDF par e-mail et en XRechnung via Peppol —, la
réception des factures le reconnaît au numéro de facture associé à
l’identifiant fiscal de l’émetteur ou à la partie attribuée. La réception
ultérieure n’est pas transmise à la comptabilité tant que vous ne confirmez
pas « Pas de doublon » ou ne la refusez pas. Un numéro seul ne suffit pas : «
1001 » existe chez de nombreux fournisseurs.

**Validation et cohérence :** Chaque facture électronique est validée par
rapport au schéma XML et, si configuré, aux règles KoSIT (EN 16931) ; la
disponibilité des contrôles est indiquée. En outre, le contrôle des écarts
avertit de manière visible — jamais silencieusement — de totaux
contradictoires (HT + taxe ≠ TTC) et d’une taxe indiquée sans identifiant
fiscal de l’émetteur.

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
- Si le numéro de facture manque, le transfert recherche dans Lexware par
  contact, date du justificatif et montant. Une correspondance n’est pas liée
  automatiquement : vous choisissez « Lier à ce justificatif » ou « Autre
  justificatif – créer ».
- L’interface ne permet pas de rechercher les justificatifs existants dans
  DATEV. N’envoyez donc pas en plus les mêmes factures par l’e-mail de
  téléversement DATEV ; la protection contre les doublons y repose uniquement
  sur la réception des factures.
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
