---
title: "Cuotas"
topic: club.fees
version: 1
audience: []
modules:
    - module.club
related:
    - club.members
    - club.groups
---

La gestión de cuotas forma parte de la base de la asociación y funciona sin graduaciones. Requiere el derecho «gestionar cuotas» (tesorería); la administración lee, las direcciones de grupo no ven datos de cuotas.

**Tarifas y tipos:** Las tarifas se nombran libremente (p. ej. adultos, niños, reducida, pasiva, familia) y llevan sus importes como tipos por fecha de validez: periodicidad (mensual, trimestral, semestral, anual), importe, ancla de facturación (mes inicial del periodo), vencimiento en días tras el inicio del periodo, regla de prorrateo y cuota de admisión opcional. Los tipos nuevos no modifican reclamaciones ya liberadas. Los recargos de sección son posiciones propias para socios con asignación activa a un grupo de la sección. No hay tarifas de asociación incorporadas.

**Cuentas de cuotas y obligados al pago:** Una cuenta de cuotas es la persona obligada al pago en el maestro de clientes/deudores. Un progenitor puede pagar por varios hijos sin ser socio. La asignación socio → cuenta y tarifa es explícita con periodo; nunca automática por coincidencia de correo o IBAN. Por socio rige como máximo una asignación a la vez.

**Cuota familiar:** Una tarifa familiar es una tarifa fija por hogar: todos los socios asignados a la cuenta con esa tarifa producen exactamente una posición de cuota base por periodo. Alternativamente quedan cuotas individuales con un descuento explícito en porcentaje y motivo. No existe una cuota base familiar e individual completa al mismo tiempo.

**Prorrateo:** Cada tipo es «periodo completo» o «por días». Por días cuenta los días naturales activos del periodo (asignación, alta/baja) dividido entre los días del periodo; cada posición se redondea a céntimos. Un alta a mitad de mes produce así la parte correspondiente.

**Exenciones:** Las exenciones y reducciones se registran expresamente con periodo y motivo. Una pausa de la afiliación por sí sola no exime de la cuota.

**Límites de edad:** Las tarifas pueden tener límites de edad. Si un socio ya no encaja en la fecha de referencia, la comprobación diaria marca la asignación para revisión; la gestión de cuotas confirma el cambio con fecha de efecto o mantiene la tarifa. Nada cambia automáticamente.

**Vista previa:** La vista previa de cuotas muestra para un mes de facturación todas las posiciones de los periodos que empiezan en ese mes con su base de cálculo. Las asignaciones incompletas o tarifas sin tipo aparecen como errores, no se omiten en silencio. Las reclamaciones surgen solo con la ejecución de cuotas.

**Ejecución de cuotas y reclamaciones:** Una ejecución congela la vista previa de un mes de facturación (todos los periodos que empiezan en ese mes). Los errores de la vista previa bloquean la liberación. La liberación crea exactamente una reclamación con posiciones por cuenta de cuotas; repetir, recuperar o ejecutar en paralelo no crea duplicados porque cada origen y periodo solo puede reclamarse una vez. Los importes liberados no cambian con cambios posteriores de tarifa o familia. Si la soberanía de facturación es externa (p. ej. Lexoffice), la vista previa y la lista de traspaso siguen disponibles; la liberación local queda bloqueada.

**Notificación de cuotas:** Cada reclamación tiene una notificación PDF con periodo, desglose, vencimiento y concepto de pago (el número de la reclamación). El envío por correo es independiente de la liberación; cada intento recibe un justificante de entrega y los errores siguen visibles. El texto al pie (p. ej. nota sobre asignación fiscal/documental) se define en los ajustes de la asociación.

**Partidas abiertas, anulación, corrección:** Las reclamaciones están abiertas, parcialmente pagadas, pagadas o anuladas; el vencimiento se deriva de la fecha y el importe restante. Una reclamación sin pagos puede anularse con motivo y los periodos quedan libres para una nueva ejecución. Las correcciones son reclamaciones propias vinculadas (reclamación adicional o abono); los importes liberados nunca se sobrescriben. Una baja finaliza las cuotas futuras, pero no elimina partidas abiertas existentes.

**Pagos:** Los pagos son apuntes propios en la cuenta de cuotas (transferencia, efectivo, adeudo SEPA, otro). Sin reclamación elegida se asignan por vencimiento: un pago conjunto de la familia cubre varias reclamaciones; el resto queda como saldo a favor y puede compensarse con reclamaciones posteriores. El mismo dinero nunca se cuenta dos veces: si la conciliación bancaria detecta un abono cuyo importe ya está registrado manualmente o por cobro, vincula el movimiento a ese pago en lugar de crear un segundo. Deshacer la asignación en la conciliación solo revierte el pago bancario; un pago registrado manualmente permanece.

**Devolución:** Una devolución compensa exactamente un pago (una sola vez), reabre el resto y bloquea el nuevo cobro hasta que la gestión de cuotas levante el bloqueo expresamente. Una comisión bancaria se crea como reclamación adicional vinculada; la reclamación original no cambia.

**Reclamación:** Solo se reclaman reclamaciones vencidas y no bloqueadas, en como máximo tres niveles (recordatorio, reclamación, última reclamación). Plazo y gastos se fijan conscientemente por reclamación, no se toman de valores por defecto de facturas; los gastos son una reclamación vinculada propia. La reclamación existe como PDF y correo con justificante de entrega. Para reclamaciones en disputa o aplazadas hay un bloqueo con motivo.

**Cobro SEPA:** La propuesta de cobro lista importes restantes vencidos de cuentas con mandato utilizable (el mandato activo del cliente o uno fijado en la cuenta). Una remesa es un lote de adeudos del módulo financiero: liberación y exportación pain.008 ocurren allí. Cada intento recibe una referencia única (número de reclamación y de intento) y la remesa reserva la posición contra un nuevo cobro. La exportación no es un pago: solo «Registrar abono» tras el abono marca la reclamación como pagada. Sin el paquete financiero siguen siendo posibles pagos, reclamación y preparación; solo no la exportación SEPA.
