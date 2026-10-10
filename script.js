/* Small interface helpers only. PHP checks permissions and saves to MySQL. */
let confirmationForm = null;
document.addEventListener('click', function (event) {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.dataset.open && new URL(location.href).searchParams.has('edit')) {
        location.href = location.pathname + '?new=1';
        return;
    }
    if (button.dataset.open) {
        document.getElementById(button.dataset.open).showModal();
    }
    if (button.dataset.close) {
        document.getElementById(button.dataset.close).close();
    }
});
document.querySelectorAll('dialog[data-auto-open]').forEach(function (dialog) {
    dialog.showModal();
});
document.querySelectorAll('[data-filter-clear]').forEach(function (button) {
    button.addEventListener('click', function () {
        const control = document.getElementById(button.dataset.filterClear);
        if (!control) return;
        if (control.tagName === 'SELECT') {
            control.selectedIndex = 0;
        } else {
            control.value = '';
        }
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
        const form = control.closest('#report-filters');
        if (form) form.requestSubmit();
    });
});
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = button.parentElement.querySelector('input');
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(showing));
        button.querySelector('.icon-eye').hidden = showing;
        button.querySelector('.icon-eye-off').hidden = !showing;
    });
});
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        confirmationForm = form;
        document.getElementById('confirm-text').textContent = form.dataset.confirm;
        document.getElementById('confirm-dialog').showModal();
    });
});
const confirmButton = document.getElementById('confirm-submit');
if (confirmButton) {
    confirmButton.addEventListener('click', function () {
        if (confirmationForm) confirmationForm.submit();
    });
}
document.querySelectorAll('input[data-search]').forEach(function (input) {
    const table = document.getElementById(input.dataset.search);
    const body = table.querySelector('tbody');
    const allRows = Array.from(body.querySelectorAll('tr'));
    const rows = allRows.filter(function (row) {
        return !row.hasAttribute('data-empty-state');
    });
    const placeholders = allRows.filter(function (row) {
        return row.hasAttribute('data-empty-state');
    });
    const empty = document.createElement('tr');
    empty.className = 'filter-empty error';
    empty.hidden = true;
    empty.innerHTML = '<td colspan="' + table.rows[0].cells.length + '">No matching results found.</td>';
    body.appendChild(empty);
    function filterTable() {
        const term = input.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(function (row) {
            row.hidden = !row.textContent.toLowerCase().includes(term);
            if (!row.hidden) shown++;
        });
        placeholders.forEach(function (row) {
            row.hidden = Boolean(term) || shown > 0;
        });
        empty.hidden = shown > 0 || (!term && placeholders.length > 0);
        if (!rows.length && !placeholders.length) {
            empty.cells[0].textContent = 'No records found.';
            empty.hidden = false;
        }
    }
    input.addEventListener('input', filterTable);
    filterTable();
});
const bookingFilters = document.getElementById('booking-filters');
if (bookingFilters) {
    const search = document.getElementById('search');
    const date = document.getElementById('filter-date');
    const court = document.getElementById('filter-court');
    const status = document.getElementById('filter-status');
    const rows = Array.from(document.querySelectorAll('#booking-records tbody tr[data-date]'));
    const empty = document.querySelector('#booking-records .filter-empty');
    function filterBookings() {
        const term = search.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(function (row) {
            const matches =
                (!term || row.textContent.toLowerCase().includes(term)) &&
                (!date.value || row.dataset.date === date.value) &&
                (court.value === '0' || row.dataset.court === court.value) &&
                (!status.value || row.dataset.status === status.value);
            row.hidden = !matches;
            if (matches) shown++;
        });
        empty.hidden = shown > 0;
    }
    [search, date, court, status].forEach(function (control) {
        control.addEventListener('input', filterBookings);
        control.addEventListener('change', filterBookings);
    });
    bookingFilters.addEventListener('submit', function (event) {
        event.preventDefault();
        filterBookings();
    });
    filterBookings();
}
const reportButtons = document.querySelectorAll('[data-report-view]');
if (reportButtons.length) {
    const reportFilters = document.getElementById('report-filter-wrap');
    reportButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            reportButtons.forEach(function (item) {
                const selected = item === button;
                item.setAttribute('aria-pressed', String(selected));
                document.getElementById(item.dataset.reportView).hidden = !selected;
            });
            reportFilters.hidden = button.dataset.reportView !== 'periodic-report';
        });
    });
}
const clientMode = document.getElementById('client_mode');
if (clientMode) {
    function switchClient() {
        const isNew = clientMode.value === 'new';
        document.getElementById('existing-client-fields').hidden = isNew;
        document.getElementById('new-client-fields').hidden = !isNew;
        document.getElementById('client_id').disabled = isNew;
        ['full_name', 'phone', 'email'].forEach(function (id) {
            const input = document.getElementById(id);
            input.disabled = !isNew;
            input.required = isNew;
        });
    }
    clientMode.addEventListener('change', switchClient);
    switchClient();
    const clientSelect = document.getElementById('client_id');
    const choices = Array.from(clientSelect.options).filter(function (option) {
        return option.value !== '';
    });
    const clientSearch = document.getElementById('client-search');
    const clientResults = document.getElementById('client-search-results');
    clientSearch.addEventListener('input', function () {
        const term = clientSearch.value.trim().toLowerCase();
        const matches = choices.filter(function (option) {
            return option.textContent.toLowerCase().includes(term);
        });
        const placeholder = clientSelect.options[0].cloneNode(true);
        clientSelect.innerHTML = '';
        clientSelect.appendChild(placeholder);
        matches.forEach(function (option) {
            clientSelect.appendChild(option.cloneNode(true));
        });
        clientSelect.value = '';

        clientResults.replaceChildren();
        clientResults.hidden = !term;
        if (!term) return;
        if (!matches.length) {
            const item = document.createElement('li');
            item.className = 'client-search-empty';
            item.textContent = 'No matching clients found.';
            clientResults.appendChild(item);
            return;
        }
        matches.forEach(function (option) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'client-search-result';
            button.textContent = option.textContent;
            button.addEventListener('click', function () {
                clientSelect.value = option.value;
                clientSearch.value = option.textContent;
                clientResults.hidden = true;
            });
            item.appendChild(button);
            clientResults.appendChild(item);
        });
    });
}
const maintenanceSelect = document.getElementById('maintenance');
if (maintenanceSelect) {
    const period = document.getElementById('maintenance-period');
    const periodFields = period.querySelectorAll('input');
    function updateMaintenanceFields() {
        const enabled = maintenanceSelect.value === '1';
        period.hidden = !enabled;
        periodFields.forEach(function (input) {
            input.required = enabled;
        });
    }
    maintenanceSelect.addEventListener('change', updateMaintenanceFields);
    updateMaintenanceFields();
}
const amount = document.getElementById('amount_paid');
if (amount) {
    amount.min = '0';
    amount.step = '0.01';
}
const printButton = document.getElementById('print-report');
if (printButton) {
    printButton.addEventListener('click', function () {
        document.querySelectorAll('details').forEach(function (details) {
            details.open = true;
        });
        window.print();
    });
}

