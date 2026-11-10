/* Dasbor Kembang Subuh — tanpa pustaka luar. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var nfRp = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
  function rupiah(n) { return 'Rp ' + nfRp.format(Math.round(n)); }
  function angka(n) { return nfRp.format(Math.round(n)); }
  function compact(n) {
    if (n >= 1e9) return (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
    if (n >= 1e6) return (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (n >= 1e3) return (n / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
    return String(n);
  }

  /* ---------- Angka KPI menghitung naik ---------- */
  function countUp() {
    if (reduceMotion) return;
    var els = document.querySelectorAll('[data-countup]');
    els.forEach(function (el) {
      var target = Number(el.getAttribute('data-countup'));
      if (!isFinite(target) || target <= 0) return;
      var fmt = el.getAttribute('data-format') === 'rupiah' ? rupiah : angka;
      var finalText = el.textContent;
      var start = null, dur = 700;
      el.setAttribute('aria-label', finalText);
      function frame(t) {
        if (start === null) start = t;
        var p = Math.min(1, (t - start) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = p < 1 ? fmt(target * eased) : finalText;
        if (p < 1) requestAnimationFrame(frame); else el.removeAttribute('aria-label');
      }
      requestAnimationFrame(frame);
    });
  }

  /* ---------- Grafik pendapatan (SVG) ---------- */
  var SVGNS = 'http://www.w3.org/2000/svg';
  function svgEl(name, attrs) {
    var el = document.createElementNS(SVGNS, name);
    for (var k in attrs) if (Object.prototype.hasOwnProperty.call(attrs, k)) el.setAttribute(k, attrs[k]);
    return el;
  }
  function niceMax(v) {
    if (v <= 0) return 100000;
    var exp = Math.pow(10, Math.floor(Math.log10(v)));
    var steps = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10];
    for (var i = 0; i < steps.length; i++) if (steps[i] * exp >= v) return steps[i] * exp;
    return 10 * exp;
  }

  function Chart(box) {
    this.box = box;
    this.status = box.querySelector('.chart-status');
    this.data = null;
    this.idx = -1;
    this.drawn = false;
  }

  Chart.prototype.load = function () {
    var self = this;
    fetch(this.box.getAttribute('data-chart'), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 401) { window.location.href = 'login.php'; throw new Error('auth'); }
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.ok) throw new Error((json && json.error) || 'gagal');
        self.data = json;
        self.render();
        if ('ResizeObserver' in window) {
          var w = self.box.clientWidth;
          new ResizeObserver(function () {
            if (Math.abs(self.box.clientWidth - w) > 4) { w = self.box.clientWidth; self.render(); }
          }).observe(self.box);
        }
      })
      .catch(function (err) {
        if (err && err.message === 'auth') return;
        var sk = self.box.querySelector('.skeleton');
        if (sk) sk.remove();
        self.status.textContent = 'Grafik belum bisa dimuat. Muat ulang halaman untuk mencoba lagi.';
      });
  };

  Chart.prototype.render = function () {
    var box = this.box, pts = this.data.points, range = this.data.range;
    var W = box.clientWidth, H = box.clientHeight;
    var narrow = W < 480;
    var m = { t: 12, r: 12, b: 28, l: narrow ? 46 : 58 };
    var iw = Math.max(10, W - m.l - m.r), ih = Math.max(10, H - m.t - m.b);
    var n = pts.length;
    var maxV = 0, hasPrev = false;
    pts.forEach(function (p) {
      maxV = Math.max(maxV, p.revenue);
      if (p.prev !== null) { hasPrev = true; maxV = Math.max(maxV, p.prev); }
    });
    var top = niceMax(maxV);
    function x(i) { return m.l + (n <= 1 ? iw / 2 : (i / (n - 1)) * iw); }
    function y(v) { return m.t + ih - (v / top) * ih; }
    this.geo = { x: x, y: y, m: m, iw: iw, ih: ih, W: W, H: H };

    var old = box.querySelector('svg');
    if (old) old.remove();
    var sk = box.querySelector('.skeleton');
    if (sk) sk.remove();

    var total = pts.reduce(function (s, p) { return s + p.revenue; }, 0);
    var svg = svgEl('svg', {
      viewBox: '0 0 ' + W + ' ' + H, role: 'img', tabindex: '0',
      'aria-label': 'Grafik pendapatan ' + range.label + ': total ' + rupiah(total) + '. Gunakan tombol panah untuk membaca tiap titik.'
    });
    var defs = svgEl('defs', {});
    var grad = svgEl('linearGradient', { id: 'c-fill', x1: '0', y1: '0', x2: '0', y2: '1' });
    grad.appendChild(svgEl('stop', { offset: '0%', 'stop-color': '#c2325f', 'stop-opacity': '.18' }));
    grad.appendChild(svgEl('stop', { offset: '100%', 'stop-color': '#c2325f', 'stop-opacity': '0' }));
    defs.appendChild(grad);
    svg.appendChild(defs);

    // Garis bantu & sumbu Y (4 interval)
    for (var g = 0; g <= 4; g++) {
      var v = top * g / 4, gy = y(v);
      svg.appendChild(svgEl('line', { x1: m.l, x2: m.l + iw, y1: gy, y2: gy, class: 'c-grid' }));
      var ty = svgEl('text', { x: m.l - 8, y: gy + 4, 'text-anchor': 'end', class: 'c-axis' });
      ty.textContent = g === 0 ? '0' : compact(v);
      svg.appendChild(ty);
    }
    // Sumbu X: label secukupnya agar tidak bertumpuk
    var maxLabels = Math.max(2, Math.floor(iw / (narrow ? 56 : 72)));
    var step = Math.max(1, Math.ceil(n / maxLabels));
    for (var i = 0; i < n; i += step) {
      var tx = svgEl('text', { x: x(i), y: H - 8, 'text-anchor': n === 1 ? 'middle' : (i === 0 ? 'start' : 'middle'), class: 'c-axis' });
      tx.textContent = pts[i].label;
      svg.appendChild(tx);
    }

    var drawIn = !this.drawn && !reduceMotion;   // garis tergambar sekali, hanya pada render pertama
    var plot = svgEl('g', { class: drawIn ? 'c-draw' : '' });
    if (hasPrev) {
      var dp = '', started = false;
      pts.forEach(function (p, i) {
        if (p.prev === null) return;
        dp += (started ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p.prev).toFixed(1) + ' ';
        started = true;
      });
      plot.appendChild(svgEl('path', { d: dp, class: 'c-prev' }));
    }
    var line = '';
    pts.forEach(function (p, i) { line += (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p.revenue).toFixed(1) + ' '; });
    var area = line + 'L' + x(n - 1).toFixed(1) + ' ' + y(0) + ' L' + x(0).toFixed(1) + ' ' + y(0) + ' Z';
    plot.appendChild(svgEl('path', { d: area, class: 'c-area' }));
    var lineAttrs = { d: line, class: 'c-line' };
    if (drawIn) lineAttrs.pathLength = '1';
    plot.appendChild(svgEl('path', lineAttrs));
    if (n === 1) plot.appendChild(svgEl('circle', { cx: x(0), cy: y(pts[0].revenue), r: 4, class: 'c-dot' }));
    svg.appendChild(plot);

    this.guide = svgEl('line', { y1: m.t, y2: m.t + ih, class: 'c-guide', visibility: 'hidden' });
    this.dotPrev = svgEl('circle', { r: 4, class: 'c-dot-prev', visibility: 'hidden' });
    this.dot = svgEl('circle', { r: 5, class: 'c-dot', visibility: 'hidden' });
    svg.appendChild(this.guide); svg.appendChild(this.dotPrev); svg.appendChild(this.dot);
    var hit = svgEl('rect', { x: m.l, y: m.t, width: iw, height: ih, class: 'c-hit' });
    svg.appendChild(hit);
    box.appendChild(svg);
    this.svg = svg;
    this.drawn = true;

    if (!this.tip) {
      this.tip = document.createElement('div');
      this.tip.className = 'chart-tip';
      this.tip.setAttribute('aria-hidden', 'true');
      box.appendChild(this.tip);
    }
    box.classList.add('is-ready');
    this.status.textContent = 'Grafik dimuat. Total ' + rupiah(total) + '.';
    this.bind(hit);
  };

  Chart.prototype.bind = function (hit) {
    var self = this;
    function fromEvent(ev) {
      var r = self.svg.getBoundingClientRect();
      var px = (ev.clientX - r.left) * (self.geo.W / r.width);
      var n = self.data.points.length;
      var i = n <= 1 ? 0 : Math.round(((px - self.geo.m.l) / self.geo.iw) * (n - 1));
      return Math.max(0, Math.min(n - 1, i));
    }
    hit.addEventListener('pointermove', function (ev) { self.show(fromEvent(ev)); });
    hit.addEventListener('pointerdown', function (ev) { self.show(fromEvent(ev)); });
    hit.addEventListener('pointerleave', function () { if (document.activeElement !== self.svg) self.hide(); });
    this.svg.addEventListener('focus', function () { self.show(self.idx >= 0 ? self.idx : self.data.points.length - 1, true); });
    this.svg.addEventListener('blur', function () { self.hide(); });
    this.svg.addEventListener('keydown', function (ev) {
      var n = self.data.points.length, i = self.idx < 0 ? n - 1 : self.idx;
      if (ev.key === 'ArrowRight') i = Math.min(n - 1, i + 1);
      else if (ev.key === 'ArrowLeft') i = Math.max(0, i - 1);
      else if (ev.key === 'Home') i = 0;
      else if (ev.key === 'End') i = n - 1;
      else if (ev.key === 'Escape') { self.hide(); return; }
      else return;
      ev.preventDefault();
      self.show(i, true);
    });
  };

  Chart.prototype.show = function (i, announce) {
    var p = this.data.points[i], g = this.geo, range = this.data.range;
    if (!p) return;
    this.idx = i;
    var cx = g.x(i), cy = g.y(p.revenue);
    this.guide.setAttribute('x1', cx); this.guide.setAttribute('x2', cx); this.guide.setAttribute('visibility', 'visible');
    this.dot.setAttribute('cx', cx); this.dot.setAttribute('cy', cy); this.dot.setAttribute('visibility', 'visible');
    if (p.prev !== null) {
      this.dotPrev.setAttribute('cx', cx); this.dotPrev.setAttribute('cy', g.y(p.prev)); this.dotPrev.setAttribute('visibility', 'visible');
    } else this.dotPrev.setAttribute('visibility', 'hidden');

    var prevName = range.unit === 'hour' ? 'Kemarin' : (p.prev_label || 'Sebelumnya');
    this.tip.innerHTML = '';
    var k = document.createElement('div'); k.className = 'tip-k'; k.textContent = p.label + ' · ' + angka(p.orders) + ' pesanan lunas';
    var v = document.createElement('strong'); v.textContent = rupiah(p.revenue);
    this.tip.appendChild(k); this.tip.appendChild(v);
    if (p.prev !== null) {
      var pv = document.createElement('div'); pv.className = 'tip-prev'; pv.textContent = prevName + ': ' + rupiah(p.prev);
      this.tip.appendChild(pv);
    }
    var scale = this.svg.getBoundingClientRect().width / g.W || 1;
    var tw = this.tip.offsetWidth, th = this.tip.offsetHeight, bw = this.box.clientWidth;
    var left = cx * scale + 14;
    if (left + tw > bw) left = cx * scale - tw - 14;
    left = Math.max(0, left);
    var topPx = Math.max(0, Math.min(cy * scale - th / 2, this.box.clientHeight - th));
    this.tip.style.transform = 'translate(' + Math.round(left) + 'px,' + Math.round(topPx) + 'px)';
    this.tip.classList.add('is-on');
    if (announce) this.status.textContent = p.label + ': ' + rupiah(p.revenue) + (p.prev !== null ? ', ' + prevName.toLowerCase() + ' ' + rupiah(p.prev) : '');
  };

  Chart.prototype.hide = function () {
    if (!this.tip) return;
    this.tip.classList.remove('is-on');
    this.guide.setAttribute('visibility', 'hidden');
    this.dot.setAttribute('visibility', 'hidden');
    this.dotPrev.setAttribute('visibility', 'hidden');
  };

  /* ---------- Produk: tandai baris yang berubah ---------- */
  function productRows() {
    document.querySelectorAll('tr[data-row]').forEach(function (row) {
      var form = row.querySelector('form');
      if (!form) return;
      var fields = document.querySelectorAll('[form="' + form.id + '"]');
      var note = row.querySelector('.dirty-note');
      var initial = [];
      fields.forEach(function (f, i) { initial[i] = f.type === 'checkbox' ? f.checked : f.value; });
      function check() {
        var dirty = false;
        fields.forEach(function (f, i) { if ((f.type === 'checkbox' ? f.checked : f.value) !== initial[i]) dirty = true; });
        row.classList.toggle('is-dirty', dirty);
        if (note) note.textContent = dirty ? 'Belum disimpan' : '';
      }
      fields.forEach(function (f) { f.addEventListener('input', check); f.addEventListener('change', check); });
      form.addEventListener('submit', function (ev) {
        var bad = Array.prototype.filter.call(fields, function (f) { return f.checkValidity && !f.checkValidity(); });
        if (bad.length) { ev.preventDefault(); bad[0].reportValidity(); }
      });
    });
  }

  /* ---------- Lain-lain ---------- */
  function misc() {
    document.querySelectorAll('[data-print]').forEach(function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
    document.querySelectorAll('[data-confirm]').forEach(function (b) {
      b.addEventListener('click', function (ev) {
        if (!window.confirm(b.getAttribute('data-confirm'))) ev.preventDefault();
      });
    });
    document.querySelectorAll('[data-pw-toggle]').forEach(function (b) {
      var input = document.getElementById(b.getAttribute('aria-controls'));
      if (!input) return;
      b.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        b.setAttribute('aria-pressed', String(show));
        b.textContent = show ? 'Tutup' : 'Lihat';
        input.focus();
      });
    });
    // Cegah kirim ganda pada formulir POST.
    document.querySelectorAll('form[method="post"]').forEach(function (f) {
      f.addEventListener('submit', function (ev) {
        if (ev.defaultPrevented) return;
        if (f.dataset.sent) { ev.preventDefault(); return; }
        f.dataset.sent = '1';
        setTimeout(function () { delete f.dataset.sent; }, 4000);
      });
    });
  }

  /* ---------- Setelah simpan: toast "Tersimpan" + animasi status berubah ---------- */
  var STATUS_KEY = 'adm-status-prev';
  function store(k, v) { try { if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) { /* abaikan */ } }
  function load(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function toneOf(el) { var m = el && /\bpetal-([a-z]+)\b/.exec(el.className); return m ? m[1] : ''; }

  function savedFeedback() {
    var ok = document.querySelector('.main > .flash-success');
    if (ok) {
      // Flash sudah role="status" (dibacakan); toast hanya visual.
      var t = document.createElement('div');
      t.className = 'toast';
      t.setAttribute('aria-hidden', 'true');
      var petal = document.createElement('span');
      petal.className = 'toast-petal';
      t.appendChild(petal);
      t.appendChild(document.createTextNode('Tersimpan'));
      document.body.appendChild(t);
      requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add('is-on'); }); });
      setTimeout(function () {
        t.classList.remove('is-on');
        setTimeout(function () { t.remove(); }, 400);
      }, reduceMotion ? 2000 : 2600);
    }

    // Detail pesanan: ingat status lama saat tombol status ditekan, lalu animasikan setelah redirect.
    var actions = document.querySelector('form.status-actions');
    var metaPetal = document.querySelector('.page-meta .status .petal');
    var id = new URLSearchParams(window.location.search).get('id') || '';
    if (actions && metaPetal) {
      actions.addEventListener('submit', function () { store(STATUS_KEY, id + ':' + toneOf(metaPetal)); });
    }
    var prev = load(STATUS_KEY);
    store(STATUS_KEY, null);
    if (!ok || !prev || !metaPetal || reduceMotion) return;
    var parts = prev.split(':');
    var oldTone = parts[1] || '';
    if (parts[0] !== id || !oldTone || oldTone === toneOf(metaPetal)) return;
    var petals = [metaPetal];
    var nowStep = document.querySelector('.step-now .petal');
    if (nowStep) petals.push(nowStep);
    petals.forEach(function (p) {
      var ghost = document.createElement('span');
      ghost.className = 'petal petal-' + oldTone + ' petal-ghost';
      ghost.setAttribute('aria-hidden', 'true');
      p.appendChild(ghost);
      p.classList.add('is-pop');
      setTimeout(function () { ghost.remove(); p.classList.remove('is-pop'); }, 700);
    });
    metaPetal.parentNode.classList.add('is-changed');
    var step = document.querySelector('.step-now');
    if (step) step.classList.add('is-changed');
  }

  function init() {
    savedFeedback();
    countUp();
    document.querySelectorAll('[data-chart]').forEach(function (box) { new Chart(box).load(); });
    productRows();
    misc();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
