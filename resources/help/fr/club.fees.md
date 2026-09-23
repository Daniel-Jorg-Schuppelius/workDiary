---
title: "Cotisations"
topic: club.fees
version: 1
audience: []
modules:
    - module.club
related:
    - club.members
    - club.groups
---

La gestion des cotisations fait partie de la base associative et fonctionne sans graduations. Elle requiert le droit « gérer les cotisations » (trésorerie) ; l'administration lit, les responsables de groupe ne voient aucune donnée de cotisation.

**Tarifs et taux :** Les tarifs sont librement nommés (p. ex. adultes, enfants, réduit, passif, famille) et portent leurs montants comme taux par date de validité : rythme (mensuel, trimestriel, semestriel, annuel), montant, ancre de facturation (mois de début de période), échéance en jours après le début de période, règle de prorata et droit d'entrée optionnel. Les nouveaux taux ne modifient pas les créances déjà validées. Les suppléments de section sont des positions distinctes pour les membres ayant une affectation active à un groupe de la section. Il n'y a aucun taux associatif intégré.

**Comptes de cotisation et payeurs :** Un compte de cotisation est la personne redevable dans le fichier clients/débiteurs. Un parent peut payer pour plusieurs enfants sans être membre. L'affectation membre → compte et tarif est explicite avec une période ; jamais automatique par correspondance d'e-mail ou d'IBAN. Par membre, au plus une affectation à la fois.

**Cotisation familiale :** Un tarif famille est un tarif fixe par foyer : tous les membres affectés au compte avec ce tarif donnent exactement une position de cotisation de base par période. Sinon, les cotisations individuelles restent avec une remise explicite en pourcentage et un motif. Il n'y a pas de cotisation de base familiale et individuelle complète en même temps.

**Prorata :** Chaque taux est « période complète » ou « au jour ». Au jour compte les jours civils actifs de la période (affectation, adhésion/départ) divisés par les jours de la période ; chaque position est arrondie au centime. Une adhésion en milieu de mois donne ainsi la part correspondante.

**Exonérations :** Les exonérations et réductions sont saisies expressément avec période et motif. Une pause d'adhésion seule n'exonère d'aucune cotisation.

**Limites d'âge :** Les tarifs peuvent porter des limites d'âge. Si un membre ne correspond plus à la date de référence, le contrôle quotidien marque l'affectation pour vérification ; la gestion des cotisations confirme le changement avec une date d'effet ou conserve le tarif. Rien ne change automatiquement.

**Aperçu :** L'aperçu des cotisations montre pour un mois de facturation toutes les positions des périodes commençant ce mois-là avec leur base de calcul. Les affectations incomplètes ou tarifs sans taux apparaissent comme erreurs, jamais omis en silence. Les créances ne naissent qu'avec le traitement des cotisations.

**Traitement et créances :** Un traitement fige l'aperçu d'un mois de facturation (toutes les périodes commençant ce mois-là). Les erreurs de l'aperçu bloquent la validation. La validation crée exactement une créance avec positions par compte de cotisation ; répétition, rattrapage et traitement parallèle ne créent aucun doublon, car chaque source et période ne peut être réclamée qu'une fois. Les montants validés ne changent pas avec des modifications ultérieures de tarif ou de famille. Si la facturation est externe (p. ex. Lexoffice), l'aperçu et la liste de transfert restent possibles ; la validation locale est bloquée.

**Avis de cotisation :** Chaque créance dispose d'un avis PDF avec période, détail, échéance et référence de paiement (le numéro de la créance). L'envoi par e-mail est distinct de la validation ; chaque tentative reçoit un justificatif d'envoi, les échecs restent visibles. Le texte de pied (p. ex. mention sur l'affectation fiscale/comptable) se définit dans les paramètres de l'association.

**Postes ouverts, annulation, correction :** Les créances sont ouvertes, partiellement payées, payées ou annulées ; le retard découle de l'échéance et du reste dû. Une créance non payée peut être annulée avec motif, les périodes redeviennent libres pour un nouveau traitement. Les corrections sont des créances distinctes et liées (complément ou avoir) ; les montants validés ne sont jamais écrasés. Un départ met fin aux cotisations futures sans supprimer les postes ouverts existants.

**Paiements :** Les paiements sont des écritures propres au compte de cotisation (virement, espèces, prélèvement SEPA, autre). Sans créance choisie, ils sont imputés par échéance — un paiement groupé de la famille couvre plusieurs créances ; un reste devient un avoir imputable sur des créances ultérieures. Le même argent n'est jamais compté deux fois : si le rapprochement bancaire trouve un crédit dont le montant est déjà enregistré manuellement ou par prélèvement, il relie l'opération à ce paiement au lieu d'en créer un second. Annuler un rapprochement ne reprend que le paiement bancaire ; un paiement saisi manuellement reste.

**Rejet de prélèvement :** Un rejet compense exactement un paiement (une seule fois), rouvre le reste et bloque un nouveau prélèvement jusqu'à ce que la gestion des cotisations lève le blocage explicitement. Des frais bancaires sont créés comme créance complémentaire liée ; la créance initiale reste inchangée.

**Relance :** Seules les créances en retard et non bloquées sont relancées, en trois niveaux au plus (rappel, relance, dernière relance). Délai et frais sont fixés volontairement par relance, pas repris des valeurs par défaut des factures ; des frais de relance forment une créance liée propre. La relance existe en PDF et par e-mail avec preuve d'envoi. Les créances contestées ou différées reçoivent un blocage de relance avec motif.

**Prélèvement SEPA :** La proposition de prélèvement liste les restes dus échus des comptes avec mandat utilisable (le mandat actif du client ou celui fixé sur le compte). Un lot de prélèvement est un lot du module finances : validation et export pain.008 s'y font. Chaque tentative reçoit une référence unique (numéro de créance et de tentative), le lot réserve la position contre un nouveau prélèvement. L'export n'est pas un paiement — seul « Enregistrer l'encaissement » après le crédit passe la créance en payée. Sans le paquet finances, paiements, relance et préparation restent possibles ; seul l'export SEPA ne l'est pas.
