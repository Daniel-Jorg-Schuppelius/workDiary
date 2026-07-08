---
title: "Datos de demostración"
topic: admin.demo-data
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Los datos de demostración sirven para llenar una organización con
datos de ejemplo para pruebas y formación, según el sector elegido.
**Generar (seed)** crea datos de ejemplo (clientes, entradas de diario)
para el sector seleccionado; **Restablecer (reset)** reinicia un
inquilino de demostración. El restablecimiento solo se permite en
inquilinos marcados como demo (`is_demo`) para proteger los datos
reales, y en ellos sobrescribe o elimina los datos de demostración
existentes. Ambas acciones requieren permisos propios y se registran
en el log de auditoría; comprueba antes de generar si la organización
realmente debe estar vacía.
