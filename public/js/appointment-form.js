/*
 * Appointment form (S2-T4). It shows only the resource pickers the chosen service needs, and lists the free start
 * times for the chosen service, resources and Jalali date. Plain JavaScript, self-hosted, no dependencies.
 *
 * The form still works without this script: every picker is shown and the start time can be typed (see
 * docs/DECISIONS.md, S2-T4). All text comes from data-msg-* attributes set from lang/fa, so no Persian is written here.
 */
(function () {
    'use strict';

    var form = document.getElementById('appointment-form');

    if (!form) {
        return;
    }

    var msg = form.dataset;
    var serviceSelect = document.getElementById('service_id');
    var dateInput = document.getElementById('from_jalali');
    var picker = document.getElementById('slot-picker');
    var startsAt = document.getElementById('starts_at');
    var selectedLabel = document.getElementById('slot-selected');
    var resourceIds = ['specialist_id', 'device_id', 'room_id'];
    var latest = 0;
    var debounce = null;

    function toAscii(text) {
        return text
            .replace(/[\u06F0-\u06F9]/g, function (c) { return String(c.charCodeAt(0) - 0x06F0); })
            .replace(/[\u0660-\u0669]/g, function (c) { return String(c.charCodeAt(0) - 0x0660); });
    }

    function messageParagraph(text) {
        var p = document.createElement('p');
        p.className = 'muted';
        p.textContent = text;

        return p;
    }

    function showMessage(text) {
        picker.textContent = '';
        picker.appendChild(messageParagraph(text));
        selectedLabel.textContent = '';
    }

    // Show the pickers a service needs, and clear the ones it does not, so a hidden value is never submitted.
    function applyServiceRules() {
        var option = serviceSelect.selectedOptions[0];
        var needs = {
            specialist_id: !!option && option.getAttribute('data-requires-specialist') === '1',
            device_id: !!option && option.getAttribute('data-requires-device') === '1'
        };

        Object.keys(needs).forEach(function (id) {
            var field = document.querySelector('[data-resource="' + id + '"]');
            var select = document.getElementById(id);

            field.style.display = needs[id] ? '' : 'none';
            select.disabled = !needs[id];

            if (!needs[id]) {
                select.value = '';
            }
        });
    }

    function choose(day, slot, button) {
        startsAt.value = slot.starts_at;

        picker.querySelectorAll('.slot-button').forEach(function (other) {
            other.setAttribute('aria-pressed', 'false');
        });
        button.setAttribute('aria-pressed', 'true');
        selectedLabel.textContent = msg.msgSelected.replace(':day', day.jalali).replace(':time', slot.label);
    }

    function render(days) {
        picker.textContent = '';

        days.forEach(function (day) {
            var group = document.createElement('div');
            var title = document.createElement('p');

            group.className = 'slot-day';
            title.className = 'slot-day__title';
            title.textContent = day.jalali;
            group.appendChild(title);

            if (day.closed) {
                group.appendChild(messageParagraph(day.closed === 'holiday' ? msg.msgClosedHoliday : msg.msgClosedWeekend));
            } else if (day.slots.length === 0) {
                group.appendChild(messageParagraph(msg.msgNone));
            } else {
                var list = document.createElement('div');

                list.className = 'slot-list';

                day.slots.forEach(function (slot) {
                    var button = document.createElement('button');

                    button.type = 'button';
                    button.className = 'btn btn--secondary btn--small slot-button';
                    button.textContent = slot.label;
                    button.setAttribute('aria-pressed', slot.starts_at === startsAt.value ? 'true' : 'false');
                    button.addEventListener('click', function () { choose(day, slot, button); });
                    list.appendChild(button);
                });

                group.appendChild(list);
            }

            picker.appendChild(group);
        });
    }

    function requestSlots() {
        var option = serviceSelect.selectedOptions[0];
        var date = toAscii(dateInput.value.trim());

        if (!option || !option.value || !/^\d{4}[\/-]\d{1,2}[\/-]\d{1,2}$/.test(date)) {
            showMessage(msg.msgPickFirst);
            return;
        }

        var params = new URLSearchParams({ service_id: option.value, from_jalali: date, days: '7' });
        var hasResource = false;

        resourceIds.forEach(function (id) {
            var select = document.getElementById(id);

            if (!select.disabled && select.value) {
                params.set(id, select.value);
                hasResource = true;
            }
        });

        if (!hasResource) {
            showMessage(msg.msgNoResource);
            return;
        }

        var token = ++latest;

        showMessage(msg.msgLoading);

        fetch(form.dataset.slotsUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('status ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                if (token === latest) {
                    render(data.days);
                }
            })
            .catch(function () {
                if (token === latest) {
                    showMessage(msg.msgError);
                }
            });
    }

    serviceSelect.addEventListener('change', function () {
        applyServiceRules();
        requestSlots();
    });

    resourceIds.forEach(function (id) {
        document.getElementById(id).addEventListener('change', requestSlots);
    });

    dateInput.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(requestSlots, 400);
    });

    applyServiceRules();
    requestSlots();
})();
