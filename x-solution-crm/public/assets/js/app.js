/* X-Solution CRM – Vanilla JS, kein Build-Schritt */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Einheitlich "1.234,56 €" (wie die serverseitige Formatierung)
  const money = (euros) => {
    const n = Number(euros) || 0;
    const s = Math.abs(n).toFixed(2).split('.');
    s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (n < 0 ? '-' : '') + s[0] + ',' + s[1] + ' €';
  };
  const moneyShort = (euros) => {
    const n = Number(euros) || 0;
    if (Math.abs(n) >= 1000) return (n / 1000).toFixed(Math.abs(n) >= 10000 ? 0 : 1).replace('.', ',') + ' Tsd. €';
    return money(n);
  };

  const parseMoney = (str) => {
    let s = String(str || '').replace(/[€\s ']/g, '');
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    else if ((s.match(/\./g) || []).length > 1) s = s.replace(/\./g, '');
    const n = parseFloat(s);
    return isNaN(n) ? null : n;
  };
  const parseDate = (str) => {
    const m = String(str || '').trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
    if (!m) return null;
    const d = new Date(+m[3], +m[2] - 1, +m[1]);
    return d.getMonth() === +m[2] - 1 ? d : null;
  };
  const fmtDate = (d) => String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0') + '.' + d.getFullYear();

  document.addEventListener('DOMContentLoaded', () => {
    /* Mobile Navigation */
    $$('[data-toggle-nav]').forEach((b) => b.addEventListener('click', () => document.body.classList.toggle('nav-open')));
    document.addEventListener('click', (e) => {
      if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar') && !e.target.closest('[data-toggle-nav]')) {
        document.body.classList.remove('nav-open');
      }
    });

    /* Dropdowns (Glocke) */
    $$('[data-dropdown]').forEach((btn) => {
      const menu = document.getElementById(btn.dataset.dropdown);
      if (!menu) return;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = menu.classList.toggle('hidden') === false;
        btn.setAttribute('aria-expanded', String(open));
      });
      document.addEventListener('click', (e) => {
        if (!menu.contains(e.target)) { menu.classList.add('hidden'); btn.setAttribute('aria-expanded', 'false'); }
      });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') menu.classList.add('hidden'); });
    });

    /* Auto-Submit (Zeitraumfilter, Listenfilter) */
    $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form && el.form.submit()));

    /* Bestätigungsdialoge */
    $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
      if (!window.confirm(f.dataset.confirm)) e.preventDefault();
    }));

    /* Flash-Meldungen ausblenden */
    $$('[data-dismiss]').forEach((el) => setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 6000));

    /* In Zwischenablage kopieren */
    $$('pre[data-copy]').forEach((pre) => pre.addEventListener('click', () => {
      if (navigator.clipboard) navigator.clipboard.writeText(pre.textContent.trim()).then(() => {
        pre.classList.add('copied'); setTimeout(() => pre.classList.remove('copied'), 900);
      });
    }));

    /* Betragsfelder beim Verlassen formatieren */
    $$('input[data-money]').forEach((inp) => inp.addEventListener('blur', () => {
      const n = parseMoney(inp.value);
      if (n !== null) inp.value = money(n).replace(' €', '');
    }));
    /* Datumsfelder: "6.10.26" → "06.10.2026" */
    $$('input[data-date]').forEach((inp) => inp.addEventListener('blur', () => {
      const m = inp.value.trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/);
      if (m) inp.value = m[1].padStart(2, '0') + '.' + m[2].padStart(2, '0') + '.20' + m[3];
      else { const d = parseDate(inp.value); if (d) inp.value = fmtDate(d); }
    }));

    initContractForm();
    initChoiceDialog();
    initCharts();
  });

  /* Vertragsformular: Monatswert aus Gesamtwert und Laufzeit vorschlagen */
  function initContractForm() {
    $$('[data-contract-form]').forEach((form) => {
      const total = $('[name=total_value]', form);
      const monthly = $('[name=monthly_value]', form);
      const start = $('[name=start_date]', form);
      const end = $('[name=end_date]', form);
      const interval = $('[name=billing_interval]', form);
      const hint = $('[data-monthly-hint]', form);
      const durationInput = $('[data-duration]', form);
      if (!total || !monthly) return;
      let touched = monthly.value.trim() !== '';
      monthly.addEventListener('input', () => { touched = monthly.value.trim() !== ''; });

      const months = () => {
        const a = parseDate(start && start.value), b = parseDate(end && end.value);
        if (!a || !b || b < a) return null;
        const bb = new Date(b.getFullYear(), b.getMonth(), b.getDate() + 1);
        let m = (bb.getFullYear() - a.getFullYear()) * 12 + (bb.getMonth() - a.getMonth());
        m += (bb.getDate() - a.getDate()) / 30;
        return Math.max(Math.round(m * 10) / 10, 0);
      };
      const update = () => {
        const t = parseMoney(total.value);
        const m = months();
        if (durationInput) durationInput.textContent = m ? m.toString().replace('.', ',') + ' Monate' : '–';
        if (t === null) { if (hint) hint.textContent = ''; return; }
        const div = m && m >= 1 ? m : (interval && interval.value === 'einmalig' ? 1 : 12);
        const suggestion = t / div;
        if (hint) hint.textContent = 'Vorschlag: ' + money(suggestion) + ' pro Monat (' + money(suggestion * 12) + ' p. a.)';
        if (!touched) monthly.placeholder = money(suggestion).replace(' €', '');
      };
      [total, start, end, interval].forEach((el) => el && el.addEventListener('input', update));
      [start, end].forEach((el) => el && el.addEventListener('blur', update));
      // Ende aus Laufzeit-Schnellwahl
      $$('[data-term]', form).forEach((b) => b.addEventListener('click', () => {
        const a = parseDate(start.value) || new Date();
        if (!parseDate(start.value)) start.value = fmtDate(a);
        const e = new Date(a.getFullYear(), a.getMonth() + Number(b.dataset.term), a.getDate() - 1);
        end.value = fmtDate(e);
        update();
      }));
      update();
    });
  }

  /* Übernahmedialog: Option-Panels ein-/ausblenden, Neukunden-Felder nur bei "neu" */
  function initChoiceDialog() {
    $$('form').forEach((form) => {
      const sel = $('[name=customer_choice]', form);
      const newFields = $('[data-new-customer]', form);
      if (sel && newFields) {
        const syncCustomer = () => {
          const show = sel.value === 'neu' && !sel.disabled;
          newFields.classList.toggle('hidden', !show);
          $$('input', newFields).forEach((i) => { i.disabled = !show; });
        };
        sel.addEventListener('change', syncCustomer);
        form.addEventListener('change', syncCustomer);
        syncCustomer();
      }
    });
    const form = $('[data-accept-form]');
    if (!form) return;
    const sync = () => {
      const mode = ($('input[name=mode]:checked', form) || {}).value;
      $$('[data-panel]', form).forEach((p) => {
        const show = p.dataset.panel.split(' ').includes(mode);
        p.classList.toggle('hidden', !show);
        $$('input, select, textarea', p).forEach((i) => { i.disabled = !show; });
      });
      const btn = $('[data-submit-label]', form);
      if (btn) btn.textContent = { interessent: 'Als Interessent übernehmen', vertrag: 'Vertrag anlegen', ablegen: 'Nur ablegen' }[mode] || 'Übernehmen';
      form.dispatchEvent(new Event('change'));
    };
    $$('input[name=mode]', form).forEach((r) => r.addEventListener('change', sync));
    sync();
  }

  /* ---------- Diagramme (Chart.js per CDN) ---------- */
  function readJSON(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }

  function initCharts() {
    if (!window.Chart) return;
    const css = getComputedStyle(document.documentElement);
    const c = (v) => css.getPropertyValue(v).trim();
    const accent = c('--accent'), glow = c('--accent-glow'), soft = c('--text-soft'), text = c('--text');
    const grid = 'rgba(96,165,250,.08)';

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = soft;
    Chart.defaults.plugins.legend.display = false;
    const tooltip = {
      backgroundColor: '#0B1324', borderColor: 'rgba(96,165,250,.35)', borderWidth: 1,
      titleColor: text, bodyColor: text, padding: 12, cornerRadius: 10, displayColors: false,
      titleFont: { weight: '600' }, bodyFont: { family: "'Inter', sans-serif" },
    };

    // Umsatzentwicklung (Linie)
    const rev = readJSON('chart-revenue-data');
    const revEl = document.getElementById('chart-revenue');
    if (rev && revEl) {
      const ctx = revEl.getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, revEl.parentNode.clientHeight || 280);
      grad.addColorStop(0, 'rgba(59,130,246,.35)');
      grad.addColorStop(1, 'rgba(59,130,246,0)');
      const datasets = [{
        label: 'Umsatz', data: rev.values, borderColor: accent, backgroundColor: grad, fill: true, tension: .38,
        borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 7, pointBackgroundColor: '#0A0F1E', pointBorderColor: glow, pointBorderWidth: 2,
      }];
      if (rev.previous) {
        datasets.push({
          label: 'Vorjahr', data: rev.previous, borderColor: 'rgba(139,151,180,.45)', borderDash: [5, 5], borderWidth: 1.5,
          pointRadius: 0, pointHoverRadius: 4, fill: false, tension: .38,
        });
      }
      new Chart(revEl, {
        type: 'line',
        data: { labels: rev.labels, datasets },
        options: {
          maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          plugins: { tooltip: { ...tooltip, displayColors: !!rev.previous, callbacks: { label: (i) => (rev.previous ? i.dataset.label + ': ' : '') + money(i.parsed.y) } },
                     legend: { display: !!rev.previous, align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } },
          scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid }, border: { display: false }, ticks: { callback: (v) => moneyShort(v) }, beginAtZero: true },
          },
        },
      });
    }

    // Vertragslaufzeiten (Balken)
    const dur = readJSON('chart-durations-data');
    const durEl = document.getElementById('chart-durations');
    if (dur && durEl) {
      const ctx = durEl.getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, durEl.parentNode.clientHeight || 280);
      grad.addColorStop(0, glow);
      grad.addColorStop(1, accent);
      new Chart(durEl, {
        type: 'bar',
        data: { labels: dur.labels, datasets: [{ data: dur.values, backgroundColor: grad, borderRadius: 8, borderSkipped: false, maxBarThickness: 46 }] },
        options: {
          maintainAspectRatio: false,
          plugins: { tooltip: { ...tooltip, callbacks: { title: (i) => i[0].label + ' Monate', label: (i) => i.parsed.y + (i.parsed.y === 1 ? ' Vertrag' : ' Verträge') } } },
          scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid }, border: { display: false }, ticks: { precision: 0 }, beginAtZero: true },
          },
        },
      });
    }

    // Vertragsstatus (Donut)
    const st = readJSON('chart-status-data');
    const stEl = document.getElementById('chart-status');
    if (st && stEl) {
      const total = st.values.reduce((a, b) => a + b, 0);
      new Chart(stEl, {
        type: 'doughnut',
        data: { labels: st.labels, datasets: [{ data: total ? st.values : [1], backgroundColor: total ? st.colors : ['rgba(96,165,250,.12)'], borderColor: '#101A2E', borderWidth: 3, hoverOffset: 6 }] },
        options: {
          maintainAspectRatio: false, cutout: '72%',
          plugins: { tooltip: { ...tooltip, enabled: total > 0, callbacks: { label: (i) => i.label + ': ' + i.parsed + ' (' + Math.round(i.parsed / total * 100) + ' %)' } } },
        },
        plugins: [{
          id: 'center',
          afterDraw(chart) {
            const { ctx, chartArea: a } = chart;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.fillStyle = text;
            ctx.font = "700 26px 'Inter', sans-serif";
            ctx.fillText(String(total), (a.left + a.right) / 2, (a.top + a.bottom) / 2 + 4);
            ctx.fillStyle = soft;
            ctx.font = "500 12px 'Inter', sans-serif";
            ctx.fillText('Verträge', (a.left + a.right) / 2, (a.top + a.bottom) / 2 + 24);
            ctx.restore();
          },
        }],
      });
    }

    // Umsatzseite: Rechnungen vs. Vertragsbasis
    const ry = readJSON('chart-revenue-year-data');
    const ryEl = document.getElementById('chart-revenue-year');
    if (ry && ryEl) {
      new Chart(ryEl, {
        data: {
          labels: ry.labels,
          datasets: [
            { type: 'bar', label: 'Umsatz (Rechnungen netto)', data: ry.invoiced, backgroundColor: accent, borderRadius: 6, maxBarThickness: 34, order: 2 },
            { type: 'line', label: 'Vertragsbasis (MRR)', data: ry.contracts, borderColor: glow, backgroundColor: glow, tension: .35, pointRadius: 3, borderWidth: 2, order: 1 },
          ],
        },
        options: {
          maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: true, align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                     tooltip: { ...tooltip, displayColors: true, callbacks: { label: (i) => i.dataset.label + ': ' + money(i.parsed.y) } } },
          scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid }, border: { display: false }, ticks: { callback: (v) => moneyShort(v) }, beginAtZero: true },
          },
        },
      });
    }
  }
})();
