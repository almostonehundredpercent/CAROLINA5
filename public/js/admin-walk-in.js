(() => {
    const form = document.getElementById('walkin-form');
    const pricingDataNode = document.getElementById('walkin-pricing-data');
    if (!form || !pricingDataNode) return;

    const pricingData = JSON.parse(pricingDataNode.textContent || '{}');
    const roomSelect = document.getElementById('walkin-room');
    const roomSummary = document.getElementById('walkin-room-summary');
    const packageSection = document.getElementById('walkin-package-section');
    const packageCopy = document.getElementById('walkin-package-copy');
    const packageGrid = document.getElementById('walkin-package-grid');
    const packageError = document.getElementById('walkin-package-error');
    const hoursInput = document.getElementById('walkin-hours');
    const coolingInput = document.getElementById('walkin-cooling');
    const checkInDate = document.getElementById('walkin-check-in');
    const checkInTime = document.getElementById('walkin-check-in-time');
    const checkoutOutput = document.getElementById('walkin-checkout');
    const totalOutput = document.getElementById('walkin-total');
    const totalCopy = document.getElementById('walkin-total-copy');
    const guestsInput = document.getElementById('walkin-guests');
    const capacityCopy = document.getElementById('walkin-capacity-copy');

    let selectedPackage = null;
    const oldRoomId = roomSelect.value;
    const oldHours = hoursInput.value;
    const oldCooling = coolingInput.value;

    const money = amount => `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(amount)}`;

    function updateSummary() {
        if (!selectedPackage) {
            checkoutOutput.textContent = 'Choose a rate and arrival time';
            totalOutput.textContent = '—';
            totalCopy.textContent = 'The selected package includes up to 2 guests.';
            return;
        }

        const guestCount = Math.max(1, Number.parseInt(guestsInput.value, 10) || 1);
        const extraGuests = Math.max(0, guestCount - 2);
        const total = selectedPackage.rate + extraGuests * Number(pricingData.extraGuestRate || 0);
        totalOutput.textContent = money(total);
        totalCopy.textContent = extraGuests
            ? `${money(selectedPackage.rate)} package + ${extraGuests} extra guest${extraGuests === 1 ? '' : 's'} at ${money(Number(pricingData.extraGuestRate || 0))} each.`
            : 'The selected package includes up to 2 guests.';

        if (!checkInDate.value || !checkInTime.value) {
            checkoutOutput.textContent = 'Choose an arrival date and time';
            return;
        }

        const [year, month, day] = checkInDate.value.split('-').map(Number);
        const [hour, minute] = checkInTime.value.split(':').map(Number);
        const checkIn = new Date(year, month - 1, day, hour, minute, 0, 0);
        if (Number.isNaN(checkIn.getTime())) {
            checkoutOutput.textContent = 'Choose an arrival date and time';
            return;
        }

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
        packageError.hidden = true;
        updateSummary();
    }

    function renderPackages(roomId, restoreOldChoice = false) {
        const room = roomSelect.selectedOptions[0];
        const packages = pricingData.rooms?.[roomId] || [];
        const roomName = room?.dataset.roomName || 'this room';
        const roomType = room?.dataset.roomType || 'Room';
        const roomCapacity = Number(room?.dataset.roomGuests || 2);

        roomSummary.hidden = !roomId;
        roomSummary.textContent = roomId ? `${roomName} · ${roomType} · Up to ${roomCapacity} guests` : '';
        packageSection.hidden = !roomId;
        packageCopy.textContent = roomId ? `Available rates for ${roomName}.` : 'Select a room to see available rates.';
        capacityCopy.textContent = roomId ? `This room accommodates up to ${roomCapacity} guests.` : 'Choose a room to see its capacity.';
        guestsInput.max = String(roomCapacity);
        packageGrid.replaceChildren();
        selectedPackage = null;
        hoursInput.value = '';
        coolingInput.value = '';

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

            if (restoreOldChoice && String(packageOption.hours) === oldHours && packageOption.cooling === oldCooling) {
                selectPackage(packageOption, button);
            }
        });

        updateSummary();
    }

    roomSelect.addEventListener('change', () => renderPackages(roomSelect.value));
    checkInDate.addEventListener('input', updateSummary);
    checkInTime.addEventListener('input', updateSummary);
    guestsInput.addEventListener('input', updateSummary);

    form.addEventListener('submit', event => {
        if (!roomSelect.value) return;
        if (!selectedPackage) {
            event.preventDefault();
            packageError.hidden = false;
            packageSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            packageGrid.querySelector('button')?.focus();
        }
    });

    renderPackages(roomSelect.value, roomSelect.value === oldRoomId && Boolean(oldHours && oldCooling));
})();
