// Password show/hide toggle — works on any page with .password-toggle buttons
(function () {
  document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? 'Hide' : 'Show';
    });
  });
})();

// RoomPlate — front-end interactions
// Currently powers the time-slot picker on room.php.

(function () {
  const slotGrid = document.getElementById('slot-grid');
  if (!slotGrid || typeof window.ROOM_ID === 'undefined') return; // not on room.php

  const datePicker = document.getElementById('date-picker');
  const inputDate = document.getElementById('input-date');
  const inputStart = document.getElementById('input-start');
  const inputEnd = document.getElementById('input-end');
  const summary = document.getElementById('selection-summary');
  const submitBtn = document.getElementById('submit-booking');

  const DAY_START = 8;  // 08:00
  const DAY_END = 20;   // 20:00

  let bookedRanges = [];
  let selectedStartIdx = null;
  let selectedEndIdx = null;

  function pad(n) { return n.toString().padStart(2, '0'); }
  function slotLabel(hour) { return pad(hour) + ':00'; }
  function slotTime(hour) { return pad(hour) + ':00:00'; }

  function isSlotBooked(hour) {
    const start = slotTime(hour);
    const end = slotTime(hour + 1);
    return bookedRanges.some(r => start < r.end_time && end > r.start_time);
  }

  function buildGrid() {
    slotGrid.innerHTML = '';
    selectedStartIdx = null;
    selectedEndIdx = null;
    updateSelectionUI();

    for (let h = DAY_START; h < DAY_END; h++) {
      const div = document.createElement('div');
      div.className = 'slot';
      div.textContent = slotLabel(h);
      div.dataset.hour = h;
      if (isSlotBooked(h)) {
        div.classList.add('taken');
      } else {
        div.addEventListener('click', () => onSlotClick(h));
      }
      slotGrid.appendChild(div);
    }
  }

  function onSlotClick(hour) {
    if (selectedStartIdx === null || selectedEndIdx !== null) {
      // start a new selection
      selectedStartIdx = hour;
      selectedEndIdx = null;
    } else {
      // choosing the end — must be after start and range must be free
      if (hour <= selectedStartIdx) {
        selectedStartIdx = hour;
      } else {
        // check every slot in between is free
        let ok = true;
        for (let h = selectedStartIdx; h <= hour; h++) {
          if (isSlotBooked(h)) { ok = false; break; }
        }
        if (ok) {
          selectedEndIdx = hour;
        } else {
          selectedStartIdx = hour;
          selectedEndIdx = null;
        }
      }
    }
    updateSelectionUI();
  }

  function updateSelectionUI() {
    document.querySelectorAll('.slot').forEach(el => {
      const h = parseInt(el.dataset.hour, 10);
      el.classList.remove('selected');
      if (selectedStartIdx !== null) {
        const endBound = selectedEndIdx !== null ? selectedEndIdx : selectedStartIdx;
        if (h >= selectedStartIdx && h <= endBound) {
          el.classList.add('selected');
        }
      }
    });

    if (selectedStartIdx !== null) {
      const endHour = (selectedEndIdx !== null ? selectedEndIdx : selectedStartIdx) + 1;
      const startLabel = slotLabel(selectedStartIdx);
      const endLabel = slotLabel(endHour);
      summary.textContent = `Selected: ${startLabel} – ${endLabel}` + (selectedEndIdx === null ? ' (click another slot to extend, or submit for 1 hour)' : '');
      inputDate.value = datePicker.value;
      inputStart.value = slotTime(selectedStartIdx);
      inputEnd.value = slotTime(endHour);
      submitBtn.disabled = false;
    } else {
      summary.textContent = 'No time selected yet.';
      inputStart.value = '';
      inputEnd.value = '';
      submitBtn.disabled = true;
    }
  }

  function loadAvailability() {
    const date = datePicker.value;
    fetch(`/api/check_availability.php?room_id=${window.ROOM_ID}&date=${date}`)
      .then(res => res.json())
      .then(data => {
        bookedRanges = data.booked || [];
        buildGrid();
      })
      .catch(() => {
        slotGrid.innerHTML = '<p class="text-muted">Could not load availability. Please try again.</p>';
      });
  }

  datePicker.addEventListener('change', loadAvailability);
  loadAvailability();
})();
