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
