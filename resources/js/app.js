const SUBMIT_METHODS = new Set([
    'verifyIdentity',
    'saveEmployeeDetails',
    'saveMedicalRecord',
    'saveBeneficiary',
    'continueFromBeneficiaries',
    'saveDocuments',
    'submitRegistration',
    'reportUploadClientError',
    '_uploadErrored',
]);

let scrollLockUntil = 0;

function fieldCandidates(fieldName) {
    if (!fieldName) {
        return [];
    }

    const name = String(fieldName).split('.')[0];
    const escaped = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(name) : name;

    return [
        `[data-reg-field="${escaped}"]`,
        `[wire\\:model="${escaped}"]`,
        `[wire\\:model.blur="${escaped}"]`,
        `[wire\\:model.live="${escaped}"]`,
        `[wire\\:model.change="${escaped}"]`,
        `#${escaped}`,
    ];
}

function scrollTarget(element) {
    if (!element) {
        return null;
    }

    return (
        element.closest(
            '.reg-upload, .reg-photo-picker, .reg-photo-dropzone, .reg-medical-block, .reg-consent, .reg-login-field, [data-reg-field]',
        ) || element
    );
}

function firstInvalidTarget(fieldName) {
    for (const selector of fieldCandidates(fieldName)) {
        const match = document.querySelector(selector);

        if (match) {
            return scrollTarget(match);
        }
    }

    const invalid = document.querySelector('.reg-input-invalid, .reg-photo-dropzone-invalid');

    if (invalid) {
        return scrollTarget(invalid);
    }

    const error = document.querySelector('.reg-field-error');

    if (error) {
        const group = error.closest(
            'div, label, section, article, .reg-upload, .reg-medical-block, .reg-consent',
        );
        const control = group?.querySelector(
            'input:not([type="hidden"]):not([type="file"]), select, textarea, button.reg-select',
        );

        return scrollTarget(control || error);
    }

    return (
        document.querySelector('.reg-validation-summary:not(.reg-validation-summary-compact)') ||
        document.querySelector('.reg-validation-summary')
    );
}

function dockClearance() {
    const dock = document.querySelector('.reg-actions-dock');

    if (!dock) {
        return 0;
    }

    return getComputedStyle(dock).position === 'fixed' ? dock.getBoundingClientRect().height + 16 : 0;
}

function focusControl(target) {
    const focusable = target.matches('input, select, textarea, button')
        ? target
        : target.querySelector(
              'input:not([type="hidden"]):not([type="file"]), select, textarea, button.reg-select',
          );

    if (!focusable || focusable.disabled) {
        return;
    }

    try {
        focusable.focus({ preventScroll: true });
    } catch {
        // Some custom controls cannot take focus.
    }
}

function eventField(event) {
    const detail = event?.detail;

    if (typeof detail === 'string') {
        return detail;
    }

    if (detail && typeof detail === 'object') {
        return detail.field ?? detail[0]?.field ?? null;
    }

    return event?.field ?? null;
}

export function regScrollToValidationError(fieldName) {
    const now = Date.now();

    if (now < scrollLockUntil) {
        return;
    }

    scrollLockUntil = now + 350;

    const target = firstInvalidTarget(fieldName);

    if (!target) {
        return;
    }

    const topPad = 20;
    const bottomPad = dockClearance();
    const rect = target.getBoundingClientRect();
    const visibleHeight = window.innerHeight - topPad - bottomPad;
    const centered = window.scrollY + rect.top - topPad - Math.max((visibleHeight - rect.height) / 2, 0);

    window.scrollTo({ top: Math.max(0, centered), behavior: 'smooth' });
    focusControl(target);
}

window.regScrollToValidationError = regScrollToValidationError;

function scheduleScroll(fieldName) {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => regScrollToValidationError(fieldName));
    });
}

function bootRegistrationValidation() {
    if (window.__regValidationScrollBooted) {
        return;
    }

    window.__regValidationScrollBooted = true;

    window.addEventListener('reg-scroll-to-error', (event) => {
        scheduleScroll(eventField(event));
    });

    document.addEventListener('click', (event) => {
        const jump = event.target.closest('[data-reg-jump]');

        if (!jump) {
            return;
        }

        event.preventDefault();
        scrollLockUntil = 0;
        regScrollToValidationError(jump.getAttribute('data-reg-jump'));
    });

    if (!window.Livewire) {
        return;
    }

    Livewire.on('reg-scroll-to-error', (event) => {
        scheduleScroll(eventField(event));
    });

    Livewire.hook('commit', ({ commit, succeed }) => {
        const calls = commit?.calls ?? [];
        const shouldScroll = calls.some((call) => SUBMIT_METHODS.has(call.method));

        if (!shouldScroll) {
            return;
        }

        succeed(() => {
            const root = document.querySelector('.reg-page') ?? document;
            const hasErrors = Boolean(
                root.querySelector('.reg-field-error, .reg-input-invalid, .reg-validation-summary, .reg-photo-dropzone-invalid'),
            );

            if (hasErrors) {
                scheduleScroll();
            }
        });
    });
}

document.addEventListener('livewire:init', bootRegistrationValidation);

if (window.Livewire) {
    bootRegistrationValidation();
}
