<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Envoie et reçoit des documents via un point d’accès Peppol certifié. WorkDiary n’exploite pas de point d’accès : les points de terminaison et noms de champs du prestataire se configurent ici.',
    ],
    'settings' => [
        'base_url' => 'URL de base du prestataire',
        'base_url_help' => 'Racine de l’API du prestataire, p. ex. https://api.example-ap.eu/v1 — sans barre oblique finale.',
        'api_key' => 'Clé d’accès',
        'api_key_help' => 'Stockée chiffrée et masquée dans les journaux.',
        'auth_header' => 'En-tête d’authentification',
        'auth_header_help' => 'En-tête transportant la clé (par défaut : Authorization).',
        'auth_scheme' => 'Préfixe d’authentification',
        'auth_scheme_help' => 'Préfixe tel que Bearer. Laisser vide si le prestataire attend la clé seule.',
        'send_path' => 'Point de terminaison d’envoi (chemin)',
        'receive_path' => 'Point de terminaison de réception (chemin)',
        'ack_path' => 'Point de terminaison d’accusé (chemin)',
        'ack_path_help' => 'L’espace réservé {messageId} est remplacé par l’identifiant du message ; sans lui, l’identifiant part dans le corps.',
        'health_path' => 'Point de terminaison d’état (chemin)',
        'payload_field' => 'Nom du champ de l’enveloppe',
        'payload_field_help' => 'Champ JSON contenant l’enveloppe SBDH. Laisser vide si le prestataire attend du XML brut.',
        'message_id_field' => 'Nom du champ de l’identifiant de message',
        'status_field' => 'Nom du champ du statut de transport',
        'items_field' => 'Nom du champ de la liste de réception',
        'sender_participant_id' => 'Identifiant de participant Peppol propre',
        'sender_participant_id_help' => 'Format <ICD>:<identifiant>, p. ex. 9930:DE123456789. Doit être enregistré chez le prestataire pour cette organisation.',
        'sender_country' => 'Pays de l’expéditeur',
        'sender_country_help' => 'Deux lettres (ISO 3166-1), inscrites dans l’enveloppe comme COUNTRY_C1.',
        'sml_zone' => 'Zone SML',
        'sml_zone_help' => 'Production ou test. Les zones NAPTR sont la procédure actuelle ; les zones CNAME ne subsistent que de la migration.',
        'lookup_ttl_hours' => 'Validité de la vérification du participant (heures)',
        'lookup_ttl_hours_help' => 'Durée de validité d’un résultat SMP avant nouvelle résolution. 0 = résoudre à chaque fois.',
    ],
    'health' => [
        'not_configured' => 'Aucun identifiant du prestataire de point d’accès enregistré.',
        'sender_invalid' => 'L’identifiant de participant Peppol propre est absent ou n’a pas la forme <ICD>:<identifiant>.',
        'unreachable' => 'Le prestataire de point d’accès ne répond pas ou refuse la clé d’accès.',
        'ok' => 'Connecté à :url.',
    ],
    'field' => [
        'participant_id' => 'Identifiant de participant Peppol',
        'participant_id_hint' => 'Format <ICD>:<identifiant>, p. ex. 9930:DE123456789 (n° TVA) ou 0204:991-12345-67 (Leitweg-ID). Vide = pas d’envoi Peppol à ce client.',
    ],
    'action' => [
        'send' => 'Envoyer via Peppol',
        'send_title' => 'Remettre la facture via le prestataire de point d’accès — la preuve de remise est l’accusé de transport.',
        'check' => 'Vérifier l’enregistrement Peppol',
    ],
    'validator' => [
        'scope' => 'Un sous-ensemble des règles Peppol BIS Billing 3.0 a été vérifié (:scenario) — ce n’est expressément pas une attestation de conformité complète. Le contrôle Schematron complet est assuré par le validateur KoSIT et le point d’accès.',
    ],
    'error' => [
        'not_configured' => 'Aucun point d’accès Peppol n’est configuré pour cette organisation (extension « Peppol Access Point »).',
        'sender_invalid' => 'L’identifiant de participant Peppol propre est absent ou invalide — il se trouve dans les réglages de l’extension.',
        'no_participant' => 'Aucun identifiant de participant Peppol n’est enregistré pour :customer.',
        'invalid_participant' => 'L’identifiant de participant Peppol de :customer est invalide : :value',
        'not_registered' => 'Le destinataire :participant n’est pas enregistré dans Peppol.',
        'unsupported_document' => 'Le destinataire :participant n’accepte pas le format :document via Peppol.',
        'lookup_failed' => 'La résolution du participant Peppol a échoué : :message',
        'validation' => 'La facture ne satisfait pas aux règles Peppol vérifiées : :messages',
        'transport' => 'Le point d’accès n’a pas accepté l’envoi : :message',
        'not_issued' => 'Seules les factures émises peuvent être remises via Peppol.',
        'external_billing' => 'La facturation appartient à un système externe — WorkDiary ne remet pas de facture pour ce client.',
        'proforma' => 'Les factures pro forma ne sont pas des factures électroniques et ne passent pas par Peppol.',
    ],
    'status' => [
        'registered' => 'Enregistré dans Peppol (SMP :smp, :count formats de document).',
        'not_registered' => 'Non enregistré dans Peppol.',
        'checked_at' => 'Dernière vérification : :at',
        'never_checked' => 'Pas encore vérifié.',
    ],
    'flash' => [
        'sent' => 'Facture remise à :participant (message :message, statut de transport :status).',
        'checked' => 'Vérification Peppol pour :customer : :result',
    ],
    'inbound' => [
        'summary' => 'Réception Peppol : :fetched récupérés, :imported repris, :duplicates doublons, :unreadable illisibles.',
        'document_name' => 'peppol-:id.xml',
    ],
];
