// Globale Fenster-API der Dialog-Infrastruktur (definiert in resources/js/layout.js,
// vorgeschrieben in AGENTS.md §4.7 als Ersatz für alert()/confirm()/prompt()).
// flatpickr hängt sich beim Import an HTMLElement/Window (`_flatpickr`, `flatpickr()`).
/// <reference types="flatpickr/dist/types/globals" />
export {};

declare global {
    interface ConfirmActionOptions {
        title?: string;
        message?: string;
        icon?: string;
        label?: string;
        tone?: string;
    }

    interface NotifyActionOptions {
        title?: string;
        message?: string;
        icon?: string;
        tone?: string;
    }

    // Genutzter Ausschnitt von Web NFC (Chrome für Android); lib.dom kennt es nicht.
    interface NdefRecordLike {
        recordType: string;
        encoding?: string;
        data: BufferSource;
    }

    interface NdefReadingEventLike extends Event {
        message: { records: NdefRecordLike[] };
        serialNumber?: string;
    }

    interface NdefReaderLike extends EventTarget {
        scan(options?: { signal?: AbortSignal }): Promise<void>;
        write(
            message: string | { records: { recordType: string; data?: string }[] },
            options?: { signal?: AbortSignal },
        ): Promise<void>;
        addEventListener(
            type: "reading",
            listener: (event: NdefReadingEventLike) => void,
            options?: boolean | AddEventListenerOptions,
        ): void;
        addEventListener(
            type: string,
            listener: EventListenerOrEventListenerObject | null,
            options?: boolean | AddEventListenerOptions,
        ): void;
    }

    // Installationsangebot der PWA (nur Chromium); lib.dom kennt es nicht.
    interface BeforeInstallPromptEventLike extends Event {
        prompt(): Promise<void>;
        userChoice: Promise<unknown>;
    }

    interface Window {
        confirmAction?: (
            opts: string | ConfirmActionOptions,
        ) => Promise<boolean>;
        notifyAction?: (opts: string | NotifyActionOptions) => Promise<void>;

        // Bibliotheken und Module, die sich am Fenster ablegen.
        Alpine?: import("alpinejs").Alpine;
        Echo?: import("laravel-echo").default<"reverb">;
        Pusher?: typeof import("pusher-js").default;
        SignaturePad?: typeof import("signature_pad").default;
        NDEFReader?: new () => NdefReaderLike;
        workDiaryMap?: {
            initMap: typeof import("../map.js").initMap;
            addMarker: typeof import("../map.js").addMarker;
            drawRoute: typeof import("../map.js").drawRoute;
        };
        __?: (key: string, replace?: Record<string, unknown>) => string;
        __initFlatpickr?: (el: HTMLInputElement) => void;
        refreshChatChannelList?: () => void;
        refreshChatUnread?: () => void;

        // Seitendaten, die Blade vor den Modulen setzt (layouts/app, schedule/index, Hilfe-Export).
        __formats?: { date?: string; time?: string };
        __layout?: {
            themeUpdateUrl?: string;
            i18n?: Record<string, string>;
        };
        __scheduleConfig?: import("../schedule.js").ScheduleConfig;
        // ThemeService::seed()
        __theme?: {
            authenticated?: boolean;
            active?: string;
            autoLight?: string;
            autoDark?: string;
            schemes?: Record<string, string>;
            allowed?: string[];
        };
        __translations?: Record<string, string>;
        HELP_SITE_INDEX?: unknown;
    }
}
