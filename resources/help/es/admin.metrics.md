---
title: "Métricas"
topic: admin.metrics
version: 3
keywords:
    - indicadores
    - estadísticas
    - monitorización
    - uso del sistema
    - espacio de almacenamiento
    - usuarios activos
    - trabajos fallidos
    - cola
    - estadísticas de uso
    - rendimiento
    - métricas operativas
audience:
    - admin
related:
    - admin.diagnostics
    - admin.handbook
    - admin.backups
---

La página **Métricas operativas** muestra, en modo de solo lectura,
indicadores operativos y de rendimiento para supervisar el sistema.
Complementa el diagnóstico, que proporciona el estado tipo semáforo de
las comprobaciones de estado (health checks). Todas las métricas se
recopilan y almacenan exclusivamente en local; no se envía nada a
sistemas externos.

La página se divide en las siguientes secciones:

- **Versión** de la aplicación (en la cabecera de la página)
- **Cola**: **Trabajos pendientes** y **Trabajos fallidos**
- **Heartbeats de copia de seguridad**: copias de seguridad notificadas
  más recientes (momento, tamaño, origen)
- **Errores de plugins (7 días)**: número e incidentes más recientes
- **Almacenamiento**: cantidad y tamaño de **Adjuntos** y **Versiones
  de documentos** según los metadatos de la base de datos (la ocupación
  del disco la muestra el diagnóstico)
- **Usuarios activos (30 días)**: usuarios distintos con inicio de
  sesión según el registro de auditoría
- **Registros por módulo principal**: volúmenes p. ej. de **Encargos
  (diario)**, **Documentos**, **Protocolos** y **Artículos de
  conocimiento**
- **Uso de funciones (30 días)**: **Cantidad** y **Último uso** por
  función, agregados por organización y día
- **Transparencia de métricas**: qué contadores de uso se recopilan y
  si están activos en este momento

Los valores se recopilan de nuevo en cada consulta; si no están
disponibles, algunas secciones recurren a valores predeterminados
vacíos sin bloquear la página.

El acceso requiere el permiso **Ver las métricas operativas**.
Encontrará las comprobaciones de estado detalladas y el correo de
prueba en **Diagnóstico**.
