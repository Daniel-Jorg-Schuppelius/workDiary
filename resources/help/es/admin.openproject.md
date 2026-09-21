---
title: "Integración con OpenProject"
topic: admin.openproject
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.toggl
    - admin.import
---

La integración con OpenProject es **bidireccional**: importa tiempos
desde OpenProject y permite contabilizar de vuelta los tiempos
registrados. Primero ejecute la sincronización de estructura
(proyectos, work packages, usuarios), requisito para el import de
tiempos. Los proyectos sin asignación automática llegan a la bandeja
de entrada, donde los asigna a un proyecto existente, crea uno nuevo
o los descarta; los futuros imports usan las asignaciones guardadas.
Para la contabilización de vuelta (push) debe estar configurada una
**actividad estándar** (default_activity_id); revise los mappings
antes del primer push, ya que modifica datos en el sistema OpenProject
conectado.
