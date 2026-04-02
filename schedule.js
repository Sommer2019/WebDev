// ── Single source of truth for the schedule ──────────────────────────────
const SCHEDULE = [
  {
    day: 'Montag',
    events: [
      { name: 'AXA', start: '06:30', end: '14:36', cls: 'ev-axa' }
    ]
  },
  {
    day: 'Dienstag',
    events: [
      { name: 'AXA', start: '06:30', end: '14:36', cls: 'ev-axa' }
    ]
  },
  {
    day: 'Mittwoch',
    events: [
      { name: 'AXA',       start: '07:00', end: '11:00', cls: 'ev-axa'       },
      { name: 'Mainframe', start: '13:00', end: '15:20', cls: 'ev-mainframe'  },
      { name: 'CTF',       start: '15:20', end: '18:15', cls: 'ev-ctf'        }
    ]
  },
  {
    day: 'Donnerstag',
    events: [
      { name: 'Machine Learning', start: '08:30', end: '11:45', cls: 'ev-ml'     },
      { name: 'PTP',              start: '11:45', end: '14:35', cls: 'ev-ptp'    },
      { name: 'WebDev',           start: '14:35', end: '17:45', cls: 'ev-webdev' }
    ]
  },
  {
    day: 'Freitag',
    events: [
      { name: 'ERP Anwendungssysteme',  start: '07:45', end: '11:00', cls: 'ev-erp'    },
      { name: 'Communication Network',  start: '11:00', end: '14:35', cls: 'ev-comnet'  },
      { name: 'Technisches Englisch',   start: '14:35', end: '17:45', cls: 'ev-english' }
    ]
  }
];

// ── Time helpers (origin 06:00, scale 1.5 px/min) ────────────────────────
const ORIGIN_MIN = 6 * 60; // 06:00 in minutes since midnight
const SCALE      = 1.5;    // px per minute

// Convert 'HH:MM' string to total minutes since midnight
function toMin(t) {
  const [h, m] = t.split(':').map(Number);
  return h * 60 + m;
}
// Pixel offset from the top of the day column for a given start time
function eventTop(start)         { return (toMin(start) - ORIGIN_MIN) * SCALE; }
// Pixel height for an event spanning start→end
function eventHeight(start, end) { return (toMin(end)   - toMin(start)) * SCALE; }

// ── Build desktop view ───────────────────────────────────────────────────
function buildDesktop() {
  const section = document.querySelector('.desktop-schedule');

  // Day headers row
  const headers = document.createElement('div');
  headers.className = 'day-headers';
  headers.setAttribute('aria-hidden', 'true');
  headers.innerHTML = '<div></div>'; // spacer for time axis
  SCHEDULE.forEach(({ day }) => {
    const cell = document.createElement('div');
    cell.className = 'day-header-cell';
    cell.textContent = day;
    headers.appendChild(cell);
  });
  section.appendChild(headers);

  // Schedule body
  const body = document.createElement('div');
  body.className = 'schedule-body';

  // Time axis (06:00–19:00, label every hour)
  const axis = document.createElement('div');
  axis.className = 'time-axis';
  axis.setAttribute('aria-hidden', 'true');
  for (let h = 6; h <= 19; h++) {
    const label = document.createElement('span');
    label.className = 'time-label';
    label.style.top = ((h - 6) * 60 * SCALE) + 'px';
    label.textContent = String(h).padStart(2, '0') + ':00';
    axis.appendChild(label);
  }
  body.appendChild(axis);

  // Day tracks
  SCHEDULE.forEach(({ day, events }) => {
    const track = document.createElement('div');
    track.className = 'day-track';
    track.setAttribute('aria-label', day);

    events.forEach(({ name, start, end, cls }) => {
      const ev = document.createElement('div');
      ev.className = 'event ' + cls;
      ev.setAttribute('aria-label', `${name} ${start} bis ${end}`);
      ev.style.top    = eventTop(start) + 'px';
      ev.style.height = eventHeight(start, end) + 'px';
      ev.innerHTML =
        `<span class="event-name">${name}</span>` +
        `<span class="event-time">${start} \u2013 ${end}</span>`;
      track.appendChild(ev);
    });

    body.appendChild(track);
  });

  section.appendChild(body);
}

// ── Build mobile view ────────────────────────────────────────────────────
function buildMobile() {
  const section = document.querySelector('.mobile-schedule');

  SCHEDULE.forEach(({ day, events }) => {
    const card = document.createElement('div');
    card.className = 'day-card';

    const header = document.createElement('div');
    header.className = 'day-card-header';
    header.textContent = day;
    card.appendChild(header);

    const list = document.createElement('div');
    list.className = 'event-list';

    events.forEach(({ name, start, end, cls }) => {
      const item = document.createElement('div');
      item.className = 'event-item ' + cls;
      item.innerHTML =
        `<span class="time-badge">${start} \u2013 ${end}</span>` +
        `<span class="subject">${name}</span>`;
      list.appendChild(item);
    });

    card.appendChild(list);
    section.appendChild(card);
  });
}

buildDesktop();
buildMobile();
