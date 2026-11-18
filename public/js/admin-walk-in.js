(() => {
    const form = document.getElementById('walkin-form');
    const pricingDataNode = document.getElementById('walkin-pricing-data');
    if (!form || !pricingDataNode) return;

    const pricingData = JSON.parse(pricingDataNode.textContent || '{}');
    const roomInput = document.getElementById('walkin-room');
    const roomTrigger = document.getElementById('walkin-room-trigger');
    const roomTriggerCopy = document.getElementById('walkin-room-trigger-copy');
    const roomPanel = document.getElementById('walkin-room-options');
    const roomButtons = Array.from(document.querySelectorAll('[data-room-option]'));
    const roomSummary = document.getElementById('walkin-room-summary');
    const packageSection = document.getElementById('walkin-package-section');
    const guestSection = document.getElementById('walkin-guest-section');
    const arrivalSection = document.getElementById('walkin-arrival-section');
    const finishSection = document.getElementById('walkin-finish-section');
    const packageCopy = document.getElementById('walkin-package-copy');
    const packageGrid = document.getElementById('walkin-package-grid');
    const hoursInput = document.getElementById('walkin-hours');
    const coolingInput = document.getElementById('walkin-cooling');
    const guestsInput = document.getElementById('walkin-guests');
    const guestOptions = document.getElementById('walkin-guest-options');
    const capacityCopy = document.getElementById('walkin-capacity-copy');
    const checkInDate = document.getElementById('walkin-check-in');
    const checkInTime = document.getElementById('walkin-check-in-time');
    const dateTrigger = document.getElementById('walkin-date-trigger');
    const dateCopy = document.getElementById('walkin-date-copy');
    const calendar = document.getElementById('walkin-calendar');
    const calendarMonth = document.getElementById('walkin-calendar-month');
    const calendarDays = document.getElementById('walkin-calendar-days');
    const calendarPrevious = document.getElementById('walkin-calendar-prev');
    const calendarNext = document.getElementById('walkin-calendar-next');
    const timeTrigger = document.getElementById('walkin-time-trigger');
    const timeCopy = document.getElementById('walkin-time-copy');
    const timeOptions = document.getElementById('walkin-time-options');
    const checkoutOutput = document.getElementById('walkin-checkout');
    const totalOutput = document.getElementById('walkin-total');
    const totalCopy = document.getElementById('walkin-total-copy');
    const selectionError = document.getElementById('walkin-selection-error');

    const initialRoomId = roomInput.value;
    const initialHours = hoursInput.value;
    const initialCooling = coolingInput.value;
    const initialGuests = guestsInput.value;
    const today = parseDate(pricingData.today);
    const [nowDatePart, nowTimePart = '00:00'] = String(pricingData.now || '').split(' ');
    const propertyNow = parseDate(nowDatePart || pricingData.today);
    const [propertyNowHour, propertyNowMinute = '00'] = nowTimePart.split(':');
    propertyNow.setHours(Number(propertyNowHour) || 0, Number(propertyNowMinute) || 0, 0, 0);
    let calendarCursor = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedRoom = null;
    let selectedPackage = null;
    let roomCapacity = 0;

    const money = amount => `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(amount)}`;

    function parseDate(value) {
        const [year, month, day] = String(value || '').split('-').map(Number);
        return new Date(year || todayYearFallback(), (month || 1) - 1, day || 1);
    }

    function todayYearFallback() {
        return new Date().getFullYear();
    }

    function toIsoDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function formatLongDate(value) {
        if (!value) return 'Choose a date';
        const date = parseDate(value);
        return date.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function closePanels(except = null) {
        [[roomPanel, roomTrigger], [calendar, dateTrigger], [timeOptions, timeTrigger]].forEach(([panel, trigger]) => {
            if (panel !== except) {
                panel.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function updateSummary() {
        if (!selectedPackage || !guestsInput.value || !checkInDate.value || !checkInTime.value) {
            finishSection.hidden = true;
            return;
        }

        const guestCount = Number.parseInt(guestsInput.value, 10);
        const extraGuests = Math.max(0, guestCount - 2);
        const extraGuestRate = Number(pricingData.extraGuestRate || 0);
        const total = selectedPackage.rate + extraGuests * extraGuestRate;
        totalOutput.textContent = money(total);
        totalCopy.textContent = extraGuests
            ? `${money(selectedPackage.rate)} package + ${extraGuests} extra guest${extraGuests === 1 ? '' : 's'} at ${money(extraGuestRate)} each.`
            : 'The selected package includes up to 2 guests.';

        const [year, month, day] = checkInDate.value.split('-').map(Number);
        const [hour, minute] = checkInTime.value.split(':').map(Number);
        const checkIn = new Date(year, month - 1, day, hour, minute, 0, 0);
        const checkOut = new Date(checkIn);
        if (Number(selectedPackage.hours) === 720) {
            const originalDay = checkOut.getDate();
            checkOut.setDate(1);
            checkOut.setMonth(checkOut.getMonth() + 1);
            const lastDay = new Date(checkOut.getFullYear(), checkOut.getMonth() + 1, 0).getDate();
            checkOut.setDate(Math.min(originalDay, lastDay));
        } else {
            checkOut.setTime(checkOut.getTime() + Number(selectedPackage.hours) * 60 * 60 * 1000);
        }

        const date = checkOut.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        const time = checkOut.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
        checkoutOutput.textContent = `${date} · ${time}`;
        finishSection.hidden = false;
    }

    function selectGuestCount(count, button) {
        guestsInput.value = String(count);
        guestOptions.querySelectorAll('[data-guest-count]').forEach(option => {
            const isSelected = option === button;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        selectionError.hidden = true;
        updateSummary();
    }

    function renderGuestOptions(restoreCount = '') {
        guestOptions.replaceChildren();
        guestsInput.value = '';
        for (let count = 1; count <= roomCapacity; count += 1) {
            const button = document.createElement('button');
            button.className = 'walkin-guest-option';
            button.type = 'button';
            button.dataset.guestCount = String(count);
            button.textContent = `${count} ${count === 1 ? 'guest' : 'guests'}`;
            button.setAttribute('aria-pressed', 'false');
            button.addEventListener('click', () => selectGuestCount(count, button));
            guestOptions.append(button);
            if (String(count) === String(restoreCount)) selectGuestCount(count, button);
        }
        capacityCopy.textContent = `Select up to ${roomCapacity} guests. Extra guests are ₱${Number(pricingData.extraGuestRate || 0)} each after the first 2.`;
    }

    function selectPackage(packageOption, button) {
        selectedPackage = packageOption;
        hoursInput.value = String(packageOption.hours);
        coolingInput.value = packageOption.cooling;
        packageGrid.querySelectorAll('[data-package-option]').forEach(option => {
            const isSelected = option === button;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        selectionError.hidden = true;
        guestSection.hidden = false;
        arrivalSection.hidden = false;
        updateSummary();
    }

    function renderPackages(roomId, restoreOldChoice = false) {
        const packages = pricingData.rooms?.[roomId] || [];
        packageCopy.textContent = `Available rates for ${selectedRoom?.name || 'this room'}.`;
        packageSection.hidden = !roomId;
        packageGrid.replaceChildren();
        selectedPackage = null;
        hoursInput.value = '';
        coolingInput.value = '';
        guestSection.hidden = true;
        arrivalSection.hidden = true;
        finishSection.hidden = true;
        renderGuestOptions(restoreOldChoice ? initialGuests : '');

        packages.forEach(packageOption => {
            const button = document.createElement('button');
            button.className = 'walkin-package-option';
            button.type = 'button';
            button.dataset.packageOption = '';
            button.setAttribute('aria-pressed', 'false');

            const copy = document.createElement('span');
            copy.className = 'walkin-package-copy';
            const length = document.createElement('strong');
            length.textContent = packageOption.duration;
            const cooling = document.createElement('small');
            cooling.textContent = packageOption.coolingLabel;
            copy.append(length, cooling);

            const rate = document.createElement('b');
            rate.textContent = money(packageOption.rate);
            button.append(copy, rate);
            button.addEventListener('click', () => selectPackage(packageOption, button));
            packageGrid.append(button);

            if (restoreOldChoice && String(packageOption.hours) === initialHours && packageOption.cooling === initialCooling) {
                selectPackage(packageOption, button);
            }
        });

        updateSummary();
    }

    function selectRoom(button, restore = false) {
        const roomId = button.dataset.roomId;
        if (!roomId) return;
        selectedRoom = {
            id: roomId,
            name: button.dataset.roomName,
            type: button.dataset.roomType,
            capacity: Number(button.dataset.roomGuests || 2),
        };
        roomCapacity = selectedRoom.capacity;
        roomInput.value = roomId;
        roomTriggerCopy.textContent = `${selectedRoom.name} · ${selectedRoom.type} · up to ${roomCapacity} guests`;
        roomSummary.textContent = `${selectedRoom.type} · up to ${roomCapacity} guests`;
        roomSummary.hidden = false;
        roomButtons.forEach(option => {
            const isSelected = option === button;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        roomPanel.hidden = true;
        roomTrigger.setAttribute('aria-expanded', 'false');
        selectionError.hidden = true;
        renderPackages(roomId, restore);
    }

    function renderCalendar() {
        const year = calendarCursor.getFullYear();
        const month = calendarCursor.getMonth();
        calendarMonth.textContent = calendarCursor.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        calendarPrevious.disabled = year === today.getFullYear() && month === today.getMonth();
        calendarDays.replaceChildren();

        const firstWeekday = new Date(year, month, 1).getDay();
        const lastDay = new Date(year, month + 1, 0).getDate();
        for (let blank = 0; blank < firstWeekday; blank += 1) {
            const spacer = document.createElement('span');
            spacer.className = 'walkin-calendar-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            calendarDays.append(spacer);
        }

        for (let day = 1; day <= lastDay; day += 1) {
            const date = new Date(year, month, day);
            const value = toIsoDate(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'walkin-calendar-day';
            button.textContent = String(day);
            button.disabled = value < pricingData.today || (value === pricingData.today && date.getTime() < new Date(today).getTime());
            if (value === checkInDate.value) button.classList.add('is-selected');
            if (value === pricingData.today) button.classList.add('is-today');
            button.setAttribute('aria-label', date.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
            button.addEventListener('click', () => selectDate(value));
            calendarDays.append(button);
        }
    }

    function renderTimeOptions() {
        timeOptions.replaceChildren();
        for (let hour = 0; hour < 24; hour += 1) {
            const value = `${String(hour).padStart(2, '0')}:00`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'walkin-time-option';
            button.textContent = new Date(2000, 0, 1, hour).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
            button.disabled = checkInDate.value === pricingData.today && hour <= propertyNow.getHours();
            if (value === checkInTime.value) button.classList.add('is-selected');
            button.addEventListener('click', () => selectTime(value));
            timeOptions.append(button);
        }
    }

    function selectDate(value) {
        checkInDate.value = value;
        dateCopy.textContent = formatLongDate(value);
        calendar.hidden = true;
        dateTrigger.setAttribute('aria-expanded', 'false');
        timeTrigger.disabled = false;
        if (value === pricingData.today && checkInTime.value && Number(checkInTime.value.slice(0, 2)) <= propertyNow.getHours()) {
            checkInTime.value = '';
            timeCopy.textContent = 'Choose a time';
        }
        renderTimeOptions();
        updateSummary();
    }

    function selectTime(value) {
        checkInTime.value = value;
        const [hour] = value.split(':').map(Number);
        timeCopy.textContent = new Date(2000, 0, 1, hour).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
        timeOptions.hidden = true;
        timeTrigger.setAttribute('aria-expanded', 'false');
        selectionError.hidden = true;
        updateSummary();
    }

    roomTrigger.addEventListener('click', () => {
        const opening = roomPanel.hidden;
        closePanels(opening ? roomPanel : null);
        roomPanel.hidden = !opening;
        roomTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) roomPanel.querySelector('[data-room-option]')?.focus();
    });
    roomPanel.addEventListener('click', event => {
        const roomButton = event.target.closest('[data-room-option]');
        if (roomButton) selectRoom(roomButton);
        if (event.target.closest('[data-close-room-options]')) {
            roomPanel.hidden = true;
            roomTrigger.setAttribute('aria-expanded', 'false');
            roomTrigger.focus();
        }
    });

    dateTrigger.addEventListener('click', () => {
        const opening = calendar.hidden;
        closePanels(opening ? calendar : null);
        if (checkInDate.value) {
            const selectedDate = parseDate(checkInDate.value);
            calendarCursor = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
        }
        renderCalendar();
        calendar.hidden = !opening;
        dateTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    calendarPrevious.addEventListener('click', () => {
        calendarCursor.setMonth(calendarCursor.getMonth() - 1);
        renderCalendar();
    });
    calendarNext.addEventListener('click', () => {
        calendarCursor.setMonth(calendarCursor.getMonth() + 1);
        renderCalendar();
    });
    timeTrigger.addEventListener('click', () => {
        if (!checkInDate.value) return;
        const opening = timeOptions.hidden;
        closePanels(opening ? timeOptions : null);
        renderTimeOptions();
        timeOptions.hidden = !opening;
        timeTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.walkin-room-picker')) {
            roomPanel.hidden = true;
            roomTrigger.setAttribute('aria-expanded', 'false');
        }
        if (!event.target.closest('.walkin-date-time-field')) {
            calendar.hidden = true;
            timeOptions.hidden = true;
            dateTrigger.setAttribute('aria-expanded', 'false');
            timeTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        closePanels();
        roomTrigger.focus();
    });

    form.addEventListener('submit', event => {
        let message = '';
        let target = null;
        if (!roomInput.value) {
            message = 'Choose a room first.';
            target = roomTrigger;
        } else if (!selectedPackage) {
            message = 'Choose a rate package.';
            target = packageGrid.querySelector('button');
        } else if (!guestsInput.value) {
            message = 'Select the number of guests.';
            target = guestOptions.querySelector('button');
        } else if (!checkInDate.value) {
            message = 'Choose a check-in date.';
            target = dateTrigger;
        } else if (!checkInTime.value) {
            message = 'Choose a check-in time.';
            target = timeTrigger;
        }

        if (message) {
            event.preventDefault();
            selectionError.textContent = message;
            selectionError.hidden = false;
            target?.focus();
            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    dateCopy.textContent = formatLongDate(checkInDate.value);
    timeTrigger.disabled = !checkInDate.value;
    if (checkInTime.value) {
        const [hour] = checkInTime.value.split(':').map(Number);
        timeCopy.textContent = new Date(2000, 0, 1, hour).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    }

    const oldRoom = roomButtons.find(button => button.dataset.roomId === initialRoomId);
    if (oldRoom) selectRoom(oldRoom, true);
})();
