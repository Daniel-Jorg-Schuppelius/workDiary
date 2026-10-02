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
        'participant_id' => 'Identificador de participante Peppol',
        'participant_id_hint' => 'Forma <ICD>:<identificador>, p. ej. 9930:DE123456789 (NIF-IVA) o 0204:991-12345-67 (Leitweg-ID). Vacío = sin envío Peppol a este cliente.',
    ],
    'action' => [
        'send' => 'Enviar por Peppol',
        'send_title' => 'Entregar la factura a través del proveedor de punto de acceso — la prueba de entrega es el acuse de transporte.',
        'check' => 'Comprobar el registro Peppol',
    ],
    'validator' => [
        'scope' => 'Se comprobó un subconjunto de las reglas Peppol BIS Billing 3.0 (:scenario) — expresamente no es una declaración de conformidad completa. La comprobación Schematron completa la realizan el validador KoSIT y el punto de acceso.',
    ],
    'error' => [
        'not_configured' => 'Para esta organización no hay ningún punto de acceso Peppol configurado (complemento «Peppol Access Point»).',
        'sender_invalid' => 'Falta el identificador de participante Peppol propio o no es válido — está en los ajustes del complemento.',
        'no_participant' => 'Para :customer no hay ningún identificador de participante Peppol guardado.',
        'invalid_participant' => 'El identificador de participante Peppol de :customer no es válido: :value',
        'not_registered' => 'El destinatario :participant no está registrado en Peppol.',
        'unsupported_document' => 'El destinatario :participant no acepta el formato :document por Peppol.',
        'lookup_failed' => 'La resolución del participante Peppol ha fallado: :message',
        'validation' => 'La factura no cumple las reglas Peppol comprobadas: :messages',
        'transport' => 'El punto de acceso no aceptó el envío: :message',
        'not_issued' => 'Solo las facturas emitidas pueden entregarse por Peppol.',
        'external_billing' => 'La facturación pertenece a un sistema externo — WorkDiary no entrega facturas para este cliente.',
        'proforma' => 'Las facturas pro forma no son facturas electrónicas y no van por Peppol.',
    ],
    'status' => [
        'registered' => 'Registrado en Peppol (SMP :smp, :count formatos de documento).',
        'not_registered' => 'No registrado en Peppol.',
        'checked_at' => 'Última comprobación: :at',
        'never_checked' => 'Todavía sin comprobar.',
    ],
    'flash' => [
        'sent' => 'Factura entregada a :participant (mensaje :message, estado de transporte :status).',
        'checked' => 'Comprobación Peppol para :customer: :result',
    ],
    'inbound' => [
        'summary' => 'Entrada Peppol: :fetched recuperados, :imported incorporados, :duplicates duplicados, :unreadable ilegibles.',
        'document_name' => 'peppol-:id.xml',
    ],
];
