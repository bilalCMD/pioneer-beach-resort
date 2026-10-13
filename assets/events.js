/* ============================================================
   Events & Activities — renders events.json into a list view and a
   month calendar. Staff edit the JSON through events-admin.php, so
   nothing here needs touching when the programme changes.
   ============================================================ */
(function () {
  const MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
  const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  let EVENTS = [];
  let calDate = new Date();
  calDate.setDate(1);

  const esc = s => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  /* "2026-10-31" -> a Date in local time, not UTC, so the day never slips. */
  function parseDate(str) {
    if (!str) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str.trim());
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3]);
  }

  function prettyDate(str) {
    const d = parseDate(str);
    if (!d) return '';
    return d.toLocaleDateString('en-US',
      { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  }

  function statusPill(ev) {
    if (ev.status === 'cancelled') return '<span class="ev-pill cancelled">Cancelled</span>';
    if (ev.status === 'rescheduled') return '<span class="ev-pill rescheduled">Rescheduled</span>';
    return '';
  }

  function card(ev) {
    const d = parseDate(ev.date);
    const badge = d
      ? '<div class="ev-date"><span class="ev-d">' + d.getDate() + '</span>' +
        '<span class="ev-m">' + MONTHS[d.getMonth()].slice(0, 3) + '</span></div>'
      : '<div class="ev-date repeat"><span class="ev-rep">' + esc(ev.recurs || 'Ongoing') + '</span></div>';

    const meta = [];
    if (d) meta.push(prettyDate(ev.date));
    else if (ev.recurs) meta.push(esc(ev.recurs));
    if (ev.endDate && parseDate(ev.endDate)) meta.push('through ' + prettyDate(ev.endDate));
    if (ev.time) meta.push(esc(ev.time));
    if (ev.location) meta.push(esc(ev.location));

    return '<article class="ev-card' + (ev.status === 'cancelled' ? ' is-off' : '') + '">' +
      badge +
      '<div class="ev-body">' +
        '<div class="ev-head"><h3>' + esc(ev.title) + '</h3>' + statusPill(ev) + '</div>' +
        (meta.length ? '<p class="ev-meta">' + meta.join(' &middot; ') + '</p>' : '') +
        (ev.statusNote ? '<p class="ev-note">' + esc(ev.statusNote) + '</p>' : '') +
        (ev.description ? '<p class="ev-desc">' + esc(ev.description) + '</p>' : '') +
      '</div>' +
      (ev.image ? '<div class="ev-img"><img src="' + esc(ev.image) + '" alt="' + esc(ev.title) + '" loading="lazy"></div>' : '') +
    '</article>';
  }

  function renderLists() {
    const today = new Date(); today.setHours(0, 0, 0, 0);

    const dated = EVENTS.filter(e => parseDate(e.date));
    const upcoming = dated.filter(e => {
      const end = parseDate(e.endDate) || parseDate(e.date);
      return end >= today;
    }).sort((a, b) => parseDate(a.date) - parseDate(b.date));

    const activities = EVENTS.filter(e => e.kind === 'activity' && !parseDate(e.date));
    const promos = upcoming.filter(e => e.kind === 'promotion');
    const events = upcoming.filter(e => e.kind !== 'promotion');

    const put = (id, items, empty) => {
      const host = document.getElementById(id);
      if (!host) return;
      host.innerHTML = items.length ? items.map(card).join('') :
        '<p class="ev-empty">' + empty + '</p>';
    };

    put('evUpcoming', events, 'Nothing on the calendar just now &mdash; check back soon.');
    put('evActivities', activities, 'Our regular activity programme is being updated.');
    put('evPromos', promos, 'No promotions running at the moment.');

    const pw = document.getElementById('evPromoWrap');
    if (pw) pw.hidden = promos.length === 0;
  }

  function renderCalendar() {
    const grid = document.getElementById('evCalGrid');
    const label = document.getElementById('evCalLabel');
    if (!grid || !label) return;

    const y = calDate.getFullYear(), m = calDate.getMonth();
    label.textContent = MONTHS[m] + ' ' + y;

    const byDay = {};
    EVENTS.forEach(ev => {
      const start = parseDate(ev.date);
      if (!start) return;
      const end = parseDate(ev.endDate) || start;
      for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        if (d.getFullYear() === y && d.getMonth() === m) {
          (byDay[d.getDate()] = byDay[d.getDate()] || []).push(ev);
        }
      }
    });

    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m + 1, 0).getDate();
    const today = new Date();

    let html = DAYS.map(d => '<div class="ev-cal-h">' + d + '</div>').join('');
    for (let i = 0; i < first; i++) html += '<div class="ev-cal-c empty"></div>';

    for (let i = 1; i <= days; i++) {
      const list = byDay[i] || [];
      const isToday = i === today.getDate() && m === today.getMonth() && y === today.getFullYear();
      html += '<div class="ev-cal-c' + (isToday ? ' today' : '') + (list.length ? ' has' : '') + '">' +
        '<span class="ev-cal-n">' + i + '</span>' +
        list.map(e => '<span class="ev-cal-e' + (e.status === 'cancelled' ? ' off' : '') + '" title="' +
          esc(e.title) + '">' + esc(e.title) + '</span>').join('') +
        '</div>';
    }
    grid.innerHTML = html;
  }

  function switchView(view) {
    document.querySelectorAll('.ev-tab').forEach(b =>
      b.classList.toggle('active', b.dataset.view === view));
    document.querySelectorAll('.ev-view').forEach(v =>
      v.classList.toggle('active', v.dataset.view === view));
    if (view === 'calendar') renderCalendar();
  }

  function init() {
    if (!document.getElementById('evUpcoming')) return;

    document.querySelectorAll('.ev-tab').forEach(b =>
      b.addEventListener('click', () => switchView(b.dataset.view)));

    const prev = document.getElementById('evCalPrev');
    const next = document.getElementById('evCalNext');
    if (prev) prev.addEventListener('click', () => { calDate.setMonth(calDate.getMonth() - 1); renderCalendar(); });
    if (next) next.addEventListener('click', () => { calDate.setMonth(calDate.getMonth() + 1); renderCalendar(); });

    fetch('events.json?t=' + Date.now())
      .then(r => r.json())
      .then(data => {
        EVENTS = Array.isArray(data.events) ? data.events : [];
        renderLists();
        renderCalendar();
      })
      .catch(() => {
        const host = document.getElementById('evUpcoming');
        if (host) host.innerHTML =
          '<p class="ev-empty">Our events list could not be loaded just now. ' +
          'Please call the office on 1-888-480-3246.</p>';
      });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
