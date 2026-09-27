<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sustainability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nachhaltigkeit: Standorte, Benchmarking, Auszug (MVP-929/930).
return [
    'site' => [
        'benchmark' => 'Comparación de sedes',
        'subtitle' => 'Emisiones por sede y año a partir de los datos de actividad, con intensidades por m² y por persona empleada. Las actividades sin factor no se cuentan.',
        'back' => 'Sostenibilidad',
        'create' => 'Añadir sede',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'inactive' => 'inactiva',
        'empty' => 'Aún no hay sedes: añada sedes y registre actividades con sede.',
        'field' => [
            'site' => 'Sede',
            'code' => 'Código',
            'year' => 'Año',
            'area_m2' => 'Superficie (m²)',
            'headcount' => 'Personas empleadas',
            'co2e_t' => 'CO₂e (t)',
            'per_m2' => 'kg CO₂e por m²',
            'per_head' => 'kg CO₂e por persona',
            'missing' => 'sin factor',
            'active' => 'activa',
        ],
        'flash' => [
            'saved' => 'Sede guardada.',
        ],
    ],
    'excerpt' => [
        'title' => 'Extracto público',
        'intro' => 'Publique una instantánea congelada del informe. Se muestra mediante un enlace y como aviso en el portal de clientes, sin afirmaciones de conformidad ni de neutralidad climática.',
        'snapshot' => 'Instantánea publicada',
        'none' => '— no publicado —',
        'with_targets' => 'Incluir objetivos',
        'publish' => 'Guardar publicación',
        'token_once' => 'Enlace visible solo ahora: cópielo.',
        'link' => 'Enlace público',
        'state_none' => 'no emitido',
        'state_active' => 'activo',
        'state_paused' => 'en pausa',
        'pause' => 'Pausar',
        'resume' => 'Reanudar',
        'revoke' => 'Revocar',
        'rotate' => 'Emitir nuevo enlace',
        'issue' => 'Emitir enlace',
        'public_title' => 'Extracto de sostenibilidad :org',
        'period' => 'Periodo :from – :to',
        'emissions' => 'Emisiones de gases de efecto invernadero',
        'scope' => 'Alcance :scope',
        'targets' => 'Objetivos',
        'disclaimer' => 'Cifras congeladas a partir de los datos de actividad registrados; sin afirmaciones de conformidad ni de neutralidad climática.',
        'factors' => 'Conjuntos de factores: :sets.',
        'portal_subject' => 'Extracto de sostenibilidad :from – :to',
        'portal_body' => 'Emisiones de gases de efecto invernadero en el periodo: :tonnes t CO₂e.',
        'flash' => [
            'published' => 'Publicación guardada.',
            'issued' => 'Enlace emitido.',
            'revoked' => 'Enlace revocado.',
            'saved' => 'Ajuste guardado.',
        ],
    ],
    // Vergleich nach Kundengruppe (MVP-949).
    'customer_group' => [
        'title' => 'Emisiones por grupo de clientes :year',
        'group' => 'Grupo de clientes',
        'customers' => 'Clientes',
        'per_customer' => 'por cliente',
        'none' => 'Sin grupo de clientes',
        'customer' => 'Cliente (para la comparación por grupo de clientes)',
        'empty' => 'No hay actividades vinculadas a clientes este año.',
    ],
];
