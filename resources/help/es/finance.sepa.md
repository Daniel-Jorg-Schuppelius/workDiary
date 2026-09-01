---
title: "Pagos salientes SEPA"
topic: finance.sepa
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.incoming-invoices
    - invoices.manage
    - contacts.manage
---

La remesa agrupa las facturas recibidas aprobadas en una transferencia SEPA
conjunta. workDiary genera un **fichero, no una orden de pago**: el pago se
lanza en el programa bancario con su propia autorización.

**Propuesta de pago:** La lista contiene todas las facturas recibidas
abiertas aprobadas para el pago. Para cada una se propone la fecha de
ejecución más ventajosa — la fecha de descuento mientras sea alcanzable y, en
otro caso, el vencimiento. El importe ya viene entonces reducido por el
descuento. Cada posición puede desmarcarse; una factura sin IBAN se muestra
como bloqueada y no entra en la remesa.

**Tres pasos:** componer (borrador) → aprobar → exportar. La aprobación es un
permiso propio: quien compone la remesa no tiene por qué poder aprobarla.
Tras la exportación la remesa es inmutable; la anulación solo es posible
antes y devuelve las facturas al estado pagadero.

**Deducción:** Algunas posiciones pueden fijarse a un importe menor mientras
la remesa sea un borrador — por ejemplo, ante una retención por defectos
frente al proveedor. Un importe reducido exige un motivo; importe facturado e
importe pagado quedan entonces uno junto al otro.

**Prueba:** El fichero generado se archiva como documento confidencial y su
hash SHA-256 queda registrado en la remesa. Una segunda descarga devuelve el
mismo fichero — nunca uno nuevo con otro identificador de mensaje, que el
banco podría entender como un segundo pago.

**Mandatos y adeudo:** Para el adeudo directo, el registro de mandatos guarda
la referencia, la fecha de firma y el tipo (puntual/recurrente). Un mandato
nunca se borra, sino que se revoca — la revocación es la prueba de desde
cuándo ya no se podía adeudar. Tras 36 meses sin adeudos, un mandato caduca.
El plazo de preaviso es de cinco días hábiles bancarios para el primer adeudo
y de dos para los siguientes. El adeudo requiere el identificador de acreedor
de la organización (ajuste «identificador de acreedor» en el registro de ajustes).

**Módulo adicional:** La generación del fichero pertenece al módulo de pago
de formatos bancarios. Sin él, la remesa y el registro de mandatos siguen
siendo utilizables; solo falta la exportación.
