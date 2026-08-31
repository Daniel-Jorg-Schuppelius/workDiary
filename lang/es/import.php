<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'entity' => [
        'customers' => 'Clientes',
        'suppliers' => 'Proveedores',
        'articles' => 'Artículos',
        'projects' => 'Proyectos',
        'users' => 'Usuarios',
        'materials' => 'Materiales',
        'vehicles' => 'Vehículos',
        'scheduled_shifts' => 'Planes de turnos',
        'tours' => 'Rutas',
        'remote_sessions' => 'Sesiones de mantenimiento remoto',
        'attendances' => 'Fichajes',
        'project_times' => 'Tiempos de proyecto',
        // MVP-707 (Vollscan H20): Altsystem-Übernahme.
        'invoices' => 'Facturas antiguas (partidas abiertas)',
        'quotes' => 'Presupuestos',
        'assets' => 'Activos',
        'contact_persons' => 'Personas de contacto',
        'documents' => 'Documentos (ZIP)',
    ],
    'template' => [
        'example_required' => 'Valor de ejemplo (obligatorio)',
        'example_optional' => 'Valor de ejemplo (opcional)',
        'download' => 'Descargar plantilla de ejemplo',
    ],

    'state' => [
        'preflight' => 'Comprobación previa',
        'awaitingApproval' => 'Pendiente de aprobación',
        'running' => 'En curso',
        'succeeded' => 'Correcto',
        'partial' => 'Parcial',
        'failed' => 'Fallido',
    ],
    'errorCode' => [
        'required' => 'Falta campo obligatorio',
        'format' => 'Error de formato',
        'unique' => 'Valor no único',
        'fkMissing' => 'Referencia no encontrada',
        'tooLong' => 'Valor demasiado largo',
        'outOfRange' => 'Valor fuera de rango',
        'persist' => 'Error de persistencia',
        'headerMissing' => 'Columna ausente',
        'headerUnknown' => 'Columna desconocida',
        'periodLocked' => 'Periodo bloqueado',
        'skipped' => 'Omitido',
        'blocked' => 'Bloqueado',
    ],
    'error' => [
        'email_taken' => 'Esta dirección de correo electrónico ya está en uso.',
        'required' => 'Falta el campo obligatorio :field.',
        'tooLong' => 'El campo :field supera la longitud máxima de :max caracteres.',
        'header' => [
            'missing' => 'Falta la columna obligatoria :column en el encabezado CSV.',
            'duplicate' => 'La columna :column aparece varias veces.',
        ],
        'format' => [
            'default' => 'El campo :field tiene un formato no válido (:reason).',
            'email' => 'Dirección de correo electrónico no válida.',
            'country' => 'El código de país debe tener de 2 a 3 letras mayúsculas (ISO 3166-1).',
            'currency' => 'El código de moneda debe tener 3 letras mayúsculas (ISO 4217).',
            'enum' => 'El valor no es un estado válido.',
            'parse' => 'No se pudo analizar el archivo: :reason',
            'xlsxUnreadable' => 'El archivo de Excel está dañado o no es un formato XLSX válido.',
            'xlsxEmpty' => 'La primera hoja de cálculo del archivo de Excel no contiene filas.',
            'date' => 'Fecha no válida (se esperaba p. ej. «28.05.2026, 09:42:09»).',
            'time' => 'Hora no válida (se esperaba HH:MM).',
            'status' => 'El valor no es un estado válido.',
            'amount' => 'Importe no válido.',
            'url' => 'Dirección no válida (se espera http:// o https://).',
        ],
        'outOfRange' => [
            'rowLimit' => 'Límite de filas (:max) superado — resto ignorado.',
            'contactPersons' => 'No se admiten más de :max personas de contacto por cliente/proveedor.',
        ],
        'fkMissing' => [
            'customer' => 'No se encontró ningún cliente con el número :number.',
            'supplier' => 'No se encontró ningún proveedor con el número :number.',
            'asset' => 'No se encontró ningún activo con el número :number.',
            'article' => 'No se encontró ningún artículo con el número :number.',
            'projectNumber' => 'No se encontró ningún proyecto con el número :number.',
            'customerName' => 'No se encontró ningún cliente único con el nombre «:value».',
            'user' => 'No se encontró ningún usuario con el correo :value.',
            'project' => 'No se encontró ningún proyecto «:value» — fila enviada a la bandeja de asignación.',
        ],
        // MVP-707: Altsystem-Übernahme (Rechnungshoheit, Altrechnungen, Dokument-ZIP).
        'blocked' => [
            'invoiceSovereignty' => 'La facturación la gestiona :program — las facturas antiguas locales están bloqueadas para este cliente.',
        ],
        'invoice' => [
            'amountMissing' => 'Falta el importe bruto o neto (con tipo impositivo).',
            'paidExceedsTotal' => 'El importe pagado (:paid) supera el importe de la factura (:total).',
            'numberTaken' => 'El número de factura :number ya está en uso.',
        ],
        'document' => [
            'manifestMissing' => 'El archivo ZIP no contiene manifest.csv.',
            'fileMissing' => 'El archivo «:file» no está incluido en el ZIP.',
            'extension' => 'La extensión «:ext» no está permitida.',
            'mime' => 'El contenido del archivo (:mime) no está permitido.',
            'targetType' => 'El tipo de destino debe ser customer, project o asset.',
            'noContent' => 'Los documentos solo pueden importarse mediante la importación ZIP (manifest.csv + archivos).',
            'zipUnreadable' => 'No se pudo leer el archivo ZIP: :reason',
            'tooLarge' => 'El archivo «:file» supera el límite de :max MB.',
            'noActor' => 'Ejecución de importación sin usuario — los documentos necesitan un creador.',
        ],
        'persist' => [
            'noBookingUser' => 'No se encontró ningún usuario imputable en la organización.',
        ],
        // MVP-438: bloqueo GoBD — sin sobrescritura silenciosa de periodos revisados.
        'periodLocked' => [
            'attendance' => 'El día :date está bloqueado por el cierre diario o la aprobación mensual — fila omitida.',
            'projectTime' => 'El periodo :date ya está cerrado/exportado — fila omitida.',
        ],
        // MVP-438: filas de aviso iCal (mapeo deliberadamente conservador).
        'ical' => [
            'allDay' => 'Evento de todo el día «:event» omitido (no computable como presencia).',
            'noTime' => 'Evento «:event» sin hora omitido.',
            'category' => 'Evento «:event» fuera de la lista de categorías permitidas omitido.',
            'transparent' => 'Evento «:event» marcado como libre/ausente omitido.',
            'recurring' => 'Evento recurrente «:event»: solo se importó la instancia base (la expansión de la serie vendrá después).',
            'unsupportedEntity' => 'La importación iCal no es compatible con este tipo de importación.',
        ],
    ],

    // MVP-707: Upload-Hinweise je Dateiart + Texte der Altrechnungs-Übernahme.
    'upload' => [
        'csv' => 'Archivo CSV, Excel o iCal (.csv, .xlsx, .ics, máx. :mb MB, :rows filas)',
        'zip' => 'Archivo ZIP con manifest.csv y los archivos de documentos (.zip, máx. :mb MB, :entries archivos)',
        'zipHint' => 'Cada fila de manifest.csv (plantilla arriba) referencia un archivo del ZIP y lo asigna a un cliente, proyecto o activo.',
    ],
    'legacy' => [
        'position' => 'Traspaso del sistema anterior — factura :number',
        'note' => 'Factura antigua traspasada desde :source (partida abierta de apertura, sin asiento en el diario).',
    ],
];
