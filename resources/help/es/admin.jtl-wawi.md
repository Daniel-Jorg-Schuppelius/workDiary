---
title: "Conectar JTL-Wawi"
topic: admin.jtl-wawi
version: 3
keywords:
    - sistema de gestión de inventario
    - ERP
    - sincronización de stock
    - artículos padre
    - variantes
    - asignación de almacenes
    - EAN
    - GTIN
    - gestión de existencias
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary conecta JTL-Wawi como **sistema de gestión de inventario
principal**: los artículos (padre/hijo), los almacenes y las
existencias vienen de JTL; WorkDiary los lee y devuelve sus propios
movimientos de stock de forma controlada.

**Modos de funcionamiento:** Una Wawi *OnPremise* se conecta a través
de su instancia API local (crearla en el administrador de JTL, puerto
predeterminado 5883). Si la Wawi está en su propia red, hay que
permitir explícitamente las direcciones privadas — esta autorización
se audita. La *pasarela cloud* usa ID de cliente/secreto e ID de
tenant del portal de socios de JTL.

**Registro de la app (OnPremise):** Abrir primero en JTL-Wawi
«Admin > Registro de apps», luego iniciar aquí el registro y aprobar
la app en la Wawi. La clave API se emite **una sola vez** y se guarda
cifrada — nunca aparece en registros ni diagnósticos.

**Asignaciones:** Tras la primera sincronización, asigne los almacenes
JTL a los almacenes de WorkDiary (1:1 para los asientos). Los
artículos se asignan automáticamente por SKU y GTIN; los casos dudosos
llegan a la bandeja de integraciones donde usted decide — WorkDiary
nunca crea artículos automáticamente.

**Liderazgo de existencias:** En «Liderazgo de existencias» elige
quién lidera: *local* (WorkDiary), *externo* (lidera JTL, WorkDiary
devuelve por la outbox) o *solo lectura*. Volver a «local» importa las
existencias de JTL como inventario de apertura.

**Nota beta:** La API de JTL-Wawi funciona actualmente como programa
beta/piloto. Tras el lanzamiento oficial puede depender de la edición
y pasar a ser de pago; una licencia caducada lleva a un estado
bloqueado visible, nunca a asientos erróneos silenciosos.

**Volver a ejecutar el inventario de apertura:** si la importación falló al cambiar a «local», iníciela de nuevo el mismo día con «Ejecutar inventario de apertura»; una segunda ejecución no contabiliza nada dos veces. A partir del día siguiente mandan las existencias locales y la acción deja de ofrecerse.

**Soberanía de datos:** La gestión de existencias de esta página es también la gestión del área de existencias en la página «Soberanía de datos», que solo la muestra. Si otro sistema gestiona las existencias, el inventario de traspaso no contabiliza nada para el par afectado; el caso queda en la bandeja de integraciones para asumirlo una vez.

**Existencias gestionadas en otro lugar:** si otro programa (como Billbee) gestiona las existencias, la página lo indica y no cambia el modo; de lo contrario, el inventario de traspaso de JTL-Wawi se aplicaría a existencias que no gestiona.
