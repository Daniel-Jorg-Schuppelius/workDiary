---
title: "Alcance funcional"
topic: scope.overview
version: 1
keywords:
    - activar módulos
    - desactivar módulos
    - ocultar funciones
    - simplificar el menú
    - selección de módulos
    - ajuste predefinido
    - página de inicio por rol
    - página de entrada
    - desactivar funcionalidades
audience:
    - admin
related:
    - admin.handbook
    - navigation.customize
---

La página **Alcance funcional** define qué módulos utiliza visiblemente su
organización. Es un atajo para la configuración de módulos: solo se cambia el
estado del módulo — **nunca se eliminan datos** y todo vuelve al reactivar.

## Preajustes

Un preajuste (p. ej. «Inicio ligero» o «Servicio y oficios») cambia la lista
de módulos en un solo paso. Después puede ajustar módulos individuales.

## Recomendación del perfil sectorial

Si su organización tiene un perfil sectorial instalado, la página muestra su
recomendación de módulos. Nunca se aplica automáticamente: la confirma usted.

## Página de inicio por rol

Debajo de los módulos define adónde llega un rol tras iniciar sesión, por
ejemplo el reloj de fichaje para el personal de campo o el flujo de
comprobantes para contabilidad. La elección propia en el perfil siempre tiene
prioridad. Si una persona tiene varios roles, se aplica el primero del orden
mostrado que tenga una página asignada. Una página que la persona no puede
abrir se omite; sin asignación se mantiene la predeterminada.

## Límites

- Los módulos sin licencia no se pueden activar aquí; eso requiere la
  gestión de licencias.
- Ocultar no cambia los permisos. Las páginas bloqueadas responden con un
  aviso (HTTP 423) en lugar de perder datos.

## Modo de alcance e inicio compacto

Desde la funcionalidad 165 la página muestra también el **modo de alcance**:
«Limitado a propósito» mantiene desactivados los módulos que se añadan más
tarde a la licencia hasta que los configure en *Ampliar el alcance
funcional*; «Alcance licenciado completo» activa de inmediato cualquier
ampliación. La tarjeta **Inicio compacto** lleva a la configuración con pocos
objetivos (clientes y pedidos, tiempo, planificación de equipo, presupuestos y
facturas) y, tras aplicarla, a la reversión que restaura el estado anterior.
Los cambios manuales posteriores se indican y solo se sobrescriben a petición.
