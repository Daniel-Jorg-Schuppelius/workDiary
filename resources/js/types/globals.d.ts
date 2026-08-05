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
        confirmAction?: (opts: string | ConfirmActionOptions) => Promise<boolean>;
        notifyAction?: (opts: string | NotifyActionOptions) => Promise<void>;
    }
}
