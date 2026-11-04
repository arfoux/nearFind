const profilePage = document.querySelector('[data-profile-page]');

if (profilePage) {
    initProfilePage(profilePage);
}

function initProfilePage(page) {
    const modals = [...document.querySelectorAll('[data-profile-modal]')];
    let modalReturnFocus = null;

    const activeModal = () => modals.find((modal) => !modal.classList.contains('hidden')) ?? null;

    function openModal(name, trigger = null) {
        const modal = modals.find((item) => item.dataset.profileModal === name);

        if (!modal) {
            return;
        }

        modals.forEach((item) => {
            item.classList.add('hidden');
            item.classList.remove('flex');
        });

        modalReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        requestAnimationFrame(() => {
            modal.querySelector('input:not([type="hidden"]), textarea, button[type="submit"], [href]')?.focus({ preventScroll: true });
        });
    }

    function closeModal({ restoreFocus = true } = {}) {
        const modal = activeModal();

        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        if (restoreFocus && modalReturnFocus instanceof HTMLElement) {
            modalReturnFocus.focus({ preventScroll: true });
        }

        modalReturnFocus = null;
    }

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open-modal]');

        if (opener) {
            event.preventDefault();
            openModal(opener.dataset.openModal, opener);

            return;
        }

        if (event.target.closest('[data-modal-close]')) {
            event.preventDefault();
            closeModal();

            return;
        }

        if (event.target.closest('[data-modal-backdrop]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        const modal = activeModal();

        if (!modal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
            .filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    initActivityTabs(page);
        initBioCounter(page);

    const initialModal = page.dataset.initialModal?.trim();

    if (initialModal) {
        requestAnimationFrame(() => openModal(initialModal));
    }
}

function initActivityTabs(page) {
    const tabs = [...page.querySelectorAll('[data-activity-tab]')];
    const panels = [...page.querySelectorAll('[data-activity-panel]')];

    if (tabs.length === 0) {
        return;
    }

    function activate(name) {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.activityTab === name;
            const count = tab.querySelector('span');

            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.classList.toggle('bg-brand-700', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('shadow-[0_10px_20px_-16px_rgba(0,92,85,0.9)]', isActive);
            tab.classList.toggle('bg-white', !isActive);
            tab.classList.toggle('text-muted', !isActive);
            tab.classList.toggle('ring-1', !isActive);
            tab.classList.toggle('ring-brand-900/10', !isActive);
            tab.classList.toggle('hover:text-brand-700', !isActive);

            if (count) {
                count.className = isActive
                    ? 'rounded-full bg-white/15 px-1.5 py-0.5 text-[9px]'
                    : 'rounded-full bg-canvas px-1.5 py-0.5 text-[9px]';
            }
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.activityPanel !== name);
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activate(tab.dataset.activityTab));
    });
}

function initBioCounter(page) {
    const input = page.querySelector('[data-bio-input]');
    const counter = page.querySelector('[data-bio-count]');

    if (!input || !counter) {
        return;
    }

    const update = () => {
        counter.textContent = String(input.value.length);
    };

    input.addEventListener('input', update);
    update();
}
