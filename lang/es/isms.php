<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'section' => 'SGSI',
        'risks' => 'Registro de riesgos',
        'controls' => 'Catálogo de medidas',
        'soa' => 'DdA',
    ],

    'subtitle' => [
        'risks' => 'Identificar, evaluar (5×5) y tratar los riesgos de seguridad de la información.',
        'controls' => 'Gestionar las medidas y documentar la declaración de aplicabilidad por medida.',
    ],

    'field' => [
        'risk_no' => 'N.º',
        'title' => 'Título',
        'description' => 'Descripción',
        'category' => 'Categoría',
        'asset_ref' => 'Referencia (sistema/proceso/ubicación)',
        'threat' => 'Amenaza',
        'likelihood' => 'Probabilidad',
        'impact' => 'Impacto',
        'score' => 'Puntuación',
        'treatment' => 'Tratamiento',
        'status' => 'Estado',
        'owner' => 'Responsable',
        'review_due_on' => 'Revisión prevista',
        'controls' => 'Medidas vinculadas',
        'code' => 'Código',
        'source' => 'Origen',
        'applicable' => 'Aplicable',
        'justification' => 'Justificación',
        'implementation_status' => 'Estado de implantación',
        'evidence_note' => 'Nota de evidencia',
        'risks' => 'Riesgos vinculados',
    ],

    'group' => [
        'risk' => 'Riesgo',
        'assessment' => 'Evaluación y tratamiento',
        'control' => 'Medida',
        'soa' => 'Declaración de aplicabilidad',
    ],

    'action' => [
        'create_risk' => 'Añadir riesgo',
        'edit_risk' => 'Editar riesgo',
        'create_control' => 'Añadir medida',
        'edit_control' => 'Editar medida',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'transition' => 'Cambiar estado',
        'import_catalog' => 'Cargar catálogo Anexo A',
        'back' => 'Volver',
        'print' => 'Imprimir / guardar PDF',
    ],

    'filter' => [
        'all' => 'Todos',
        'sort' => 'Orden',
        'sort_score' => 'Mayor puntuación primero',
        'sort_review' => 'Fecha de revisión',
        'sort_newest' => 'Más recientes primero',
        'applicable_yes' => 'Aplicable',
        'applicable_no' => 'No aplicable',
    ],

    'scale' => [
        'likelihood' => [
            1 => 'muy raro',
            2 => 'raro',
            3 => 'posible',
            4 => 'probable',
            5 => 'muy probable',
        ],
        'impact' => [
            1 => 'insignificante',
            2 => 'leve',
            3 => 'apreciable',
            4 => 'grave',
            5 => 'crítico',
        ],
    ],

    'matrix' => [
        'title' => 'Matriz de riesgos (riesgos abiertos)',
        'cell' => 'Probabilidad :likelihood × impacto :impact — :count riesgo(s)',
        'axes' => 'Filas: probabilidad (1–5) · Columnas: impacto (1–5)',
        'legend' => 'Leyenda',
        'low' => 'Bajo (puntuación ≤ 6)',
        'medium' => 'Medio (puntuación 7–12)',
        'high' => 'Alto (puntuación > 12)',
        'review_due' => '{1} 1 revisión pendiente|[2,*] :count revisiones pendientes',
    ],

    'hint' => [
        'asset_ref' => 'p. ej. sistema ERP, sala de servidores, centro de datos …',
        'threat' => '¿Qué amenaza/vulnerabilidad está en la base?',
        'controls' => 'Selección múltiple (mantener Ctrl/Cmd)',
        'no_controls_yet' => 'Aún no hay medidas: cargue primero el catálogo del Anexo A o cree medidas propias.',
        'code' => 'p. ej. M-01 (medida propia)',
        'justification' => 'obligatoria si no es aplicable',
        'evidence_note' => 'Referencia a evidencia/documento',
    ],

    'flash' => [
        'risk_created' => 'El riesgo se ha añadido.',
        'risk_updated' => 'El riesgo se ha actualizado.',
        'risk_transitioned' => 'El estado del riesgo se ha cambiado.',
        'risk_deleted' => 'El riesgo se ha eliminado.',
        'control_created' => 'La medida se ha añadido.',
        'control_updated' => 'La medida se ha actualizado.',
        'control_deleted' => 'La medida se ha eliminado.',
        'catalog_imported' => 'Catálogo del Anexo A cargado (:count medidas nuevas).',
    ],

    'error' => [
        'invalid_transition' => 'El cambio de estado de «:from» a «:to» no está permitido.',
        'justification_required' => 'Para las medidas no aplicables se requiere una justificación en la DdA.',
    ],

    'soa' => [
        'document_title' => 'Declaración de aplicabilidad',
        'heading' => 'Declaración de aplicabilidad (DdA)',
        'generated_at' => 'Estado a',
        'control_count' => ':count medidas',
        'yes' => 'Sí',
        'no' => 'No',
        'disclaimer' => 'Referencia: ISO/IEC 27001:2022 Anexo A (solo códigos y títulos breves propios — sin textos normativos). La evaluación de conformidad corresponde a un organismo de certificación independiente.',
    ],

    'empty_risks' => 'Aún no hay riesgos registrados.',
    'empty_risks_title' => 'No se han encontrado riesgos',
    'empty_controls' => 'Aún no hay medidas.',
    'empty_controls_title' => 'No se han encontrado medidas',
    'empty_controls_hint_catalog' => 'Aún no hay medidas: use «Cargar catálogo Anexo A» para importar el catálogo de referencia ISO/IEC 27001 (93 medidas).',
    'empty_controls_linked' => 'Ninguna medida vinculada.',
    'empty_filtered' => 'No se han encontrado entradas para los filtros actuales.',
    'confirm_delete_risk' => '¿Eliminar realmente este riesgo?',
    'confirm_delete_control' => '¿Eliminar realmente esta medida? Se quitarán los vínculos con los riesgos.',
    'confirm_import_catalog' => '¿Cargar el catálogo de referencia ISO/IEC 27001:2022 Anexo A (93 medidas, solo código + título breve) en esta organización? Las medidas existentes permanecen sin cambios.',
];
