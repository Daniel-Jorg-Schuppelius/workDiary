<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Transferir a contabilidad',
    ],

    'flash' => [
        'pushed' => 'Cliente transferido a contabilidad (ID :id).',
        'failed' => 'La transferencia ha fallado: :msg',
        'no_plugin' => 'No hay ningún sistema contable activo.',
    ],

    'error' => [
        'accounting_leads' => 'La contabilidad posee los datos maestros — no se transfiere nada (ajuste «autoridad de datos maestros»).',
        'no_syncer' => 'El plugin :plugin no transfiere contactos.',
    ],

    'authority' => [
        'workdiary' => 'Dirige workDiary',
        'accounting' => 'Dirige la contabilidad',
    ],

    // Lokale Buchhaltung (Feature 125, MVP-671): Einrichtung, Buchungshoheit,
    // Geschäftsjahre und Preflight.
    'ledger' => [
        'title' => 'Contabilidad local',
        'menu' => 'Contabilidad',
        'setup_menu' => 'Configuración',
        'subtitle' => 'Autoridad contable, ejercicios y comprobación previa de la configuración.',
        'open_ended' => 'en curso',
        'section' => [
            'profile' => 'Perfil contable',
            'preflight' => 'Comprobación previa',
            'fiscal_years' => 'Ejercicios',
            'sovereignty' => 'Autoridad contable',
        ],
        'field' => [
            'profit_determination' => 'Determinación del resultado',
            'base_currency' => 'Moneda base',
            'fiscal_year_start_month' => 'El ejercicio comienza en',
            'starts_on' => 'Inicio de los asientos (fecha de referencia)',
            'note' => 'Nota',
            'fiscal_year_starts_on' => 'Inicio del ejercicio',
            'fiscal_year_label' => 'Denominación',
            'sovereignty' => 'Nueva autoridad contable',
            'external_provider' => 'Sistema principal',
            'valid_from' => 'Válido desde',
            'reason' => 'Motivo',
            'datev_account' => 'Cuenta DATEV',
            'euer_category' => 'Línea ingresos-gastos',
            'euer_category_none' => '— sin asignación —',
            'deductible_percent' => 'Parte deducible (%)',
            'description' => 'Descripción',
            'post_now' => 'Contabilizar de inmediato',
            'reversal_reason' => 'Motivo',
            'reversal_booked_on' => 'Fecha del contraasiento',
        ],
        'hint' => [
            'profit_determination' => 'Cambia los análisis (criterio de caja o partida doble), no las reglas de asiento y prueba.',
            'base_currency' => 'La primera versión gestiona una sola moneda; los documentos divergentes se muestran con motivo en lugar de convertirse.',
            'starts_on' => 'Desde este día se generan asientos locales. Los documentos anteriores quedan como historial y no se contabilizan retroactivamente.',
            'fiscal_year_starts_on' => 'Se crean doce periodos mensuales hasta el día anterior al año siguiente.',
            'fiscal_year_label' => 'Dejar vacío para «2026» o «2026/2027» con ejercicio no natural.',
            'sovereignty' => 'Quién llevó el libro mayor y en qué periodo sigue siendo trazable, incluso tras un cambio.',
            'sovereignty_switch' => 'El traslado de datos sigue siendo el cambio de contabilidad; aquí solo se reasigna la dirección.',
            'external_provider' => 'Solo con autoridad externa: nombre del sistema principal (p. ej. lexoffice).',
            'datev_account' => 'Solo para la exportación; el asiento local no depende de ella.',
            'euer_category' => 'Determina en qué línea del formulario aparece la cuenta. Sin asignación queda entre los casos sin aclarar.',
            'deductible_percent' => 'Solo afecta a la vista previa de ingresos-gastos — en el diario siempre consta el importe íntegro (p. ej. 70 % en gastos de representación).',
            'normal_balance' => 'Preseleccionado según el tipo de cuenta, modificable en cada caso.',
            'post_now' => 'Una vez contabilizado, el asiento solo se corrige con un contraasiento.',
            'reversal_booked_on' => 'Dejar vacío para el día original, mientras su periodo siga abierto.',
        ],
        'action' => [
            'activate' => 'Activar la contabilidad local',
            'add_fiscal_year' => 'Crear ejercicio',
            'switch' => 'Cambiar la autoridad contable',
            'switch_submit' => 'Reasignar la dirección',
            'add_account' => 'Crear cuenta',
            'edit_account' => 'Editar cuenta',
            'deactivate' => 'Desactivar',
            'add_entry' => 'Nuevo asiento',
            'post' => 'Contabilizar',
            'reverse' => 'Anular',
            'reverse_submit' => 'Crear contraasiento',
        ],
        'column' => [
            'fiscal_year' => 'Ejercicio',
            'range' => 'Periodo',
            'periods' => 'Periodos',
            'status' => 'Estado',
            'from' => 'Desde',
            'to' => 'Hasta',
            'holder' => 'Dirección',
            'reason' => 'Motivo',
            'number' => 'Cuenta',
            'name' => 'Denominación',
            'type' => 'Tipo de cuenta',
            'normal_balance' => 'Sentido del saldo',
            'flags' => 'Características',
            'journal_no' => 'N.º',
            'booked_on' => 'Fecha de asiento',
            'document_on' => 'Fecha del documento',
            'memo' => 'Concepto',
            'accounts' => 'Cuentas',
            'amount' => 'Importe',
            'debit' => 'Debe',
            'credit' => 'Haber',
            'account' => 'Cuenta',
            'document_reference' => 'Documento',
            'posted_by' => 'Contabilizado por',
            'source' => 'Origen',
        ],
        'empty' => [
            'accounts' => 'Todavía no hay ninguna cuenta.',
            'entries' => 'Ninguna anotación en el periodo.',
            'fiscal_years' => 'Todavía no se ha creado ningún ejercicio.',
            'sections' => 'Aún no se ha registrado ningún cambio de dirección.',
        ],
        'flash' => [
            'saved' => 'Perfil contable guardado.',
            'activated' => 'Contabilidad local activada.',
            'fiscal_year_created' => 'Ejercicio :year creado con sus periodos.',
            'sovereignty_switched' => 'Autoridad contable cambiada.',
            'account_saved' => 'Cuenta guardada.',
            'account_deactivated' => 'Cuenta desactivada.',
            'imported' => 'Importación de cuentas: :imported nuevas, :updated actualizadas, :errors errores.',
            'entry_saved' => 'Asiento guardado.',
            'entry_posted' => 'Asiento contabilizado.',
            'entry_reversed' => 'Contraasiento creado.',
        ],
        'error' => [
            'sovereignty' => 'El :date el libro mayor lo lleva :holder — ese día no se permiten asientos locales.',
            'fiscal_year_overlap' => 'El periodo se solapa con el ejercicio :year.',
            'start_locked' => 'El inicio de los asientos ya no se puede cambiar tras la activación.',
            'provider_required' => 'Con autoridad contable externa hay que indicar el sistema principal.',
            'sovereignty_unchanged' => 'Esta autoridad contable ya rige en esa fecha.',
            'later_section_exists' => 'Ya existe un tramo de dirección posterior desde el :date.',
            'period_closed' => 'El periodo desde :period ya no admite asientos.',
            'no_period' => 'No hay periodo contable para el :date.',
            'entry_frozen' => 'El asiento está contabilizado — corrección solo mediante contraasiento.',
            'needs_two_lines' => 'Un asiento necesita al menos dos líneas.',
            'unknown_account' => 'Una línea remite a una cuenta desconocida.',
            'inactive_account' => 'La cuenta :account está desactivada.',
            'foreign_currency_line' => 'Todas las líneas deben estar en :currency.',
            'negative_amount' => 'Los importes son positivos; el sentido lo da el Debe o el Haber.',
            'both_sides' => 'Una línea lleva Debe o Haber, nunca ambos.',
            'unbalanced' => 'El Debe (:debit) y el Haber (:credit) no coinciden.',
            'reverse_not_posted' => 'Solo se puede anular un asiento contabilizado.',
            'reversal_reason_required' => 'La anulación exige un motivo.',
            'account_in_use' => 'Esta cuenta ya tiene asientos — solo puede desactivarse.',
            'entry_without_organization' => 'El asiento no tiene organización — informe al administrador del sistema.',
            'account_number_taken' => 'Este número de cuenta ya existe.',
        ],
        'preflight' => [
            'not_configured' => 'Perfil aún no guardado — la comprobación se ejecuta desde el primer guardado.',
            'blocked_hint' => 'La activación permanece bloqueada mientras haya un punto en rojo.',
            'profile_missing' => 'Todavía no se ha guardado ningún perfil contable.',
            'starts_on_missing' => 'No se ha fijado el inicio de los asientos.',
            'starts_on_ok' => 'Inicio de los asientos: :date.',
            'fiscal_year_missing' => 'No hay ningún ejercicio que cubra el inicio de los asientos.',
            'periods_missing' => 'El ejercicio :year no tiene periodos.',
            'fiscal_year_ok' => 'Ejercicio :year con :count periodos.',
            'migration_active' => 'Hay un cambio de contabilidad en curso (:status) — mientras tanto la dirección no es unívoca.',
            'migration_none' => 'No hay ningún cambio de contabilidad en curso.',
            'handed_over' => 'El lote DATEV :batch ya cubre el periodo hasta el :to.',
            'handed_over_none' => 'Ningún lote exportado se solapa con el periodo.',
            'sovereignty_conflict' => 'Desde el :date ya dirige :holder — el periodo estaría ocupado dos veces.',
            'sovereignty_ok' => 'Ningún tramo de dirección en conflicto.',
            'foreign_currency' => ':count documentos desde la fecha de referencia no están en :currency; siguen visibles en la bandeja contable.',
            'base_currency_ok' => 'Todos los documentos desde la fecha de referencia están en :currency.',
            'billing_external' => 'Las facturas las emite :program — los documentos vendrán de allí.',
            'billing_local' => 'workDiary emite por sí mismo las facturas de venta.',
            'master_data_external' => 'Los datos maestros los dirige la contabilidad; clientes y proveedores no se sobrescriben desde aquí.',
            'master_data_local' => 'workDiary dirige los datos maestros.',
            'key' => [
                'profile' => 'Perfil',
                'starts_on' => 'Fecha de referencia',
                'fiscal_year' => 'Ejercicio',
                'migration_run' => 'Cambio',
                'handed_over' => 'Traspasos',
                'sovereignty' => 'Dirección',
                'base_currency' => 'Moneda',
                'billing_mode' => 'Facturación',
                'master_data' => 'Datos maestros',
            ],
        ],
        'reversal_memo' => 'Anulación del asiento n.º :no',
        'opening_memo' => 'Asiento de apertura',
        'reverse_hint' => 'La anulación crea un contraasiento real. El asiento original permanece sin cambios.',
        'accounts' => [
            'title' => 'Plan contable',
            'menu' => 'Plan contable',
            'subtitle' => 'Cuentas, sentido del saldo y correspondencia DATEV de la contabilidad local.',
        ],
        'journal' => [
            'title' => 'Libro diario',
            'menu' => 'Diario',
            'subtitle' => 'Asientos contabilizados y preparados en el periodo elegido.',
        ],
        'entry' => [
            'title' => 'Asiento',
            'head' => 'Cabecera',
            'lines' => 'Líneas',
            'total' => 'Total',
            'is_reversal_of' => 'Este asiento anula el asiento n.º :no.',
            'reversed_by' => 'Anulado por el asiento n.º :no — :reason',
        ],
        'filter' => [
            'only_active' => 'solo activas',
            'all_types' => 'Todos los tipos de cuenta',
            'all_states' => 'Todos los estados',
        ],
        'flag' => [
            'open_item' => 'Partidas abiertas',
            'bank' => 'Banco',
            'cash' => 'Caja',
            'clearing' => 'Regularización',
            'inactive' => 'Desactivada',
        ],
        'confirm' => [
            'deactivate' => '¿Desactivar realmente esta cuenta? Los asientos existentes se conservan.',
        ],
        'import' => [
            'line_invalid' => 'Línea :line omitida (falta número, nombre o tipo de cuenta).',
        ],
    ],

    // Buchungs-Inbox und Mappingregeln (Feature 125, MVP-673).
    'inbox' => [
        'title' => 'Bandeja contable',
        'menu' => 'Bandeja contable',
        'subtitle' => 'Documentos, gastos y movimientos de caja del periodo con su estado contable.',
        'empty' => 'No hay elementos abiertos en el periodo.',
        'four_eyes_active' => 'Principio de doble control activo: quien prepara una propuesta no la contabiliza él mismo.',
        'state' => [
            'blocked' => 'Bloqueado',
            'open' => 'Sin contabilizar',
            'ready' => 'Listo',
            'posted' => 'Contabilizado',
        ],
        'column' => [
            'kind' => 'Origen',
            'document' => 'Documento',
            'booked_on' => 'Fecha',
            'proposal' => 'Propuesta',
        ],
        'filter' => [
            'all_kinds' => 'Todos los orígenes',
            'include_posted' => 'mostrar contabilizados',
        ],
        'action' => [
            'prepare' => 'Aceptar la propuesta',
            'prepare_and_post' => 'Aceptar y contabilizar',
            'batch_prepare' => 'Aceptar todo',
            'batch_post' => 'Aceptar y contabilizar todo',
        ],
        'confirm' => [
            'batch' => '¿Aceptar como borradores todos los elementos no bloqueados del periodo?',
            'batch_post' => '¿Aceptar Y contabilizar todos los elementos no bloqueados? Los asientos contabilizados solo se corrigen con contraasientos.',
        ],
        'flash' => [
            'prepared' => 'Propuesta aceptada.',
            'batch' => 'Lote: :prepared aceptados, :posted contabilizados, :failed abiertos.',
        ],
        'error' => [
            'four_eyes' => 'Principio de doble control: usted preparó este asiento — debe contabilizarlo otra persona.',
        ],
        'blocker' => [
            'missing_rule' => 'No hay regla contable para :role:criteria.',
            'handed_over' => 'El documento ya forma parte de un lote exportado.',
            'no_tax_breakdown' => 'El documento no tiene desglose de impuestos.',
            'no_amount' => 'El documento no tiene importe.',
            'no_lines' => 'La propuesta no tiene líneas de asiento.',
            'sovereignty' => 'En este periodo la organización no lleva un libro mayor local.',
            'foreign_currency' => 'El documento está en :currency, la contabilidad en :base — todavía no hay una conversión justificable.',
            'unsupported_target' => 'Todavía no hay vía contable para este destino de pago.',
        ],
        'memo' => [
            'sales_invoice' => 'Factura :number · :customer',
            'incoming_invoice' => 'Factura recibida :number · :seller',
            'expense' => 'Gasto :description · :user',
            'cash_entry' => 'Caja :register · :purpose',
            'payment' => 'Pago (:kind) · :target',
        ],
        'reversal_reason' => [
            'unmatched' => 'Asignación de pago anulada — contraasiento.',
        ],
    ],
    'rules' => [
        'title' => 'Reglas contables',
        'menu' => 'Reglas contables',
        'subtitle' => 'Correspondencia de origen y rol con una cuenta — versionada y con fecha de vigencia.',
        'empty' => 'Todavía no se ha creado ninguna regla.',
        'fallback' => 'Regla general (todos los casos)',
        'no_tax_code' => '— sin código de impuesto —',
        'column' => [
            'role' => 'Rol',
            'match' => 'Criterios',
            'validity' => 'Vigencia',
            'priority' => 'Prioridad',
        ],
        'field' => [
            'tax_code' => 'Código de impuesto',
            'match_key' => 'Criterio',
            'match_value' => 'Valor',
        ],
        'hint' => [
            'role' => 'Qué representa la cuenta en el asiento — ingreso, cliente, IVA soportado …',
            'tax_code' => 'Opcional; asigna el resultado fiscal congelado del documento a una cuenta.',
            'match' => 'Dejar vacío para la regla general. Ejemplos: tax_rate = 19.00, expense_category_id = 5.',
            'priority' => 'Gana la más alta; en empate, la regla más específica.',
        ],
        'action' => [
            'add' => 'Crear regla',
            'edit' => 'Editar regla',
        ],
        'confirm' => [
            'deactivate' => '¿Desactivar la regla? Los asientos existentes conservan su versión de regla.',
        ],
        'flash' => [
            'saved' => 'Regla contable guardada.',
            'versioned' => 'Nueva versión de la regla creada desde la fecha de vigencia.',
            'deactivated' => 'Regla contable desactivada.',
        ],
    ],

    // Offene Posten (Feature 125, MVP-674).
    'open_items' => [
        'title' => 'Partidas abiertas',
        'menu' => 'Partidas abiertas',
        'subtitle' => 'Derechos de cobro y obligaciones de pago de los asientos contabilizados, con antigüedad.',
        'empty' => 'No hay partidas abiertas.',
        'overdue_days' => ':days días de retraso',
        'settle_hint' => 'Abierto: :open. Los pagos vienen de la conciliación bancaria — aquí solo descuento, retención o baja.',
        'column' => [
            'counterparty' => 'Contraparte',
            'due_date' => 'Vencimiento',
            'original' => 'Original',
            'open' => 'Abierto',
            'kind' => 'Tipo',
        ],
        'bucket' => [
            'not_due' => 'No vencido',
            'd30' => '1–30 días',
            'd60' => '31–60 días',
            'd90' => '61–90 días',
            'd90plus' => 'más de 90 días',
        ],
        'action' => [
            'settle' => 'Compensar',
            'show_entry' => 'Ver asiento',
        ],
        'flash' => [
            'settled' => 'Compensación registrada.',
        ],
    ],

    // Wiederkehrende Vorgänge (Feature 125, MVP-675).
    'recurring' => [
        'title' => 'Operaciones recurrentes',
        'menu' => 'Recurrentes',
        'subtitle' => 'Expectativas de documentos, plantillas de asiento y planes de facturación de un vistazo.',
        'principle' => 'Una expectativa de documento no crea documento ni asiento — solo el original la cumple. Las plantillas crean únicamente borradores.',
        'invoice_schedules_hint' => 'Las facturas periódicas siguen en el plan de facturación; aquí solo como referencia.',
        'preview' => 'Próximos vencimientos: :dates',
        'no_account' => '— sin cuenta —',
        'section' => [
            'open_runs' => 'Operaciones abiertas',
            'templates' => 'Plantillas',
            'invoice_schedules' => 'Planes de facturación',
        ],
        'column' => [
            'template' => 'Plantilla',
            'period' => 'Periodo',
            'expected' => 'Esperado',
            'name' => 'Denominación',
            'kind' => 'Tipo',
            'interval' => 'Ritmo',
            'next_due' => 'Próximo vencimiento',
            'responsible' => 'Responsable',
        ],
        'field' => [
            'due_day' => 'Día de vencimiento',
            'starts_on' => 'Inicio',
            'ends_on' => 'Fin',
        ],
        'hint' => [
            'kind' => 'La expectativa espera un original; la plantilla de asiento crea un borrador.',
            'due_day' => '1–28, para que todos los meses tengan ese día.',
            'accounts' => 'Solo para plantillas de asiento — junto con el importe esperado.',
        ],
        'action' => [
            'add' => 'Crear plantilla',
            'edit' => 'Editar plantilla',
            'run' => 'Ejecutar ahora',
            'pause' => 'Pausar',
            'resume' => 'Reanudar',
            'end' => 'Finalizar',
            'open_schedules' => 'Abrir los planes',
        ],
        'confirm' => [
            'end' => '¿Finalizar la plantilla? Las operaciones ya creadas se conservan.',
        ],
        'empty' => [
            'runs' => 'No hay operaciones abiertas.',
            'templates' => 'Todavía no hay plantillas.',
            'schedules' => 'Ningún plan activo.',
        ],
        'flash' => [
            'saved' => 'Plantilla guardada.',
            'versioned' => 'Plantilla guardada como nueva versión.',
            'paused' => 'Plantilla pausada.',
            'resumed' => 'Plantilla reanudada.',
            'ended' => 'Plantilla finalizada.',
            'ran' => 'Ejecución realizada.',
        ],
        'error' => [
            'already_closed' => 'La operación ya está cerrada.',
            'template_incomplete' => 'Una plantilla de asiento necesita cuenta del Debe, del Haber e importe.',
        ],
        'blocker' => [
            'no_lines' => 'La plantilla no tiene líneas de asiento.',
        ],
        'notification' => [
            'title' => 'Operación recurrente vencida: :name',
            'message' => 'Vence el :due — estado: :status.',
        ],
    ],

    // Finanzberichte (Feature 125, MVP-676).
    'reports' => [
        'title' => 'Informes financieros',
        'menu' => 'Informes financieros',
        'subtitle' => 'Análisis de la contabilidad local en el periodo seleccionado.',
        'period' => 'Periodo :from – :to',
        'as_of' => 'A :date',
        'empty' => 'No hay datos en el periodo.',
        'vat_preview_hint' => 'Vista previa verificable — el MVP no presenta ninguna declaración de IVA.',
        'euer_preview_hint' => 'Vista previa según cobro y pago (§ 11 EStG), agrupada por las líneas del formulario alemán — no es el formulario.',
        'euer_manual_hint' => 'a registrar manualmente',
        'pnl_hint' => 'Resultado por grupos de cuentas — no es una cuenta de resultados auditada.',
        'column' => [
            'euer_category' => 'Línea ingresos-gastos',
            'gross' => 'Importe',
            'deductible' => 'Deducible',
            'not_deductible' => 'No deducible',
            'opening' => 'Saldo inicial',
            'closing' => 'Saldo final',
            'balance' => 'Saldo',
            'direction' => 'Sentido',
            'payable' => 'IVA a ingresar',
            'result' => 'Resultado',
            'section' => 'Sección',
        ],
        'section' => [
            'income' => 'Ingresos',
            'expense' => 'Gastos',
            'balances' => 'Cuentas bancarias y de caja',
        ],
        'kpi' => [
            'cash' => 'Banco y caja',
            'receivable' => 'Cobros pendientes',
            'payable' => 'Pagos pendientes',
            'forecast' => 'Previsión',
            'findings' => 'Hallazgos',
        ],
        'aging' => [
            'receivable' => 'Antigüedad de cobros',
            'payable' => 'Antigüedad de pagos',
        ],
        'unclear' => [
            'title' => 'Casos sin aclarar',
            'none' => 'No hay casos sin aclarar.',
            'open_items' => ':count partidas abiertas no están compensadas en el periodo.',
            'settlement_without_item' => 'Compensación :id sin partida abierta correspondiente.',
            'settlement_without_source' => 'Liquidación :id sin documento de origen utilizable.',
            'account_without_category' => 'La cuenta :account no tiene línea de ingresos-gastos.',
        ],
        'quality' => [
            'headline' => 'Qué impide los análisis',
            'none' => 'Sin hallazgos.',
            'drafts' => ':count asientos aún no están contabilizados.',
            'unbalanced' => ':count borradores no están cuadrados.',
            'blocked_runs' => ':count ejecuciones recurrentes están bloqueadas.',
            'open_expectations' => ':count expectativas de documentos siguen abiertas.',
            'ten_day_rule' => ':count pagos caen entre el 22.12 y el 10.01 y corresponden al año contiguo según el documento (§ 11 ap. 1 fr. 2 EStG).',
            'open_clearing' => ':count cuentas puente aún no están saldadas.',
            'overdue_filings' => ':count plazos de declaración han vencido y no constan como presentados.',
            'kpi' => [
                'drafts' => 'Borradores',
                'unbalanced' => 'Descuadrados',
                'blocked_runs' => 'Ejecuciones bloqueadas',
                'open_expectations' => 'Expectativas abiertas',
            ],
        ],
        'card' => [
            'trial_balance' => [
                'title' => 'Balance de sumas y saldos',
                'text' => 'Apertura, movimiento y saldo por cuenta.',
            ],
            'account_ledger' => [
                'title' => 'Libro mayor',
                'text' => 'Todos los movimientos de una cuenta con acceso al asiento.',
            ],
            'vat' => [
                'title' => 'IVA',
                'text' => 'IVA repercutido, soportado y a ingresar como vista previa.',
            ],
            'euer' => [
                'title' => 'Vista previa por caja',
                'text' => 'Ingresos y gastos según cobro y pago.',
            ],
            'recapitulative' => [
                'title' => 'Estado recapitulativo',
                'text' => 'Entregas intracomunitarias por NIF-IVA',
            ],
            'pnl' => [
                'title' => 'Resultado',
                'text' => 'Ingresos y gastos por grupos de cuentas.',
            ],
            'liquidity' => [
                'title' => 'Liquidez',
                'text' => 'Saldos reales, partidas abiertas y previsión — por separado.',
            ],
            'quality' => [
                'title' => 'Calidad contable',
                'text' => 'Borradores, ejecuciones bloqueadas y expectativas abiertas.',
            ],
            'journal' => [
                'title' => 'Diario',
                'text' => 'Todos los asientos contabilizados en orden de diario.',
            ],
            'open_items' => [
                'title' => 'Partidas abiertas',
                'text' => 'Cobros y pagos pendientes con antigüedad.',
            ],
        ],
    ],

    // Periodenabschluss (Feature 125, MVP-677).
    'closing' => [
        'title' => 'Cierre de periodos',
        'menu' => 'Cierre',
        'subtitle' => 'Cerrar periodos de forma provisional o definitiva — y reabrirlos con motivo.',
        'blocked_hint' => 'El cierre permanece bloqueado mientras haya un punto en rojo.',
        'reopen_hint' => 'La reapertura anula un cierre. Queda registrada con su motivo en la cadena de prueba.',
        'column' => [
            'period' => 'Periodo',
            'closed_at' => 'Cerrado',
            'reopened' => 'Reabierto',
        ],
        'field' => [
            'reason' => 'Motivo',
        ],
        'action' => [
            'soft_close' => 'Cerrar provisionalmente',
            'close' => 'Cerrar definitivamente',
            'close_submit' => 'Cerrar el periodo',
            'reopen' => 'Reabrir',
            'reopen_submit' => 'Abrir el periodo',
            'close_year' => 'Cerrar el ejercicio',
        ],
        'confirm' => [
            'year' => '¿Cerrar el ejercicio? Todos los periodos deben estar cerrados.',
        ],
        'check' => [
            'no_drafts' => 'No hay borradores abiertos en el periodo.',
            'drafts' => ':count asientos aún no están contabilizados.',
            'balanced' => 'Todos los asientos están cuadrados.',
            'unbalanced' => ':count asientos no están cuadrados.',
            'sequence_ok' => 'No quedan periodos anteriores abiertos.',
            'earlier_open' => ':count periodos anteriores siguen abiertos.',
            'key' => [
                'drafts' => 'Borradores',
                'balanced' => 'Cuadre',
                'sequence' => 'Orden',
            ],
        ],
        'flash' => [
            'soft_closed' => 'Periodo cerrado provisionalmente.',
            'closed' => 'Periodo cerrado.',
            'reopened' => 'Periodo reabierto.',
            'year_closed' => 'Ejercicio cerrado.',
        ],
        'error' => [
            'reason_required' => 'La reapertura exige un motivo.',
            'already_open' => 'El periodo ya está abierto.',
            'wrong_status' => 'En este estado (:status) el paso no es posible.',
            'periods_open' => ':count periodos no están cerrados.',
        ],
    ],

    // Startsalden und DATEV-Übergabe (Feature 125, MVP-677).
    'opening' => [
        'title' => 'Importar saldos iniciales',
        'subtitle' => 'CSV con cuenta, Debe y Haber — primero comprobar, luego contabilizar.',
        'hint' => 'El MVP asume saldo inicial, partidas abiertas y fecha de referencia; un diario histórico completo no se importa.',
        'field' => [
            'file' => 'Archivo CSV',
        ],
        'action' => [
            'dry_run' => 'Simulación',
            'import' => 'Importar',
        ],
        'flash' => [
            'dry_run' => 'Simulación: :lines líneas, Debe :debit, Haber :credit, :errors errores.',
            'imported' => 'Asiento de apertura :no creado.',
        ],
        'error' => [
            'missing_account' => 'Línea :line sin cuenta.',
            'unknown_account' => 'La cuenta :account (línea :line) no existe.',
            'both_sides' => 'La línea :line lleva Debe y Haber.',
            'unbalanced' => 'El Debe (:debit) y el Haber (:credit) no coinciden.',
        ],
    ],
    'datev' => [
        'title' => 'Traspaso DATEV',
        'subtitle' => 'Líneas de asiento del periodo en CSV.',
        'hint' => 'Generado a partir de los asientos contabilizados — no derivado de nuevo de los documentos.',
        'action' => [
            'export' => 'Exportar',
        ],
    ],

    // Kontenplan-Vorlagen (Feature 125, MVP-678).
    'template' => [
        'title' => 'Plan contable desde plantilla',
        'subtitle' => 'Crear cuentas, códigos de impuesto y reglas contables de una vez.',
        'hint_first' => 'La plantilla crea cuentas, códigos de impuesto y reglas correspondientes — la bandeja contable funciona de inmediato.',
        'hint_additive' => 'Solo se añade: las cuentas y reglas existentes permanecen sin cambios.',
        'disclaimer' => 'Extracto inicial basado en el plan contable estándar alemán correspondiente, válido para Alemania. La elección de cuentas y la asignación fiscal deben revisarse profesionalmente antes del primer asiento.',
        'field' => [
            'template' => 'Plantilla',
        ],
        'action' => [
            'apply' => 'Aplicar plantilla',
        ],
        'flash' => [
            'applied' => 'Plantilla aplicada: :accounts cuentas, :tax_codes códigos de impuesto, :rules reglas creadas, :skipped omitidas.',
        ],
        'error' => [
            'unknown' => 'Plantilla de plan contable desconocida: :code',
        ],
    ],

    // Versteuerungsart (Feature 125, MVP-679).
    'taxation' => [
        'title' => 'Régimen de IVA',
        'subtitle' => 'Devengo o caja — afecta solo al informe de IVA.',
        'current' => 'Actual: :method',
        'default_hint' => 'Sin ajuste rige el criterio de devengo (§ 16 ap. 1 UStG).',
        'field' => [
            'method' => 'Régimen',
            'valid_from' => 'Válido desde',
        ],
        'hint' => [
            'method' => 'El criterio de caja (§ 20 UStG) requiere autorización; el IVA soportado no se ve afectado.',
            'valid_from' => 'Habitualmente al cambio de año — se propone el próximo 1 de enero.',
        ],
        'column' => [
            'changeover' => 'Partidas abiertas al cambio',
        ],
        'action' => [
            'switch' => 'Cambiar el régimen',
            'switch_submit' => 'Registrar el cambio',
        ],
        'changeover' => [
            'headline' => ':count partidas abiertas por :amount están afectadas en la fecha de referencia.',
            'hint' => '§ 20 fr. 3 UStG: las operaciones no deben registrarse dos veces ni quedar sin tributar. El cambio no se bloquea — la valoración corresponde a la asesoría fiscal.',
            'summary' => ':count partidas / :amount',
        ],
        'flash' => [
            'switched' => 'Régimen de IVA cambiado.',
        ],
        'error' => [
            'unchanged' => 'Este régimen ya rige en esa fecha.',
            'later_section' => 'Ya existe un periodo posterior desde el :date.',
        ],
    ],
    // Klärungsbuchung und interne Umbuchung (Feature 125, MVP-681).
    'clearing' => [
        'title' => 'Asiento en suspenso',
        'memo' => 'Caso por aclarar: :purpose',
        'no_account' => 'No hay ninguna cuenta puente configurada. Marque una cuenta del plan contable como cuenta puente.',
        'action' => [
            'post' => 'Contabilizar en cuenta puente',
            'post_submit' => 'Crear el asiento en suspenso',
        ],
        'field' => [
            'account' => 'Cuenta puente',
            'note' => '¿Por qué no está claro este movimiento?',
            'follow_up_on' => 'Fecha de revisión',
        ],
        'hint' => [
            'account' => 'Solo se ofrecen las cuentas marcadas expresamente como cuentas puente.',
            'note' => 'Obligatorio — es la única constancia de por qué aquí no se asignó nada.',
            'follow_up_on' => 'El caso debe resolverse antes de esta fecha.',
        ],
        'error' => [
            'not_a_clearing_account' => 'La cuenta elegida no es una cuenta puente.',
            'note_required' => 'La motivación es obligatoria.',
        ],
        'blocker' => [
            'unassigned' => 'Sin documento asignado — solo contabilizable mediante una asignación o la cuenta puente.',
        ],
        'flash' => [
            'posted' => 'Asiento en suspenso creado.',
        ],
    ],
    'transfer' => [
        'title' => 'Traspaso interno',
        'action' => [
            'record' => 'Traspaso interno',
            'record_submit' => 'Contabilizar el traspaso',
        ],
        'field' => [
            'from_account' => 'Desde la cuenta',
            'to_account' => 'A la cuenta',
        ],
        'hint' => [
            'note' => '¿Para qué se movió el dinero (p. ej. retirada del banco para la caja)?',
        ],
        'error' => [
            'same_account' => 'La cuenta de origen y la de destino deben ser distintas.',
            'not_a_money_account' => 'La cuenta :account no es una cuenta bancaria, de caja ni puente.',
            'amount_positive' => 'El importe debe ser mayor que cero.',
        ],
        'flash' => [
            'recorded' => 'Traspaso contabilizado.',
        ],
    ],

    // Meldepflichten der Umsatzsteuer (Feature 125, MVP-684).
    'filing' => [
        'fields' => [
            'title' => 'Casillas de la declaración',
            'subtitle' => 'Asignación de los códigos de IVA a las casillas de la declaración alemana — ayuda de conciliación, no el formulario.',
            'tax_codes' => 'Códigos de IVA',
            'remaining' => 'Pago anticipado restante (83)',
            'unclear' => 'El código de IVA :code no tiene casilla.',
            'column' => [
                'field' => 'Casilla',
                'base' => 'Base imponible',
                'tax' => 'Cuota',
            ],
            'hint' => [
                'base' => 'Casilla de la base imponible, p. ej. 81 (19 %), 86 (7 %), 41 (entregas intracom.).',
                'tax' => 'Casilla de la cuota, p. ej. 66 (IVA soportado), 61 (adquisición intracom.).',
            ],
            'flash' => [
                'saved' => 'Casillas guardadas.',
            ],
        ],
        'calendar' => [
            'menu' => 'Plazos fiscales',
            'title' => 'Plazos fiscales',
            'subtitle' => 'Plazos del IVA y su estado.',
            'hint' => 'Los plazos se calculan (§ 108 ap. 3 AO: fines de semana y festivos pasan al siguiente día hábil). No se transmite nada.',
            'tax_advised' => 'con asesoría fiscal',
            'overdue' => 'Vencido',
            'empty' => 'Sin plazos en el periodo.',
            'column' => [
                'kind' => 'Obligación',
                'due_on' => 'Vencimiento',
            ],
            'action' => [
                'submitted' => 'Marcar como presentado',
            ],
        ],
        'notification' => [
            'title' => ':kind está próxima',
            'message' => 'Periodo :period — vencimiento :due.',
        ],
        'no_period' => 'Esta organización no tiene periodo de liquidación (pequeño empresario § 19 UStG).',
        'prepayment_memo' => 'Pago anticipado especial 1/11 para :year',
        'prepayment' => [
            'title' => 'Contabilizar el pago anticipado',
            'submit' => 'Contabilizar el pago',
            'calculation' => 'Un onceavo de :year: impuesto :tax, anualizado :annualised → :amount.',
            'annualised_hint' => 'Solo :months meses de actividad el año pasado — anualizado (§ 47 ap. 3 UStDV).',
            'due_hint' => 'Declaración y pago hasta el :date.',
        ],
        'title' => 'Obligaciones de declaración',
        'subtitle' => 'Periodo de liquidación del IVA, prórroga permanente y plazos.',
        'current' => 'Actualmente: :interval',
        'default_hint' => 'Sin ajuste rige el trimestre natural (§ 18 ap. 2 fr. 1 UStG).',
        'field' => [
            'period' => 'Periodo',
            'remaining' => 'Pago anticipado restante',
            'prepayment_account' => 'Cuenta de pago anticipado',
            'money_account' => 'Cuenta de tesorería',
            'interval' => 'Periodo de liquidación',
            'valid_from' => 'Válido desde',
            'year' => 'Año natural',
            'granted_on' => 'Concedida el',
            'special_prepayment' => 'Pago anticipado especial (1/11)',
        ],
        'hint' => [
            'prepayment_account' => 'Habitualmente 1781 (SKR03) o 3830 (SKR04) — pagos anticipados de IVA 1/11.',
            'interval' => 'El periodo lo decide la administración tributaria — el programa solo lo registra.',
            'valid_from' => 'Por regla general un cambio de año; también es posible a mitad de año.',
            'granted_on' => 'Dejar vacío mientras no se haya concedido la prórroga.',
            'special_prepayment' => 'Un onceavo de los pagos anticipados del año anterior; declaración y pago hasta el 10 de febrero (§ 47 UStDV).',
        ],
        'action' => [
            'switch' => 'Cambiar periodo',
            'switch_submit' => 'Aplicar periodo',
        ],
        'error' => [
            'note_required' => '«No necesario» requiere una justificación.',
            'amount_positive' => 'El importe debe ser mayor que cero.',
            'not_a_money_account' => 'La cuenta elegida no es una cuenta bancaria ni de caja.',
            'no_extension' => 'Para :year no hay ninguna prórroga registrada.',
            'unchanged' => 'Ese periodo de liquidación ya rige en esa fecha.',
            'later_section' => 'Ya existe una sección desde el :date. Modifique primero esa.',
        ],
        'flash' => [
            'marked' => 'Estado registrado.',
            'prepayment_posted' => 'Pago anticipado contabilizado.',
            'switched' => 'Periodo de liquidación cambiado.',
            'extension_saved' => 'Prórroga guardada.',
        ],
        'suggestion' => [
            'headline' => 'Propuesta a partir de :year (impuesto :amount): :interval.',
            'monthly' => 'Más de 9.000 € de impuesto del año anterior — mensual (§ 18 ap. 2 fr. 2 UStG).',
            'quarterly' => 'Entre 2.000 € y 9.000 € — trimestre natural (§ 18 ap. 2 fr. 1 UStG).',
            'annual' => 'Hasta 2.000 € — posible exención de la declaración periódica (§ 18 ap. 2 fr. 3 UStG).',
            'none' => 'Sin declaración periódica de IVA (pequeño empresario § 19 UStG).',
            'founder_rule' => 'A partir del periodo impositivo 2027 las nuevas empresas vuelven a declarar mensualmente.',
        ],
        'extension' => [
            'short' => 'con prórroga',
            'title' => 'Prórroga permanente',
            'active' => 'Prórroga desde :year',
            'no_prepayment' => 'Quienes liquidan trimestralmente obtienen la prórroga sin pago anticipado especial (§ 46 UStDV).',
            'prepayment_note' => 'Pago anticipado especial :amount registrado para :year.',
        ],
    ],

    // Zusammenfassende Meldung (Feature 125, MVP-687).
    'recapitulative' => [
        'title' => 'Estado recapitulativo',
        'hint' => 'Declaración según § 18a UStG. La prórroga permanente NO se aplica aquí — el plazo sigue siendo el día 25 tras el periodo.',
        'due' => 'Plazo: :date',
        'interval' => 'Periodo: :interval',
        'total' => 'Entregas intracomunitarias',
        'column' => [
            'vat_id' => 'NIF-IVA',
        ],
        'unclear' => [
            'missing_vat_id' => 'Asiento :entry (:customer) sin NIF-IVA del destinatario.',
            'unknown_customer' => 'sin cliente',
        ],
    ],

];
