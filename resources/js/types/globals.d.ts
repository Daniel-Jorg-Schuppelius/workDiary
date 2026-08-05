// Globale Fenster-API der Dialog-Infrastruktur (definiert in resources/js/layout.js,
// vorgeschrieben in AGENTS.md §4.7 als Ersatz für alert()/confirm()/prompt()).
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

    interface Window {
        confirmAction?: (
            opts: string | ConfirmActionOptions,
        ) => Promise<boolean>;
        notifyAction?: (opts: string | NotifyActionOptions) => Promise<void>;

        // Untypisierte globale Interop — via window.* exponiert (Third-Party
        // ohne @types bzw. projekteigene Laufzeit-Helfer/Seed-Daten aus dem
        // Layout). Bewusst `any`, da sie dynamisch aufgerufen, per `new`
        // instanziiert, gelesen und geschrieben werden.
        Alpine?: any;
        Echo?: any;
        Pusher?: any;
        SignaturePad?: any;
        __?: any;
        __formats?: any;
        __initFlatpickr?: any;
        __layout?: any;
        __scheduleConfig?: any;
        __theme?: any;
        __translations?: any;
        refreshChatChannelList?: any;
        refreshChatUnread?: any;
        workDiaryMap?: any;
    }
}
