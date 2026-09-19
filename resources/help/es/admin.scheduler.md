---
title: "Trabajos programados"
topic: admin.scheduler
version: 1
audience:
    - admin
related:
    - admin.diagnostics
    - admin.operations
---

Esta página muestra todos los trabajos en segundo plano recurrentes de
la plataforma — desde el housekeeping y la sincronización de
integraciones hasta las escaladas de plazos.

**Registro en lugar de proliferación:** Todos los trabajos
planificables proceden de un registro central con un **plan estándar**
predefinido. Solo los trabajos registrados allí aparecen aquí y pueden
gestionarse — deliberadamente, en esta página no es posible programar
comandos arbitrarios.

**Vista general:** Por cada trabajo verá el plan efectivo con su
**origen** (estándar, ajuste o reprogramación manual), la última
ejecución con su resultado, un contador de errores y el próximo
vencimiento. Así reconoce de un vistazo si un trabajo está bloqueado o
falla de forma persistente.

**Reprogramar con barreras de seguridad:** Cada trabajo define qué
cadencias están permitidas para él (p. ej. cada hora o diariamente a
una hora determinada). Reprogramar solo es posible dentro de esas
cadencias permitidas — así un trabajo crítico no puede ponerse por
descuido en un ritmo inadecuado. Las expresiones cron libres quedan
reservadas al operador. Mediante **Restablecer**, un trabajo vuelve en
cualquier momento a su plan estándar.

**Ventana de funcionamiento:** si el servidor no funciona las 24
horas –por ejemplo, porque se apaga por la noche–, indica en
**Ajustes** el inicio y el fin de la ventana de funcionamiento (un fin
de 00:00 significa medianoche). Las tareas con hora fija fuera de la
ventana se ejecutan entonces agrupadas en las dos primeras horas de
funcionamiento, en su orden original y nunca antes de lo previsto. La
vista general muestra la hora efectiva e indica desde qué hora se
desplazó una tarea; el watchdog comprueba con esa hora. Los intervalos
horarios y más cortos no cambian.

**Pausar y ejecución de prueba:** Los trabajos pueden pausarse y
reanudarse más tarde — un trabajo pausado deja de vencer, pero sigue
visible en la vista general. Una **ejecución de prueba** inicia el
trabajo de inmediato fuera de turno; entre dos ejecuciones de prueba
rige un breve período de bloqueo para que las ejecuciones no se
solapen.

**Constancias de ejecución:** Cada ejecución se registra con inicio,
duración y resultado. Las constancias se conservan durante un período
configurable (por defecto 30 días) y después se depuran
automáticamente.

**Watchdog:** Un trabajo de supervisión propio comprueba el propio
planificador: si las ejecuciones vencidas no se producen o los errores
se acumulan, de ello surgen tareas operativas o advertencias. Así
también se detecta un planificador completamente parado — y no solo
cuando faltan los informes.

**Recomendación:** Modifique los planes con moderación y observe las
siguientes ejecuciones tras cada reprogramación. Un contador de errores
elevado de forma persistente es un caso para el diagnóstico, no para
pausar.
