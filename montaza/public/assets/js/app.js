// MONTAŽA — zajednički JS (bez build koraka)
(function () {
    'use strict';

    // CSRF token za fetch() pozive (dodaje se kao X-CSRF-Token zaglavlje)
    const meta = document.querySelector('meta[name="csrf-token"]');
    window.MZ = {
        csrf: meta ? meta.content : '',
        post(url, data) {
            const body = data instanceof FormData ? data : new URLSearchParams(data || {});
            return fetch(url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': this.csrf, 'Accept': 'application/json' },
            });
        },
    };

    // Spreči dupli submit forme (spor mobilni internet)
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.submitting) { e.preventDefault(); return; }
        form.dataset.submitting = '1';
        setTimeout(() => { delete form.dataset.submitting; }, 8000);
    });
})();
