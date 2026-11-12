---
title: "Design des documents"
topic: admin.document-design
version: 4
keywords:
    - papier à en-tête
    - en-tête
    - mise en page facture
    - modèle de facture
    - mise en page PDF
    - personnaliser la mise en page
    - charte graphique
    - police
    - marges
    - fenêtre adresse
    - pied de page
    - modèle de document
audience:
    - admin
modules:
    - module.dokumentdesign
related:
    - admin.branding
    - invoices.manage
---

Le design des documents adapte les PDF générés à l'apparence de
votre organisation : papier à en-tête, zones d'impression et zones
bloquées, blocs d'information et préréglage de style de tableau.

Déroulement :

1. **Téléverser un papier à en-tête** (PDF, JPG ou PNG, A4 portrait) — un
   asset pour la première page et, en option, un pour les pages suivantes.
   Les PDF sont réduits à une page matricielle sûre et non interactive ;
   l'original est conservé comme preuve.
2. **Créer un profil** et définir dans l'éditeur les zones d'impression,
   la fenêtre d'adresse, la ligne d'expéditeur et les zones bloquées en
   millimètres — visuellement ou numériquement, clavier compris.
3. **Déclarer les blocs d'information** : `dynamique` (WorkDiary imprime),
   `fourni par le papier à en-tête` (avec confirmation par version de
   profil) ou `non applicable`. Les blocs obligatoires des types de
   documents attribués et les données variables sont protégés.
4. **Générer un document de test** par type de document avec textes longs,
   nombreuses positions et plusieurs taux de taxe ; le preflight montre les
   chevauchements, les blocs obligatoires manquants et les problèmes de
   contraste.
5. **Activer la version** — uniquement avec un preflight sans erreur. Les
   versions activées sont immuables ; les modifications passent par un
   nouveau brouillon. Les documents finalisés conservent leur état figé.

Sans profil, la valeur système par défaut (sortie actuelle) s'applique.
Les factures ZUGFeRD/PDF-A-3 restent valides après application du design —
la facture structurée reste déterminante.

Design de base CI et héritage :

- Le profil standard de l'organisation est votre **design de base CI**.
  Les variantes pour des sortes de documents individuelles (p. ex.
  devis, facture, avoir, relance) ou des familles entières (ventes,
  achats, justificatifs) **héritent** de toutes les sections non
  remplacées — chaque section indique si elle est héritée ou
  remplacée ; « réinitialiser sur le design de base » supprime le
  remplacement. La variante la plus spécifique l'emporte : sorte avant
  famille avant design de base.
- L'**aperçu PDF intégré** de l'éditeur utilise la même pipeline que la
  sortie finale ; la sorte de document et les données d'exemple (textes
  longs, nombreuses positions, plusieurs taux de TVA) sont
  commutables.
- **La famille de polices et la taille de base** proviennent d'une liste
  sélectionnée compatible PDF ; les couleurs primaire/d'accent peuvent
  **référencer le branding de l'organisation** — les modifications du
  branding s'appliquent alors automatiquement, sans copie de couleur
  dans le profil.
- À l'activation, le design de base est vérifié contre les blocs
  obligatoires de TOUTES les sortes de documents personnalisables ; les
  vrais formats spéciaux (p. ex. étiquettes) déclarent leur restriction
  dans le registre central des sortes de documents.
- Les **textes d'en-tête/pied** des documents de vente (anciennement
  modèles de facture) forment leur propre section de profil héritable —
  versionnée et gelée pour les documents finalisés. Les **designs
  spécifiques aux clients** sont des profils réguliers attribués dans le
  dossier client (panneau « Design de documents ») ; les profils marqués
  « spécifique au client » n'agissent que par cette attribution.
