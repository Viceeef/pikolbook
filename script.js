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
    input.addEventListener('input', function () {
        const term = input.value.toLowerCase();
        document.querySelectorAll('#' + input.dataset.search + ' tbody tr').forEach(function (row) {
            row.hidden = !row.textContent.toLowerCase().includes(term);
        });
    });
});
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
            input.required = isNew && id !== 'email';
        });
    }
    clientMode.addEventListener('change', switchClient);
    switchClient();
    const clientSelect = document.getElementById('client_id');
    const choices = Array.from(clientSelect.options);
    document.getElementById('client-search').addEventListener('input', function (event) {
        const term = event.target.value.toLowerCase();
        const selected = clientSelect.value;
        clientSelect.innerHTML = '';
        choices.forEach(function (option) {
            if (!option.value || option.textContent.toLowerCase().includes(term)) {
                clientSelect.appendChild(option.cloneNode(true));
            }
        });
        if (
            Array.from(clientSelect.options).some(function (option) {
                return option.value === selected;
            })
        ) {
            clientSelect.value = selected;
        }
    });
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
