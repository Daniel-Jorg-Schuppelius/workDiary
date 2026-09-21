---
title: "Webhooks"
topic: admin.webhooks
version: 1
audience:
    - admin
related:
    - admin.notification-rules
    - admin.handbook
    - glossary.core
---

Los webhooks envían notificaciones de eventos a sistemas externos:
cuando ocurre un evento suscrito, WorkDiary entrega una carga JSON
firmada por `POST` HTTPS a su URL. Cree el webhook con nombre y URL de
destino, suscriba los **eventos** deseados, copie la **clave de
firma** (se muestra en claro una sola vez y puede rotarse) y verifique
la entrega con **Enviar evento de prueba**. La carga es mínima y pobre
en datos personales; cada entrega lleva cabeceras de firma
(HMAC-SHA256 sobre `timestamp.body`) que debe verificar en tiempo
constante, descartando marcas de tiempo antiguas. Las entregas
fallidas se reintentan con backoff y, tras varios fallos consecutivos,
el endpoint se **desactiva automáticamente**; guárdelo como activo
para reactivarlo.