/* Show the end time and price before the user saves a booking. */
const bookingForm = document.getElementById('booking-form');
if (bookingForm) {
    const validationError = document.getElementById('booking-validation-error');
    bookingForm.addEventListener(
        'invalid',
        function () {
            validationError.textContent = 'Please correct the highlighted fields and complete all required fields.';
            validationError.hidden = false;
        },
        true,
    );
    bookingForm.addEventListener('input', function () {
        validationError.hidden = true;
    });
    bookingForm.addEventListener('change', function () {
        validationError.hidden = true;
    });
    const start = document.getElementById('hour');
    const duration = document.getElementById('duration');
    const court = document.getElementById('court_id');
    const summary = document.getElementById('booking-summary');
    const received = document.getElementById('amount_paid');
    const verified = bookingForm.querySelector('input[name="verified"]');
    function hourText(hour) {
        if (hour === 24) return '12 AM (next day)';
        return (hour % 12 || 12) + (hour >= 12 ? ' PM' : ' AM');
    }
    function updateSummary(changed) {
        const startHour = Number(start.value);
        // Do not offer a duration that would end after closing time.
        Array.from(duration.options).forEach(function (option) {
            option.disabled = startHour + Number(option.value) > 24;
        });
        if (startHour + Number(duration.value) > 24) {
            duration.value = String(24 - startHour);
        }
        const hours = Number(duration.value);
        const selectedCourt = court.options[court.selectedIndex];
        const rate = selectedCourt ? Number(selectedCourt.dataset.rate) : 300;
        const total = hours * rate;
        summary.textContent =
            hourText(startHour) +
            ' – ' +
            hourText(startHour + hours) +
            ' · ' +
            hours +
            (hours === 1 ? ' hour' : ' hours') +
            ' · Total: ₱' +
            total.toFixed(2);
        if (changed) {
            received.value = total.toFixed(2);
            verified.checked = false;
        }
    }
    [start, duration, court].forEach(function (input) {
        input.addEventListener('change', function () {
            updateSummary(true);
        });
    });
    updateSummary(false);
}
