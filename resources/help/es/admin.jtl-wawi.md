---
title: "Conectar JTL-Wawi"
topic: admin.jtl-wawi
version: 1
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

**Asignaciones:** Tras la primera sincronización, asigna los almacenes
JTL a los almacenes de WorkDiary (1:1 para los asientos). Los
artículos se asignan automáticamente por SKU y GTIN; los casos dudosos
llegan a la bandeja de integraciones donde tú decides — WorkDiary
nunca crea artículos automáticamente.

**Liderazgo de existencias:** En «Liderazgo de existencias» eliges
quién lidera: *local* (WorkDiary), *externo* (lidera JTL, WorkDiary
devuelve por la outbox) o *solo lectura*. Volver a «local» importa las
existencias de JTL como inventario de apertura.

**Nota beta:** La API de JTL-Wawi funciona actualmente como programa
beta/piloto. Tras el lanzamiento oficial puede depender de la edición
y pasar a ser de pago; una licencia caducada lleva a un estado
bloqueado visible, nunca a asientos erróneos silenciosos.
