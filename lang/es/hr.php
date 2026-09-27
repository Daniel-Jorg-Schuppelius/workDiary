<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Expediente personal digital (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Expediente personal',
        'title_mine' => 'Mi expediente personal',
        'nav' => 'Mi expediente personal',
        'subtitle' => 'Expediente personal de :name — confidencial, visible solo para el círculo de RR. HH. y la persona afectada.',
        'subtitle_mine' => 'Su propio expediente personal (acceso propio, solo lectura).',
        'back' => 'Volver a la lista de personal',
        'empty' => 'Todavía no hay documentos en el expediente personal.',
        'confidential_fixed' => 'Los expedientes personales son siempre confidenciales — se omite el interruptor, la marca se impone.',
        'retention_pending' => 'desde la salida',
        'confirm_delete' => '¿Destruir definitivamente este documento del expediente personal? Se eliminan archivos y versiones; el registro de auditoría se conserva.',
        'field' => [
            'title' => 'Título',
            'category' => 'Categoría',
            'validity' => 'Validez',
            'valid_from' => 'Válido desde',
            'valid_until' => 'Válido hasta',
            'retention_until' => 'Conservación hasta',
            'version' => 'Versión',
            'updated_at' => 'Actualizado',
            'description' => 'Descripción',
            'file' => 'Archivo',
            'version_note' => 'Nota de versión',
            'documents' => 'Documentos',
        ],
        'action' => [
            'upload' => 'Añadir documento',
            'edit' => 'Editar',
            'save' => 'Guardar',
            'download' => 'Descargar',
            'versions' => 'Versiones',
            'delete' => 'Destruir',
        ],
        'flash' => [
            'created' => 'El documento se ha añadido al expediente personal.',
            'updated' => 'El documento del expediente personal se ha actualizado.',
        ],
    ],
    // Personal-Kapazität (MVP-940).
    'capacity' => [
        'title' => 'Capacidad de personal',
        'button' => 'Capacidad',
        'subtitle' => 'Demanda planificada (órdenes asignadas) frente a las horas teóricas de los miembros por semana; festivos y vacaciones aprobadas descontados.',
        'team' => 'Equipo',
        'week' => 'Semana desde :date',
        'members' => ':count miembros',
        'empty' => 'Aún no hay equipos.',
        'hint' => 'Valores en horas: planificado / disponible.',
        'open_requisitions' => 'Puestos abiertos en total: :count.',
    ],
    // Vertretungen beim Austritt (MVP-941).
    'offboarding' => [
        'deputies' => 'Reasignar suplencias',
        'deputies_hint' => 'Estas personas tienen al miembro saliente como suplente. Sin selección, la suplencia termina.',
        'deputy_for' => 'Nuevo suplente para :name',
        'no_deputy' => '— sin suplente —',
    ],
    // Arbeitsvertrag zur Unterschrift (MVP-939).
    'employment' => [
        'title' => 'Contrato de trabajo para firmar',
        'intro' => 'El contrato se envía por enlace a la persona; después la organización lo contrafirma. La versión firmada se archiva en el expediente personal.',
        'send' => 'Enviar para firmar',
        'default_title' => 'Contrato de trabajo :name',
        'default_declaration' => 'He leído el contrato de trabajo y lo acepto.',
        'filed_note' => 'Versión firmada del contrato :number.',
        'field' => [
            'title' => 'Denominación',
            'starts_on' => 'Inicio',
            'email' => 'Correo de la persona',
            'declaration_text' => 'Declaración de conformidad',
            'file' => 'Contrato (PDF)',
        ],
        'flash' => [
            'sent' => 'Contrato de trabajo enviado a :email para firmar.',
        ],
        'error' => [
            'email' => 'Indique una dirección de correo.',
        ],
    ],
];
