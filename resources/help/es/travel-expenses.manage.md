---
title: "Viajes, gastos y dietas"
topic: travel-expenses.manage
version: 1
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

El libro de viajes, los gastos y las dietas documentan los
desplazamientos de trabajo por separado, pero con periodo y
justificantes comunes. Flujo típico: registrar el viaje con fecha,
trayecto, motivo, vehículo y kilometraje; añadir gastos con categoría,
importe, forma de pago y justificante; en viajes de varios días dejar
calcular la dieta a partir de horarios y destino; revisar y enviar a
aprobación o liquidación. Justificantes, kilometrajes y horarios deben
ser plausibles; los registros aprobados o liquidados no se modifican en
silencio.

## Transferir un gasto a la contabilidad como comprobante

Un gasto **aprobado** puede transferirse directamente desde el diálogo de
comprobantes al sistema contable principal como comprobante de compra — en
lugar de registrarlo allí por segunda vez. El ID externo vuelve al crearlo; el
duplicado no puede ni surgir.

Tres reglas:

- **Solo gastos aprobados.** La transferencia es irrevocable — el sistema de
  destino no conoce ni modificación ni borrado de comprobantes. Las
  correcciones se hacen allí con un contracomprobante.
- **Sin categoría contable no hay transferencia.** La asignación se mantiene
  por categoría de gasto (Administración → Categorías de gasto); una categoría
  adivinada sería peor que el mensaje de error.
- **Desde la transferencia manda el comprobante.** El vínculo ya no puede
  deshacerse — el comprobante existe, vinculado o no.

Los archivos del gasto se suben también — sin archivo, el comprobante no vale
nada para la contabilidad.

### Corrección con contracomprobante

Si algo no cuadra en un gasto ya transferido, lo corrige en el diálogo del
comprobante **con un contracomprobante**, indicando obligatoriamente el motivo.
Se transfiere una nota de abono de compra por el mismo importe que anula el
comprobante original en contabilidad. Al mismo tiempo se crea un nuevo gasto como
**borrador** con referencia al anterior; pasa por aprobación y transferencia como
cualquier otro.

Si el gasto original estaba aprobado pero aún no reembolsado, se anula; de lo
contrario se pagarían ambos. Si ya estaba reembolsado, el borrador avisa de que
solo debe reembolsarse la diferencia.

## Escanear el comprobante en vez de teclearlo

En lugar de introducir importe, fecha y comercio a mano, puede **fotografiar
el comprobante o subirlo como PDF**. El reconocimiento lee los campos
habituales y rellena el formulario.

El resultado es una **propuesta**, no un asiento terminado: compruebe importe,
fecha, tipo impositivo y comercio antes de guardar. Las fotos mal iluminadas,
el papel térmico y los comprobantes manuscritos son las fuentes más frecuentes
de lecturas erróneas.

El comprobante original queda adjunto sin cambios: el reconocimiento no lo
sustituye, solo le ahorra teclear.