- **Finitions :** l'héritage s'applique par groupe de réglages (marges,
  fenêtre d'adresse, zones bloquées, lignes d'en-tête/pied, typographie,
  papier à en-tête, blocs, style de tableau, textes). Les **avertissements**
  du preflight bloquent l'activation jusqu'à confirmation consciente dans le
  dialogue. Nouveau également : **lignes d'en-tête/pied** par page et tous
  les réglages du style de tableau (quadrillage, espacements, couleurs,
  répétition d'en-tête, mise en valeur des totaux).

Prise en main et aperçu du papier à en-tête (retouche 6) :

- Tant que les cinq étapes ne sont pas terminées, la vue d'ensemble
  affiche une **liste de contrôle** (téléverser le papier à en-tête →
  créer un profil → concevoir dans l'éditeur → activer la version →
  attribuer les types de document) avec accès direct au dialogue ou à
  l'onglet d'éditeur correspondant. Le flux de documents et la page de
  branding renvoient ici.
- Chaque papier à en-tête a une **vignette** et un dialogue
  **Afficher** — aussi en « contrôle requis » : l'original (image) et les
  remarques de contrôle sont alors affichés ; l'**original** est toujours
  téléchargeable (les PDF uniquement en téléchargement, jamais dans le
  visualiseur du navigateur).
- L'éditeur regroupe la colonne de droite dans les onglets **Apparence**
  (papier à en-tête, style de tableau), **Mise en page** (zones
  d'impression, fenêtres, zones bloquées, typographie), **Contenus**
  (blocs d'information, textes d'en-tête/pied) et **Publication**
  (documents de test, attribution, versions). La sélection du papier à
  en-tête apparaît immédiatement dans l'aperçu A4 et est enregistrée avec
  « Enregistrer le brouillon ».

Blocs d'information sur le document :

- Facture, avoir, pro forma, devis, confirmation de commande, relance et
  avis de cotisation impriment les blocs selon leur état. **Dynamique**
  imprime les **coordonnées de l'entreprise** (nom, adresse, registre,
  mention légale), les **données fiscales** (n° de TVA, numéro fiscal), le
  **contact** (commercial de la facture ou auteur du devis, sinon les
  coordonnées de l'organisation), l'**introduction** et la **conclusion**
  (texte d'en-tête et de pied du profil) ainsi que la **mention de
  confidentialité** (texte propre dans l'onglet Contenus, vide = mention par
  défaut). « Déjà sur le papier à en-tête » et « non applicable » omettent
  le bloc.
- Les coordonnées de l'entreprise et les données fiscales proviennent des
  données vendeur de la facture électronique, à défaut du branding et des
  mentions légales de l'organisation ; le PDF visible ne contredit donc pas
  la facture structurée.
- Le contrôle préalable ne considère les coordonnées et données fiscales
  « dynamiques » comme remplies que si les données sont renseignées. Sinon,
  c'est une erreur pour les documents de vente attribués et un avertissement
  pour le design de base.
- Les factures émises restent inchangées : les données de l'émetteur et le
  contact sont figés à l'émission ; les documents antérieurs à ces blocs ne
  les impriment pas, même lors d'une réimpression.

## Coordonnées de l'entreprise sur les commandes, bons de livraison et procès-verbaux

Ces pièces impriment elles aussi les coordonnées de l'entreprise et les données
fiscales selon l'état de bloc du design ; cela comprend les métrés et les
attestations d'élimination, conçus comme des procès-verbaux. Si le bloc obligatoire
« Coordonnées de l'entreprise » est sur « dynamique », le contrôle préalable vérifie
que le nom et l'adresse complète sont renseignés — comme pour les factures et les
devis. Les justificatifs signés ou clôturés auparavant restent inchangés.

## Coordonnées de l'entreprise sur les justificatifs, certificats et courriers de chantier

Chaque type de document avec le bloc obligatoire « Coordonnées de l'entreprise »
l'imprime selon l'état du bloc, et le contrôle préalable vérifie ses données : outre
les pièces ci-dessus, le justificatif de fabrication, l'avis d'empêchement et
l'avis de réserves, le certificat, le bulletin et l'attestation de grade, le
justificatif de clôture d'une signature de contrat, le justificatif d'instruction
et l'évaluation des risques, ainsi que la facture commerciale et la facture
pro forma pour la douane. Le design est figé à l'envoi, à l'émission, à la première
signature, à la validation ou à la clôture ; les courriers et justificatifs envoyés,
émis, signés ou clôturés auparavant restent inchangés. Feuille de temps, formulaire,
rapport, dossier, justificatif de prestations et étiquette n'ont pas de bloc
entreprise ; le reçu de don suit le modèle officiel avec son propre bloc émetteur.
