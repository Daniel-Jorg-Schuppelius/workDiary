---
title: "Cierre e informes"
topic: accounting.closing
version: 4
keywords:
    - cierre mensual
    - cierre anual
    - bloquear periodo
    - reabrir periodo
    - cuenta de resultados
    - informes financieros
    - previsión de tesorería
    - presupuesto vs real
    - informe de IVA
    - exportación auditoría fiscal
    - reparto de costes
    - exportación DATEV
    - revisión fiscal DATEV
    - cuenta automática
    - BU 40
    - falta NIF-IVA
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
related:
    - accounting.overview
    - accounting.posting
---

Los **periodos** se cierran en dos pasos: *provisional* es una señal,
*definitivo* es un bloqueo. Una comprobación previa revisa borradores abiertos
y asientos descuadrados.

La **reapertura** exige un permiso propio, un motivo y queda registrada en la
cadena de prueba.

Los **informes** solo leen asientos contabilizados. IVA y criterio de caja son
**vistas previas** verificables: el MVP no presenta nada ante Hacienda.

**Sin contabilidad local:** si la organización lleva su contabilidad en un
programa contable, estos informes se abren igualmente pero muestran un aviso
arriba: solo evalúan los asientos de WorkDiary y por eso quedan vacíos o
incompletos. Los ingresos y las partidas abiertas del programa contable
aparecen en el análisis de facturación, el flujo de documentos y el mosaico
**Partidas abiertas**.

**Exportación de los informes:** PDF, CSV y Excel de los informes
financieros, del informe económico y del presupuesto requieren, además del
permiso de lectura, el permiso **Exportar los informes**; sin él no aparecen
los botones de exportación. Los administradores pueden exportar siempre.

**Traspaso**: el paquete de auditoría contiene plan contable, diario, líneas,
partidas abiertas y periodos; el traspaso DATEV nace de los asientos
contabilizados.

**Informe económico, presupuesto y liquidez:** De los mismos asientos en firme
surgen el **informe económico** (ingresos, costes y resultado por grupos), la
**comparación presupuestaria** por cuenta y centro de coste —los importes del
año anterior pueden tomarse como punto de partida— y la **previsión de
liquidez**. Los tres son análisis, no una segunda contabilidad: lo que ve allí
consta así en el diario. Por eso las correcciones se hacen siempre en el
asiento, nunca en el informe.

**Repartos, aprobación del presupuesto y partidas planificadas:** Las claves de
reparto distribuyen proporcionalmente los gastos de un centro de coste auxiliar
entre otros centros; el informe económico puede mostrarlo «tras el reparto»,
mientras los asientos permanecen sin cambios. Un presupuesto aprobado queda
bloqueado frente a cambios hasta que un suplemento motivado lo reabre; si un
asiento supera el presupuesto mensual, el asiento lo indica, pero se
contabiliza igualmente. En la previsión de liquidez usted añade **partidas
planificadas** (por ejemplo, pagos a cuenta de impuestos o cuotas de préstamos);
cada lunes la aplicación registra el estado semanal y «plan/real» lo
compara después con los movimientos bancarios reales.

**Revisión fiscal antes de la exportación DATEV:** **Revisar y exportar** en la
entrega a DATEV muestra primero los hallazgos del periodo, cada uno con enlace
al asiento. Los **errores** bloquean la exportación: una línea de ingreso o
gasto sin código de impuesto aunque el asiento muestra IVA repercutido o
soportado; una entrega intracomunitaria o inversión del sujeto pasivo en otro
país de la UE sin NIF-IVA del socio; un asiento en una cuenta desactivada. Los
**avisos** son para revisar: apuntes en los que la exportación anula el
automatismo de la cuenta con BU 40 (con número y cuenta), un tipo que difiere
del código de la cuenta automática y la inversión del sujeto pasivo nacional
sin NIF-IVA. La revisión lee las cuentas automáticas del plan contable y no
cambia el fichero. Un error desaparece en cuanto se corrigen el asiento o los
datos maestros. Si en su caso es correcto, una persona con el permiso
**Liberar la revisión fiscal antes de la exportación DATEV** lo libera con
motivo; la liberación queda en la cadena de pruebas de la contabilidad y solo
vale para los errores mostrados.
