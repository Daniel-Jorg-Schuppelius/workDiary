<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glossary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Begriffs-Glossar (Feature 039): Kurzdefinitionen für die x-term-Tooltips.
// Keys sind locale-übergreifend identisch (TranslationParityTest).
return [
    'flexzeit' => "Cuenta de horario flexible: diferencia acumulada entre tiempo de trabajo previsto y real.",
    'zuschlag' => "Recargo salarial por trabajo nocturno, dominical y festivo (parcialmente exento en derecho alemán).",
    'kostenstelle' => "Centro de costes: unidad contable para asignar costes de tiempo y salario.",
    'sla' => "Service Level Agreement: tiempos de respuesta y resolución garantizados por contrato.",
    'gobd' => "Principios alemanes de contabilidad ordenada: registros inmutables y auditables.",
    'aufmass' => "Medición en obra de la cantidad real de una partida (base de la facturación).",
    'nacharbeit' => "Retrabajo: tiempo para corregir defectos propios — no facturable, reduce el margen.",
    'kulanz' => "Cortesía comercial: trabajo deliberadamente no facturado para fidelizar al cliente.",
    'vvt' => "Registro de actividades de tratamiento (art. 30 RGPD).",
    'avv' => "Contrato de encargado del tratamiento con proveedores externos (art. 28 RGPD).",
    'tom' => "Medidas técnicas y organizativas para proteger los datos personales.",
    'dsfa' => "Evaluación de impacto de protección de datos para tratamientos de alto riesgo (art. 35 RGPD).",
    'soa' => "Statement of Applicability: declara qué controles ISO 27001 se aplican.",
    'meldebestand' => "Punto de pedido: nivel de existencias que activa propuestas de compra automáticas.",
    'vier_augen' => "Aprobación a cuatro ojos por una segunda persona independiente antes de ejecutar.",
    'backlog' => "Trabajos sin fecha fija — se incorporan durante la planificación.",
    'story_points' => "Medida relativa del esfuerzo de un elemento de trabajo — comparable dentro del equipo, nunca convertida en horas o dinero.",
    'wip' => "Work in progress: límite de elementos simultáneos por columna; superarlo requiere una justificación.",
    'velocity' => "Story points completados por sprint finalizado (mediana + rango) — magnitud de planificación, no de rendimiento.",
    'abnahme' => "Recepción: confirmación formal del cliente de que el trabajo se entregó según lo acordado — documentada mediante protocolo firmado; inicia garantía y facturación.",
    'prozedur' => "Procedimiento: instrucción de trabajo guiada paso a paso a partir de una plantilla versionada; cada ejecución se registra de forma trazable.",
    'zeitkonto' => "Cuenta de tiempo de trabajo: registra horas de más o de menos respecto al tiempo contractual — base para compensación en tiempo libre o pago.",
    'rfm_recency' => "Puntuación de recencia 1–5: ¿qué tan reciente es el último servicio? 5 = quintil superior (activo recientemente), 1 = inactivo por más tiempo.",
    'rfm_frequency' => "Puntuación de frecuencia 1–5: días de actividad en el período, como quintil sobre todos los clientes activos. 5 = clientes más frecuentes.",
    'rfm_monetary' => "Puntuación monetaria 1–5: ingresos en el período (tiempos facturables), como quintil. 5 = clientes con mayores ingresos.",
    'hhi' => "Índice de Herfindahl-Hirschman: suma de las cuotas de ingresos al cuadrado (en %). Por debajo de 1500 no crítico; por encima de 2500, alta concentración.",
    'dso' => "Days sales outstanding: cuentas por cobrar abiertas ÷ ingresos de los últimos 90 días × 90 — inmovilización media del capital en días.",
    'auslastung' => "Tiempo de trabajo registrado ÷ tiempo previsto del modelo de jornada, en porcentaje.",
    'abrechenbare_quote' => "Tiempo facturable ÷ tiempo registrado, en porcentaje — cuánto tiempo fluye hacia trabajo remunerado.",
    'realisierung' => "Tiempo facturado ÷ tiempo facturable, en porcentaje — cuánta parte del trabajo facturable llega realmente a las facturas.",
    'kohorte' => "Grupo de clientes con primer servicio en el mismo año — su actividad se sigue en los años posteriores.",
];
