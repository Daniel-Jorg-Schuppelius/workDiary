---
title: "Expediente de admisión"
topic: sales.lead-intakes
version: 1
keywords:
    - Expediente de admisión
    - Admisión
    - Aceptación de clientes
    - Verificación de nuevos clientes
    - Valoración inicial
    - Blanqueo de capitales
    - GwG
    - Identificación
    - Titulares reales
    - Persona con responsabilidad pública
    - Nivel de riesgo
    - Copia del documento de identidad
    - Paquete de verificación
    - Segunda revisión
audience: []
modules:
    - module.vertrieb
related:
    - sales.leads
    - customers.deadlines
    - forms.fill
---

El **expediente de admisión** pertenece al lead y acompaña a un nuevo cliente
desde la consulta hasta el alta: formulario de entrevista, documentos, las
verificaciones que exige el perfil del sector y la decisión. Solo después el
lead pasa a ser cliente, con el alcance de servicios y las características de
plazos tomados del expediente.

## Abrir el expediente

En el lead aparece el panel **Expediente de admisión** con «Abrir admisión».
El expediente toma los paquetes de verificación activados en ese momento y los
congela: si un paquete cambia después, el expediente queda como estaba.

## Fases

**Consulta → Entrevista → Documentos → Decisión → Alta.** Las primeras fases se
eligen libremente; el alta resulta de la aceptación y termina con la conversión
del lead («dado de alta»). El mosaico **Admisiones** del panel cuenta los
expedientes abiertos por fase.

## El formulario

Es un formulario largo con secciones, progreso e índice. Cada campo se guarda
al salir de él; el **modo entrevista** amplía las zonas táctiles para la tableta
durante la reunión. La parte básica recoge solicitud, servicios deseados,
inicio y notas; después vienen las **características de plazos** de los
conjuntos de reglas activados y, por paquete, sus campos y su **lista de
documentos** con casillas y ficheros.

## Paquetes de verificación

| Paquete | Perfil | Contenido |
| --- | --- | --- |
| Ley contra el blanqueo (GwG) | Asesoría fiscal (activado) | Identificación, verificación con documento, titulares reales, registro de transparencia, persona con responsabilidad pública, propósito, factores de riesgo, nivel de riesgo |
| Admisión de cuidados | Cuidados a domicilio (activado) | Grado de dependencia y resolución, tipo de prestación, poderes y tutela, instrucciones previas, contactos de emergencia, médico de cabecera, contrato |
| Nuevo cliente B2B | IT, limpieza, oficios (activable) | NIF-IVA (comprobación formal), informe de solvencia, plazo de pago, pago anticipado |

Los paquetes se activan en **Admisiones → Ajustes** (permiso «Decidir
admisiones»). Allí se fijan también la **actualización por nivel de riesgo**
(por defecto anual) y la **segunda revisión**.

## Decisión

Aceptar o rechazar, siempre con motivo y solo con el permiso «Decidir
admisiones». La aceptación comprueba todos los datos obligatorios y las reglas
del paquete: con una persona con responsabilidad pública el nivel de riesgo
debe ser alto, «bajo» y «alto» exigen motivo, y quien verificó con documento
de identidad o extracto del registro archiva la copia. Con la **segunda
revisión**, otra persona confirma la aceptación o la devuelve con motivo. La
aceptación con el paquete GwG crea la nota «Identificación y nota de riesgo»
en el lead.

**Conversión:** si hay activo un paquete con obligación de decisión (GwG,
admisión de cuidados), el lead solo pasa a cliente tras la aceptación. Sin ese
paquete, la conversión sigue como hasta ahora. Al convertir, el cliente recibe
los servicios deseados como alcance de servicios, las características de
plazos, el grado de dependencia y, como propuesta, NIF-IVA y plazo de pago.

## Visibilidad restringida

Los datos de identidad, la copia del documento y los datos de salud solo los
ven y registran personas con su permiso propio — quien ve al cliente no los ve
de otro modo: ni en el formulario, ni en los documentos, el PDF o el derecho
de acceso. Se guardan cifrados.

## Actualización y conservación

Tras la aceptación corre el **plazo de actualización** (GwG según nivel de
riesgo). Poco antes avisa una notificación y la cola de trabajo muestra el
expediente; «Actualizar» crea una nueva **versión** y la anterior queda como
historial.

El expediente GwG se conserva **cinco años desde el cierre del año natural**
en que termina la relación comercial (§ 8 apdo. 4 GwG). El fin se registra en
el panel del expediente en el cliente. Hasta entonces el expediente sobrevive a
la anonimización del lead y al archivado del cliente; después, el análisis de
conservación propone su eliminación. Las admisiones rechazadas cuentan desde
el rechazo. Los paquetes sin regla propia se eliminan con la anonimización
del lead.

## PDF

«PDF» genera el expediente en formato papel: tabla de cabecera, secciones,
casillas, cada paquete en su propia página, pie con fecha, cliente y número
de página. «Archivar como documento» guarda el PDF en el cliente o en el lead,
siempre sin datos de identidad ni de salud.
