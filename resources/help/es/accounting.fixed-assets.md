---
title: "Registro de inmovilizado y amortización"
topic: accounting.fixed-assets
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.closing
    - accounting.posting
    - accounting.overview
---

El **registro de inmovilizado** es la vista contable de los bienes
duraderos: coste de adquisición o producción, vida útil, valor residual y
cuentas implicadas. Responde a la pregunta «cuánto vale todavía esta máquina
en la fecha de cierre», no «dónde está y cuándo se revisó por última vez».
Eso corresponde a la ficha del equipo.

**Equipo e inmovilizado son dos cosas distintas.** Vincularlos es posible
pero no obligatorio: una instalación puede activarse sin ficha de equipo, y
un equipo de escaso valor puede amortizarse de una vez. Confundirlos produce
o bien inmovilizado sin valor contable, o bien equipos que en la contabilidad
no existen.

## Qué se registra aquí

1. **Adquisición**: fecha, coste, moneda. El número lo asigna el sistema.
2. **Vida útil en meses** y **método de amortización**. Juntos determinan
   cómo se reparte el valor a lo largo de los años.
3. **Valor residual**, si al final de la vida útil queda un valor simbólico o
   un ingreso de venta esperado.
4. **Cuentas** de inmovilizado y amortización — dirigen el asiento.

## Cómo surge la amortización

Las líneas de amortización se **calculan, no se teclean**. El cierre las
propone por activo y ejercicio; la contabilización pasa exclusivamente por la
bandeja de asientos.

**El registro no contabiliza nada por sí solo.** Es deliberado: una
amortización es una decisión del cierre, no un efecto secundario del
mantenimiento de datos maestros. Crear un activo no altera ningún saldo.

## Amortización degresiva y amortización especial

La **amortización degresiva** deduce cada año un porcentaje fijo del valor
contable. Solo está permitida para adquisiciones dentro de los periodos
legales; el diálogo indica el tipo máximo para su fecha de adquisición y vida
útil. En cuanto el reparto lineal del valor restante resulta mayor, el plan
pasa por sí solo a la amortización lineal.

La **amortización especial según el § 7g** se registra en el activo como
importe por ejercicio: en el año de adquisición y los cuatro siguientes, como
máximo el 40 % del coste de adquisición en total. Después, el valor restante se
reparte sobre la vida útil restante. La aplicación no comprueba si su empresa
cumple los requisitos (límite de beneficio); aclárelo con su asesoría fiscal.
Un año cuya amortización ya se ha contabilizado no puede modificarse.

## Baja

Una baja (venta, desguace, robo) se anota con su fecha. El activo **no
desaparece** del registro — el historial sigue siendo legible; de lo
contrario, una conciliación posterior con el balance sería imposible.

## Clases de activo y activo a partir de un justificante

En «Clases de activo» crea valores por defecto, por ejemplo «Vehículos» o
«Software»: vida útil, método y cuentas. Si elige una clase al crear un activo
fijo, WorkDiary rellena con ella los campos que deje vacíos. Los activos
existentes conservan sus valores si modifica una clase más adelante.

En una factura recibida y en un gasto aprobado, «Registrar como activo fijo»
crea el activo directamente. Denominación, fecha e importe neto vienen
rellenados y el activo remite al justificante. De un justificante surge como
máximo un activo.
