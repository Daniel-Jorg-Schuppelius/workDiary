<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Trasporto passeggeri (MVP-456, profilo taxi/noleggio con conducente).
return [
    'entry_title' => 'Ordine di corsa (:mode)',
    'entry_content' => 'Trasporto passeggeri — canale d\'ordine: :channel. Dettagli nella scheda corsa.',

    'error' => [
        'destination_required' => 'Indicare una destinazione o confermare esplicitamente la destinazione libera.',
        'receipt_channel_invalid' => 'Il noleggio con conducente / servizio a chiamata richiede una ricezione d\'ordine dimostrabile presso la sede — la fermata a mano non è ammessa (§ 49 c. 4 PBefG).',
        'pickup_required' => 'Il luogo di prelievo è obbligatorio.',
        'not_assigned' => 'La corsa può iniziare solo dopo l\'assegnazione (autista, veicolo, concessione).',
        'tariff_required' => 'Il servizio taxi applica la tariffa regolamentata — selezionare una tariffa.',
        'fixed_price_outside_corridor' => 'Il prezzo fisso è fuori dal corridoio ufficialmente ammesso.',
        'meter_value_required' => 'Il valore del tassametro/dispositivo è obbligatorio alla chiusura.',
        'tax_decision_required' => 'La decisione fiscale (aliquota) è obbligatoria alla chiusura.',
        'payment_required' => 'Il metodo di pagamento è obbligatorio alla chiusura.',
        'invalid_transition' => 'Cambio di stato non consentito.',
        'invalid_transition_detail' => 'Cambio di stato non consentito: :from → :to.',
        'return_not_applicable' => 'La prova di rientro riguarda solo il noleggio con conducente.',
    ],

    'issue' => [
        'driver_unqualified' => 'Licenza di trasporto passeggeri assente o scaduta.',
        'concession_missing' => 'Nessuna concessione valida per questa modalità.',
        'vehicle_profile_missing' => 'Il veicolo non ha un profilo di trasporto passeggeri.',
        'vehicle_mode_unsupported' => 'Il veicolo non è autorizzato per questa modalità.',
        'vehicle_proofs_expired' => 'Attestazioni del veicolo scadute (taratura/BOKraft/revisione).',
        'vehicle_not_barrier_free' => 'La corsa richiede accessibilità — il veicolo non la offre.',
        'vehicle_no_wheelchair_place' => 'La corsa richiede un posto per sedia a rotelle — il veicolo non ne ha.',
        'vehicle_too_small' => 'Il numero di passeggeri supera i posti del veicolo.',
    ],

    'proof' => [
        'meter_calibration' => 'Taratura tassametro/contachilometri',
        'bokraft' => 'Verifica BOKraft',
        'hu' => 'Revisione principale',
    ],
];
