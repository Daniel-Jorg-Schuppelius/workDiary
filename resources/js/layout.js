/*
 * Layout-Infrastruktur (ausgelagert aus layouts/app.blade.php):
 * Hell/Dunkel-Umschalter + serverseitige Persistenz, zentraler Bestätigungs-
 * dialog (window.confirmAction) und Hinweis-/Toast-Dialog (window.notifyAction),
 * data-confirm-dialog-Form-Interception. Blade-Werte (Theme-Update-URL,
 * Übersetzungen) kommen über window.__layout (inline gesetzt, vor diesem Modul).
 */
(function () {
    var cfg = window.__layout || {};
    var I = cfg.i18n || {};
                    var root = document.documentElement;
                    var seed = window.__theme || {};
                    // Hell-/Dunkel-Theme dieses Kontextes (Org-Paar, sonst corporate/dim).
                    var lightTheme = seed.autoLight || 'corporate';
                    var darkTheme = seed.autoDark || 'dim';
                    var toggles = document.querySelectorAll('[data-theme-toggle]');
                    var labels = document.querySelectorAll('[data-theme-label]');

                    function schemeOf(theme) {
                        return (seed.schemes && seed.schemes[theme])
                            || (theme === 'dim' || theme === 'dark' || theme === 'business' ? 'dark' : 'light');
                    }
                    function isDark() {
                        return schemeOf(root.getAttribute('data-theme')) === 'dark';
                    }
                    function syncLabel() {
                        // Material-Symbol: aktuell hell → Mond (Klick → dunkel);
                        // aktuell dunkel → Sonne (Klick → hell).
                        var glyph = isDark() ? 'light_mode' : 'dark_mode';
                        labels.forEach(function (l) { l.textContent = glyph; });
                    }
                    // Beim Laden NICHT das data-theme überschreiben (das setzt bereits
                    // das Anti-Flash-Skript im <head> theme-bewusst) — nur das Label
                    // an den aktuellen Zustand angleichen.
                    syncLabel();

                    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    function persistScheme(scheme) {
                        // Eingeloggt zählt allein die DB-Wahl → der Farbmodus muss
                        // serverseitig persistiert werden, damit er den Reload übersteht.
                        // Es wird NUR der Modus gespeichert; welches Theme das ist,
                        // bestimmt das Org-Hell/Dunkel-Paar (ThemeService).
                        if (!seed.authenticated) return;
                        try {
                            fetch(cfg.themeUpdateUrl, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ scheme: scheme })
                            });
                        } catch (e) {}
                    }

                    toggles.forEach(function (toggle) {
                        toggle.addEventListener('click', function () {
                            // Hell/Dunkel umschalten: in das jeweils andere Schema des
                            // Org-Hell/Dunkel-Paares wechseln, sofort anwenden und den
                            // Modus serverseitig persistieren.
                            var goDark = !isDark();
                            var next = goDark ? darkTheme : lightTheme;
                            root.setAttribute('data-theme', next);
                            root.style.colorScheme = schemeOf(next);
                            try { localStorage.setItem('workDiaryTheme', next); } catch (e) {}
                            syncLabel();
                            persistScheme(goDark ? 'dark' : 'light');
                        });
                    });

                    var confirmDialog = document.getElementById('action-confirm-dialog');
                    var confirmTitle = document.getElementById('action-confirm-title');
                    var confirmMessage = document.getElementById('action-confirm-message');
                    var confirmSubmit = document.getElementById('action-confirm-submit');
                    var confirmHeader = document.getElementById('action-confirm-header');
                    var confirmIconWrap = document.getElementById('action-confirm-icon-wrap');
                    var confirmIcon = document.getElementById('action-confirm-icon');
                    var notifyDialog = document.getElementById('action-notify-dialog');
                    var notifyTitle = document.getElementById('action-notify-title');
                    var notifyMessage = document.getElementById('action-notify-message');
                    var notifyHeader = document.getElementById('action-notify-header');
                    var notifyIconWrap = document.getElementById('action-notify-icon-wrap');
                    var notifyIcon = document.getElementById('action-notify-icon');
                    var notifyOk = document.getElementById('action-notify-ok');
                    var pendingForm = null;
                    var pendingSubmitter = null;
                    var pendingResolve = null;
                    var pendingNotifyResolve = null;

                    var toneAccents = {
                        primary: { header: 'from-primary/15 via-primary/5 to-transparent', icon: 'bg-primary/15 text-primary' },
                        success: { header: 'from-success/15 via-success/5 to-transparent', icon: 'bg-success/15 text-success' },
                        warning: { header: 'from-warning/15 via-warning/5 to-transparent', icon: 'bg-warning/15 text-warning' },
                        error:   { header: 'from-error/15 via-error/5 to-transparent',     icon: 'bg-error/15 text-error' },
                        info:    { header: 'from-info/15 via-info/5 to-transparent',       icon: 'bg-info/15 text-info' },
                        ghost:   { header: 'from-base-300/40 via-base-200/30 to-transparent', icon: 'bg-base-300 text-base-content/70' }
                    };
                    var toneClasses = [];
                    Object.keys(toneAccents).forEach(function (k) {
                        toneAccents[k].header.split(' ').forEach(function (c) { if (toneClasses.indexOf(c) < 0) toneClasses.push(c); });
                        toneAccents[k].icon.split(' ').forEach(function (c) { if (toneClasses.indexOf(c) < 0) toneClasses.push(c); });
                    });

                    function applyTone(headerEl, iconWrapEl, tone) {
                        var accent = toneAccents[tone] || toneAccents.warning;
                        toneClasses.forEach(function (c) {
                            headerEl.classList.remove(c);
                            iconWrapEl.classList.remove(c);
                        });
                        accent.header.split(' ').forEach(function (c) { headerEl.classList.add(c); });
                        accent.icon.split(' ').forEach(function (c) { iconWrapEl.classList.add(c); });
                    }

                    // Rendert einen Icon-Bezeichner in das Icon-Element.
                    // Akzeptiert Material-Symbol-Namen (a-z0-9_) ODER beliebige
                    // Emojis/Roh-HTML als Backwards-Compat.
                    function renderIcon(el, value) {
                        if (!el) return;
                        var v = (value == null) ? '' : String(value);
                        if (v !== '' && /^[a-z0-9_]+$/.test(v)) {
                            el.innerHTML = '<span class="material-symbols-outlined">' + v + '</span>';
                        } else {
                            el.textContent = v;
                        }
                    }

                    function openConfirm(opts) {
                        opts = opts || {};
                        var tone = opts.tone || (opts.dangerous === false ? 'primary' : 'warning');
                        if (confirmTitle) {
                            confirmTitle.textContent = opts.title || I.confirmTitle;
                        }
                        if (confirmMessage) {
                            confirmMessage.textContent = opts.message || I.confirmMessage;
                        }
                        if (confirmIcon) {
                            renderIcon(confirmIcon, opts.icon || (opts.dangerous === false ? 'help' : 'warning'));
                        }
                        applyTone(confirmHeader, confirmIconWrap, tone);
                        confirmSubmit.textContent = opts.label || I.confirmLabel;
                        confirmSubmit.classList.remove('btn-error', 'btn-primary', 'btn-warning', 'btn-success', 'btn-info');
                        var btnTone = opts.dangerous === false ? 'btn-primary' : (tone === 'primary' ? 'btn-primary' : (tone === 'success' ? 'btn-success' : (tone === 'info' ? 'btn-info' : 'btn-error')));
                        confirmSubmit.classList.add(btnTone);
                        if (typeof confirmDialog.showModal === 'function') {
                            confirmDialog.showModal();
                        }
                    }

                    function openNotify(opts) {
                        opts = opts || {};
                        var tone = opts.tone || 'info';
                        var defaultIcons = { info: 'info', success: 'check_circle', warning: 'warning', error: 'cancel', primary: 'info', ghost: 'info' };
                        var defaultTitles = {
                            info:    I.notifyInfo,
                            success: I.notifySuccess,
                            warning: I.notifyWarning,
                            error:   I.notifyError
                        };
                        if (notifyTitle) {
                            notifyTitle.textContent = opts.title || defaultTitles[tone] || I.notifyInfo;
                        }
                        if (notifyMessage) {
                            notifyMessage.textContent = opts.message || '';
                        }
                        if (notifyIcon) {
                            renderIcon(notifyIcon, opts.icon || defaultIcons[tone] || 'info');
                        }
                        applyTone(notifyHeader, notifyIconWrap, tone);
                        if (notifyOk) {
                            notifyOk.classList.remove('btn-error', 'btn-primary', 'btn-warning', 'btn-success', 'btn-info');
                            var okTone = tone === 'error' ? 'btn-error' : (tone === 'warning' ? 'btn-warning' : (tone === 'success' ? 'btn-success' : 'btn-primary'));
                            notifyOk.classList.add(okTone);
                        }
                        if (typeof notifyDialog.showModal === 'function') {
                            notifyDialog.showModal();
                        }
                    }

                    // Programmatic API: returns Promise<boolean>
                    window.confirmAction = function (opts) {
                        if (!confirmDialog) {
                            return Promise.resolve(false);
                        }
                        return new Promise(function (resolve) {
                            pendingForm = null;
                            pendingSubmitter = null;
                            pendingResolve = resolve;
                            openConfirm(typeof opts === 'string' ? { message: opts } : opts);
                        });
                    };

                    // Programmatic API: returns Promise<void>
                    window.notifyAction = function (opts) {
                        if (!notifyDialog) {
                            try { window.alert((opts && opts.message) || String(opts || '')); } catch (e) { /* ignore */ }
                            return Promise.resolve();
                        }
                        return new Promise(function (resolve) {
                            pendingNotifyResolve = resolve;
                            openNotify(typeof opts === 'string' ? { message: opts } : opts);
                        });
                    };

                    if (notifyDialog) {
                        notifyDialog.addEventListener('close', function () {
                            var r = pendingNotifyResolve;
                            pendingNotifyResolve = null;
                            if (r) r();
                        });
                    }

                    if (confirmDialog && confirmSubmit) {
                        document.addEventListener('submit', function (event) {
                            var form = event.target;
                            if (!(form instanceof HTMLFormElement)) {
                                return;
                            }

                            if (!form.hasAttribute('data-confirm-dialog')) {
                                return;
                            }

                            event.preventDefault();
                            pendingForm = form;
                            pendingSubmitter = null;
                            pendingResolve = null;

                            openConfirm({
                                title:   form.getAttribute('data-confirm-title') || undefined,
                                message: form.getAttribute('data-confirm-message') || undefined,
                                label:   form.getAttribute('data-confirm-label') || undefined,
                                icon:    form.getAttribute('data-confirm-icon') || undefined,
                                tone:    form.getAttribute('data-confirm-tone') || undefined,
                            });
                        });

                        // Allow data-confirm-dialog on buttons / anchors directly.
                        document.addEventListener('click', function (event) {
                            var trigger = event.target.closest('[data-confirm-dialog]');
                            if (!trigger || trigger.tagName === 'FORM') {
                                return;
                            }
                            // If trigger is a submit button inside a data-confirm-dialog form, the
                            // form-level handler will catch it on submit. Otherwise handle here.
                            var ownerForm = trigger.form || (trigger.closest && trigger.closest('form'));
                            if (ownerForm && ownerForm.hasAttribute('data-confirm-dialog')) {
                                return;
                            }

                            event.preventDefault();
                            pendingResolve = null;
                            pendingSubmitter = null;

                            if (trigger.tagName === 'BUTTON' && (trigger.type === 'submit' || !trigger.type) && ownerForm) {
                                pendingForm = ownerForm;
                                pendingSubmitter = trigger;
                            } else if (trigger.tagName === 'A' && trigger.href) {
                                pendingForm = null;
                                pendingResolve = function (ok) {
                                    if (ok) window.location.href = trigger.href;
                                };
                            } else {
                                pendingForm = null;
                                pendingResolve = function (ok) {
                                    if (ok) trigger.dispatchEvent(new CustomEvent('confirmed-action', { bubbles: true }));
                                };
                            }

                            openConfirm({
                                title:   trigger.getAttribute('data-confirm-title') || undefined,
                                message: trigger.getAttribute('data-confirm-message') || undefined,
                                label:   trigger.getAttribute('data-confirm-label') || undefined,
                                icon:    trigger.getAttribute('data-confirm-icon') || undefined,
                                tone:    trigger.getAttribute('data-confirm-tone') || undefined,
                            });
                        });

                        confirmSubmit.addEventListener('click', function () {
                            var formToSubmit = pendingForm;
                            var submitter = pendingSubmitter;
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingSubmitter = null;
                            pendingResolve = null;
                            confirmDialog.close();
                            if (formToSubmit) {
                                if (submitter && typeof formToSubmit.requestSubmit === 'function') {
                                    formToSubmit.requestSubmit(submitter);
                                } else {
                                    formToSubmit.submit();
                                }
                            }
                            if (resolver) {
                                resolver(true);
                            }
                        });

                        confirmDialog.addEventListener('close', function () {
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingSubmitter = null;
                            pendingResolve = null;
                            if (resolver) {
                                resolver(false);
                            }
                        });
                    }

                    // Sidebar (Drawer) Toggle für < lg
                    var sidebar = document.getElementById('app-sidebar');
                    var sidebarToggle = document.getElementById('app-sidebar-toggle');
                    var sidebarBackdrop = document.getElementById('app-sidebar-backdrop');
                    var sidebarCollapse = document.getElementById('app-sidebar-collapse');
                    var sidebarCollapseIcon = document.querySelector('[data-sidebar-collapse-icon]');

                    // Collapse-State auf lg+ aus localStorage anwenden
                    function applyCollapsed(collapsed) {
                        document.body.classList.toggle('sidebar-collapsed', collapsed);
                        if (sidebarCollapseIcon) {
                            sidebarCollapseIcon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
                        }
                    }
                    try {
                        applyCollapsed(localStorage.getItem('workDiarySidebarCollapsed') === '1');
                    } catch (e) { /* ignore */ }

                    if (sidebarCollapse) {
                        sidebarCollapse.addEventListener('click', function () {
                            var next = !document.body.classList.contains('sidebar-collapsed');
                            applyCollapsed(next);
                            try { localStorage.setItem('workDiarySidebarCollapsed', next ? '1' : '0'); } catch (e) { /* ignore */ }
                        });
                    }

                    // Persistenz pro Sektion (<details data-sidebar-section-key="…">)
                    (function () {
                        var STORAGE_KEY = 'workDiarySidebarSections';
                        var store = {};
                        try {
                            var raw = localStorage.getItem(STORAGE_KEY);
                            if (raw) { store = JSON.parse(raw) || {}; }
                        } catch (e) { store = {}; }
                        var sections = document.querySelectorAll('#app-sidebar details[data-sidebar-section-key]');
                        sections.forEach(function (details) {
                            var key = details.getAttribute('data-sidebar-section-key');
                            if (key && Object.prototype.hasOwnProperty.call(store, key)) {
                                details.open = store[key] === 1 || store[key] === '1' || store[key] === true;
                            }
                            details.addEventListener('toggle', function () {
                                store[key] = details.open ? 1 : 0;
                                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(store)); } catch (e) { /* ignore */ }
                            });
                        });
                    })();

                    // Persistenz pro Untergruppe (<details data-sidebar-subgroup-key="…">),
                    // analog zu den Sektionen, eigener Storage-Schlüssel.
                    (function () {
                        var STORAGE_KEY = 'workDiarySidebarSubgroups';
                        var store = {};
                        try {
                            var raw = localStorage.getItem(STORAGE_KEY);
                            if (raw) { store = JSON.parse(raw) || {}; }
                        } catch (e) { store = {}; }
                        var groups = document.querySelectorAll('#app-sidebar details[data-sidebar-subgroup-key]');
                        groups.forEach(function (details) {
                            var key = details.getAttribute('data-sidebar-subgroup-key');
                            if (key && Object.prototype.hasOwnProperty.call(store, key)) {
                                details.open = store[key] === 1 || store[key] === '1' || store[key] === true;
                            }
                            details.addEventListener('toggle', function () {
                                store[key] = details.open ? 1 : 0;
                                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(store)); } catch (e) { /* ignore */ }
                            });
                        });
                    })();

                    function isDesktop() {
                        return window.matchMedia('(min-width: 1024px)').matches;
                    }

                    function openSidebar() {
                        if (!sidebar) return;
                        sidebar.classList.remove('-translate-x-full');
                        sidebar.classList.add('translate-x-0');
                        if (sidebarBackdrop) sidebarBackdrop.classList.remove('hidden');
                        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
                    }

                    function closeSidebar() {
                        if (!sidebar || isDesktop()) return;
                        sidebar.classList.add('-translate-x-full');
                        sidebar.classList.remove('translate-x-0');
                        if (sidebarBackdrop) sidebarBackdrop.classList.add('hidden');
                        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                    }

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', function () {
                            var open = sidebarToggle.getAttribute('aria-expanded') === 'true';
                            if (open) closeSidebar(); else openSidebar();
                        });
                    }
                    if (sidebarBackdrop) {
                        sidebarBackdrop.addEventListener('click', closeSidebar);
                    }
                    if (sidebar) {
                        // Auf Mobile nach Klick auf Nav-Link schließen
                        sidebar.addEventListener('click', function (event) {
                            var link = event.target.closest('a');
                            if (link && !isDesktop()) closeSidebar();
                        });
                    }
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') closeSidebar();
                    });
                    window.addEventListener('resize', function () {
                        // Beim Übergang zu Desktop Backdrop ausblenden, Sidebar via lg:translate-x-0 wieder sichtbar
                        if (isDesktop() && sidebarBackdrop) {
                            sidebarBackdrop.classList.add('hidden');
                            if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                        }
                    });
})();
