<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ebics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// EBICS-Bankzugang (MVP-124).
return [
    'title' => 'Acceso bancario EBICS',
    'section' => [
        'access' => 'Datos de acceso del banco',
        'steps' => 'Configuración',
        'journal' => 'Historial',
    ],
    'field' => [
        'host_url' => 'URL EBICS del banco',
        'ebics_host' => 'ID de host',
        'ebics_partner' => 'ID de cliente (ID de socio)',
        'ebics_user' => 'ID de participante (ID de usuario)',
    ],
    'hint' => [
        'host_url' => 'Figura junto con el ID de host, de cliente y de participante en la carta de acceso EBICS del banco (EBICS 3.0).',
        'active' => 'La ejecución nocturna recupera los extractos; recuperados hasta :date.',
    ],
    'step' => [
        'keys' => 'Generar las claves (firma, autenticación, cifrado).',
        'initialize' => 'Enviar las claves públicas al banco (INI y HIA).',
        'letter' => 'Imprimir la carta de inicialización, firmarla y enviarla al banco.',
        'activate' => 'Tras la activación por el banco: recuperar las claves del banco.',
    ],
    'action' => [
        'save' => 'Guardar',
        'keys' => 'Generar claves',
        'initialize' => 'Enviar al banco',
        'letter' => 'Descargar carta',
        'activate' => 'Recuperar claves del banco',
        'fetch' => 'Recuperar extractos ahora',
        'suspend' => 'Bloquear acceso',
        'submit' => 'Enviar por EBICS',
    ],
    'confirm' => [
        'suspend' => '¿Bloquear el acceso en el banco? Después harán falta claves nuevas y una carta nueva.',
        'submit' => '¿Enviar ahora esta remesa al banco por EBICS? La autorización se da después en el banco.',
    ],
    'last_error' => 'Último error: :error',
    'flash' => [
        'saved' => 'Datos de acceso guardados.',
        'keys_created' => 'Claves generadas.',
        'initialized' => 'Claves enviadas al banco. Firme y entregue la carta de inicialización.',
        'activated' => 'Claves del banco recuperadas: el acceso está activado.',
        'suspended' => 'Acceso bloqueado.',
        'fetched' => ':statements extractos importados, :skipped ya existentes.',
        'submitted' => 'Remesa enviada (orden :order). Autorícela en el banco.',
    ],
    'error' => [
        'host_not_allowed' => 'Esta dirección no está permitida como acceso bancario.',
        'locked_after_keys' => 'Tras generar las claves ya no se pueden cambiar los datos de acceso; bloquee primero el acceso.',
        'invalid_step' => 'Este paso no corresponde al estado de la configuración.',
        'not_initialized' => 'La carta solo está disponible después de enviar las claves al banco.',
        'not_active' => 'El acceso EBICS no está activado.',
        'no_keys' => 'No hay claves para este acceso.',
        'no_data' => 'El banco no tiene datos nuevos disponibles.',
        'bank_rejected' => 'El banco ha rechazado la orden.',
        'failed' => 'Ha fallado la conexión con el banco.',
        'already_submitted' => 'Esta remesa ya se ha enviado por EBICS.',
    ],
    'letter' => [
        'title' => 'Carta de inicialización EBICS (INI/HIA)',
        'sent_at' => 'Enviada el',
        'key' => [
            'A' => 'Clave bancaria (firma)',
            'X' => 'Clave de autenticación',
            'E' => 'Clave de cifrado',
        ],
        'hash' => 'Valor hash (SHA-256):',
        'certificate' => 'Certificado emitido el :date',
        'confirmation' => 'Por la presente confirmo que las claves anteriores se han transmitido al banco.',
        'place_date' => 'Lugar, fecha',
        'signature' => 'Firma del participante',
        'printed_at' => 'Creada el :date',
    ],
    'run' => [
        'submitted' => 'Enviada por EBICS el :date (orden :order).',
    ],
];
