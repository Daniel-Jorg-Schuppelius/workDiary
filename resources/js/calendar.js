/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : calendar.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";

document.addEventListener("DOMContentLoaded", () => {
    const el = document.getElementById("calendar");
    if (!el) return;

    const eventsUrl = el.dataset.eventsUrl;
    const locale = el.dataset.locale || "de";
    const initialView = el.dataset.view || "timeGridWeek";

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView,
        locale,
        firstDay: 1,
        height: "auto",
        nowIndicator: true,
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
        },
        buttonText: {
            today: "Heute",
            month: "Monat",
            week: "Woche",
            day: "Tag",
            list: "Liste",
        },
        slotMinTime: "06:00:00",
        slotMaxTime: "22:00:00",
        events: {
            url: eventsUrl,
            method: "GET",
            failure: () => {
                console.error("Konnte Kalender-Ereignisse nicht laden.");
            },
        },
        eventClick: (info) => {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
    });

    calendar.render();
});
