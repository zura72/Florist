// Kembang Subuh — halaman checkout: keranjang dari localStorage, harga resmi dari server,
// validasi formulir, pembuatan pesanan, Midtrans Snap atau simulasi pembayaran (mode uji).
(function () {
  'use strict';

  var CART_KEY = 'ks-cart-v1';
  var WA = '6281200000000';
  var MAX_QTY = 50;
  var MAX_LINES = 20;
  var SNAP_HOSTS = ['https://app.sandbox.midtrans.com/', 'https://app.midtrans.com/'];
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  var $ = function (id) { return document.getElementById(id); };
  var form = $('co-form');
  var grid = $('co-grid');
  var emptyEl = $('co-empty');
  var notice = $('co-notice');
  var linesEl = $('co-lines');
  var payBtn = $('co-pay');
  var submitErr = $('co-submit-err');
  var kartuLeft = $('kartu-left');

  var state = {
    cart: null,          // { items: {id: {name, price, qty}}, kartu, penerima, alamat }
    products: {},        // id -> produk dari server
    shipping: 0,
    zones: [],
    minDate: '',
    mode: 'demo',
    busy: false,
    ready: false
  };

  // ---------- Util ----------
  function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
  function waLink(text) { return 'https://wa.me/' + WA + '?text=' + encodeURIComponent(text); }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function tick(node) {
    if (!node || reduced.matches) return;
    node.classList.remove('is-tick');
    void node.offsetWidth;
    node.classList.add('is-tick');
  }
  function wibNow() {
    // Waktu WIB (UTC+7) dibaca dari komponen UTC agar tidak bergantung zona waktu perangkat.
    var d = new Date(Date.now() + 7 * 3600 * 1000);
    return { date: d.toISOString().slice(0, 10), minutes: d.getUTCHours() * 60 + d.getUTCMinutes(), hour: d.getUTCHours() };
  }
  function addDays(ymd, n) {
    var d = new Date(ymd + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  }
  function clientMinDate() {
    var now = wibNow();
    return now.hour >= 13 ? addDays(now.date, 1) : now.date;
  }
  function normalizePhone(v) {
    var digits = String(v || '').replace(/[\s\-().]/g, '');
    var m = digits.match(/^(?:\+?62|0)(8\d{7,12})$/);
    return m ? '62' + m[1] : '';
  }
  function cleanText(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }

  // ---------- Keranjang (localStorage) ----------
  function readCart() {
    var c = null;
    try { c = JSON.parse(localStorage.getItem(CART_KEY) || 'null'); } catch (e) { c = null; }
    if (!c || typeof c !== 'object') c = {};
    if (!c.items || typeof c.items !== 'object' || Array.isArray(c.items)) c.items = {};
    Object.keys(c.items).forEach(function (id) {
      var it = c.items[id];
      var qty = it && parseInt(it.qty, 10);
      if (!it || !/^[a-z0-9-]{1,40}$/.test(id) || !(qty > 0)) { delete c.items[id]; return; }
      it.qty = Math.min(qty, MAX_QTY);
    });
    return c;
  }
  function saveCart() {
    try { localStorage.setItem(CART_KEY, JSON.stringify(state.cart)); } catch (e) { /* mode privat: abaikan */ }
  }
  function clearCart() {
    try { localStorage.removeItem(CART_KEY); } catch (e) { /* abaikan */ }
  }
  function cartIds() { return Object.keys(state.cart.items); }

  // ---------- Pemberitahuan ----------
  function showNotice(title, list, extraNode) {
    notice.textContent = '';
    if (!title) return;
    notice.appendChild(el('p', null, title));
    if (list && list.length) {
      var ul = el('ul');
      list.forEach(function (t) { ul.appendChild(el('li', null, t)); });
      notice.appendChild(ul);
    }
    if (extraNode) notice.appendChild(extraNode);
  }

  function showEmpty() {
    grid.hidden = true;
    emptyEl.hidden = false;
    document.title = 'Keranjang kosong | Kembang Subuh';
  }

  // ---------- Ringkasan ----------
  function totals() {
    var sub = 0;
    cartIds().forEach(function (id) {
      var p = state.products[id];
      sub += (p ? p.price : 0) * state.cart.items[id].qty;
    });
    var z = pickedZone();
    var ship = z && z.fee !== null ? z.fee : 0;
    return { sub: sub, ship: ship, total: sub + ship, zone: z };
  }

  // Angka total bergulir ke nilai baru (rAF, hanya mengganti teks; langsung saat kurangi gerakan).
  var rolls = {};
  function rollTo(node, to) {
    var r = rolls[node.id] || (rolls[node.id] = { value: null, raf: 0 });
    var from = r.value;
    r.value = to;
    cancelAnimationFrame(r.raf);
    if (from === null || from === to || reduced.matches) { node.textContent = rupiah(to); return; }
    tick(node);
    var start = null, dur = 420;
    function frame(ts) {
      if (start === null) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      var v = from + (to - from) * (1 - Math.pow(1 - p, 3));
      node.textContent = rupiah(p < 1 ? Math.round(v / 500) * 500 : to);
      if (p < 1) r.raf = requestAnimationFrame(frame);
    }
    r.raf = requestAnimationFrame(frame);
  }

  function renderTotals() {
    var t = totals();
    rollTo($('t-sub'), t.sub);
    rollTo($('t-total'), t.total);
    var ship = $('t-ship');
    var shipText = !t.zone ? 'Pilih daerah' : (t.zone.fee === null ? 'Tanya dulu' : rupiah(t.ship));
    if (ship.textContent !== shipText) ship.textContent = shipText;
    $('t-zone').textContent = t.zone ? '(' + t.zone.label + ')' : '';
    payBtn.querySelector('.co-pay-label').textContent = 'Bayar sekarang · ' + rupiah(t.total);
  }

  function lineNode(id, isNew) {
    var item = state.cart.items[id];
    var p = state.products[id];
    var li = el('li', 'co-line' + (isNew ? ' is-new' : ''));
    li.dataset.id = id;

    var img = document.createElement('img');
    // Small thumbnail: use the 320px WebP made by tools/make-webp.py.
    img.src = String(p.image).replace(/^assets\/([^/]+)\.jpg$/, 'assets/webp/$1-320.webp');
    img.alt = '';
    img.width = 56;
    img.height = 70;
    img.loading = 'lazy';
    img.decoding = 'async';
    li.appendChild(img);

    var name = el('p', 'co-line-name', p.name);
    li.appendChild(name);

    var meta = el('div', 'co-line-meta');
    var price = el('p', 'co-line-price', rupiah(p.price * item.qty));
    price.appendChild(el('span', 'co-line-unit', rupiah(p.price) + ' / buket'));

    var stepper = el('div', 'co-stepper');
    stepper.setAttribute('role', 'group');
    stepper.setAttribute('aria-label', 'Jumlah ' + p.name);
    var max = Math.min(MAX_QTY, Math.max(1, p.stock));

    var minus = el('button', item.qty <= 1 ? 'co-del' : null);
    minus.type = 'button';
    if (item.qty <= 1) {
      minus.setAttribute('aria-label', 'Hapus ' + p.name + ' dari keranjang');
      minus.innerHTML = '<svg class="co-ico-sm" aria-hidden="true"><use href="#i-trash"></use></svg>';
    } else {
      minus.setAttribute('aria-label', 'Kurangi ' + p.name);
      minus.textContent = '−';
    }
    minus.addEventListener('click', function () { changeQty(id, -1); });

    var out = el('output', null, String(item.qty));
    out.setAttribute('aria-live', 'polite');

    var plus = el('button', null, '+');
    plus.type = 'button';
    plus.setAttribute('aria-label', 'Tambah ' + p.name);
    plus.disabled = item.qty >= max;
    plus.addEventListener('click', function () { changeQty(id, 1); });

    stepper.appendChild(minus);
    stepper.appendChild(out);
    stepper.appendChild(plus);
    meta.appendChild(price);
    meta.appendChild(stepper);
    li.appendChild(meta);
    return li;
  }

  function renderLines(newIds) {
    linesEl.textContent = '';
    cartIds().forEach(function (id) {
      linesEl.appendChild(lineNode(id, newIds && newIds.indexOf(id) !== -1));
    });
    linesEl.setAttribute('aria-busy', 'false');
    renderTotals();
  }

  // Baris yang dihapus memudar keluar; baris dan total di bawahnya naik halus (FLIP, transform saja).
  var leaving = false;
  function collapseLine(id, done) {
    var li = linesEl.querySelector('[data-id="' + id + '"]');
    if (!li || reduced.matches || typeof li.animate !== 'function') { done(); return; }
    leaving = true;
    li.classList.add('is-leaving');
    setTimeout(function () {
      var tracked = [];
      var sib;
      for (sib = li.nextElementSibling; sib; sib = sib.nextElementSibling) tracked.push({ id: sib.dataset.id, top: sib.getBoundingClientRect().top });
      for (sib = linesEl.nextElementSibling; sib; sib = sib.nextElementSibling) tracked.push({ node: sib, top: sib.getBoundingClientRect().top });
      leaving = false;
      done();
      if (grid.hidden) return;
      tracked.forEach(function (t) {
        var node = t.node || linesEl.querySelector('[data-id="' + t.id + '"]');
        if (!node || node.hidden) return;
        var dy = t.top - node.getBoundingClientRect().top;
        if (Math.abs(dy) < 1) return;
        node.animate([{ transform: 'translateY(' + dy + 'px)' }, { transform: 'none' }], { duration: 300, easing: 'cubic-bezier(.2,.8,.2,1)' });
      });
    }, 230);
  }

  function removeLine(id, p) {
    delete state.cart.items[id];
    saveCart();
    if (!cartIds().length) { showEmpty(); showNotice(p.name + ' dihapus. Keranjang sekarang kosong.'); return; }
    renderLines();
    showNotice(p.name + ' dihapus dari keranjang.');
    var first = linesEl.querySelector('.co-stepper button');
    if (first) first.focus();
  }

  function changeQty(id, delta) {
    if (state.busy || leaving) return;
    var item = state.cart.items[id];
    var p = state.products[id];
    if (!item || !p) return;
    var next = item.qty + delta;
    var focusSel = delta > 0 ? 'button:last-child' : 'button:first-child';
    if (next < 1) {
      collapseLine(id, function () { removeLine(id, p); });
      return;
    }
    item.qty = Math.min(next, MAX_QTY, Math.max(1, p.stock));
    item.price = p.price;
    saveCart();
    var old = linesEl.querySelector('[data-id="' + id + '"]');
    var fresh = lineNode(id, false);
    linesEl.replaceChild(fresh, old);
    tick(fresh.querySelector('.co-line-price'));
    renderTotals();
    var target = fresh.querySelector('.co-stepper ' + focusSel);
    if (target && !target.disabled) target.focus();
    else { var alt = fresh.querySelector('.co-stepper button:not(:disabled)'); if (alt) alt.focus(); }
  }

  // ---------- Produk dari server ----------
  function reconcile(list) {
    var removed = [];
    var changed = [];
    state.products = {};
    list.forEach(function (p) { state.products[p.id] = p; });
    cartIds().forEach(function (id) {
      var item = state.cart.items[id];
      var p = state.products[id];
      if (!p || p.stock < 1) {
        removed.push((item.name || 'Satu produk') + ' sudah tidak tersedia, jadi kami keluarkan dari keranjang.');
        delete state.cart.items[id];
        return;
      }
      if (typeof item.price === 'number' && item.price !== p.price) {
        changed.push('Harga ' + p.name + ' sekarang ' + rupiah(p.price) + '.');
      }
      if (item.qty > p.stock) {
        changed.push('Stok ' + p.name + ' tinggal ' + p.stock + ', jumlahnya kami sesuaikan.');
        item.qty = p.stock;
      }
      item.name = p.name;
      item.price = p.price;
    });
    var ids = cartIds();
    if (ids.length > MAX_LINES) {
      ids.slice(MAX_LINES).forEach(function (id) { delete state.cart.items[id]; });
      changed.push('Satu pesanan maksimal ' + MAX_LINES + ' jenis bunga. Sisanya bisa dipesan terpisah.');
    }
    saveCart();
    var msgs = removed.concat(changed);
    if (msgs.length) showNotice('Keranjangmu sudah kami sesuaikan dengan stok terbaru:', msgs);
  }

  function loadProducts() {
    return fetch('api/products.php', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.ok) throw new Error(d.error || 'gagal'); return d; }); });
  }

  // ---------- Tanggal & slot ----------
  var dateInput = $('f-tanggal');
  var slotInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="delivery_slot"]'));
  var SLOT_START = { pagi: 9, siang: 12, sore: 15 };

  function setupDate() {
    var min = state.minDate || clientMinDate();
    var local = clientMinDate();
    if (local > min) min = local;               // halaman dibiarkan terbuka melewati 13.00
    dateInput.min = min;
    dateInput.max = addDays(wibNow().date, 60);   // sama dengan batas server (60 hari dari hari ini)
    if (!dateInput.value || dateInput.value < min) dateInput.value = min;
    updateSlots();
  }

  function updateSlots() {
    var now = wibNow();
    var isToday = dateInput.value === now.date;
    slotInputs.forEach(function (input) {
      var off = isToday && now.minutes >= (SLOT_START[input.value] - 2) * 60;
      input.disabled = off;
      if (off && input.checked) input.checked = false;
      input.closest('.co-slot').title = off ? 'Sudah lewat untuk hari ini' : '';
    });
    if (!form.querySelector('input[name="delivery_slot"]:checked')) {
      var firstOpen = slotInputs.filter(function (i) { return !i.disabled; })[0];
      if (firstOpen && isToday) firstOpen.checked = true;
    }
  }

  // ---------- Daerah antar ----------
  var zoneGroup = $('f-zone');
  var zoneRow = $('zone-row');
  function zoneInputs() { return Array.prototype.slice.call(zoneRow.querySelectorAll('input[name="shipping_zone"]')); }
  function pickedZone() {
    var i = zoneRow.querySelector('input[name="shipping_zone"]:checked');
    if (!i) return null;
    for (var k = 0; k < state.zones.length; k++) if (state.zones[k].code === i.value) return state.zones[k];
    return null;
  }
  function renderZones(list) {
    state.zones = (Array.isArray(list) ? list : []).filter(function (z) { return z && typeof z.code === 'string'; });
    zoneRow.textContent = '';
    state.zones.forEach(function (z, i) {
      var label = el('label', 'co-slot');
      var input = document.createElement('input');
      input.type = 'radio';
      input.name = 'shipping_zone';
      input.value = z.code;
      if (i === 0) input.required = true;
      var span = el('span');
      span.appendChild(el('b', '', z.label));
      span.appendChild(el('span', 'co-zone-fee', z.fee === null ? 'Tanya dulu' : rupiah(Number(z.fee))));
      label.appendChild(input);
      label.appendChild(span);
      zoneRow.appendChild(label);
      input.addEventListener('change', function () { validateZone(); renderTotals(); });
    });
  }
  function validateZone() {
    var z = pickedZone();
    var errNode = $('f-zone-err');
    errNode.textContent = '';
    var msg = '';
    if (!z) msg = 'Pilih daerah antar.';
    else if (z.fee === null) msg = 'Untuk ' + z.label + ', ongkirnya kami hitung dulu. ';
    zoneGroup.setAttribute('aria-invalid', msg ? 'true' : 'false');
    if (msg) {
      errNode.appendChild(document.createTextNode(msg));
      if (z) errNode.appendChild(waNode('Pesan lewat WhatsApp', waOrderText()));
    }
    return msg;
  }

  // ---------- Validasi ----------
  var fields = {
    customer_name:  { node: $('f-nama'),        test: function (v) { v = cleanText(v); return v.length >= 2 && v.length <= 120 ? '' : 'Isi nama pemesan, minimal 2 huruf.'; } },
    customer_phone: { node: $('f-wa'),          test: function (v) { return normalizePhone(v) ? '' : (v.trim() ? 'Nomor WhatsApp belum benar. Contoh: 0812 3456 7890.' : 'Isi nomor WhatsApp pemesan.'); } },
    customer_email: { node: $('f-email'),       test: function (v) { v = v.trim(); return !v || (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) && v.length <= 160) ? '' : 'Alamat email belum benar. Contoh: nama@gmail.com.'; } },
    recipient_name: { node: $('f-penerima'),    test: function (v) { v = cleanText(v); return v.length >= 2 && v.length <= 120 ? '' : 'Isi nama penerima, minimal 2 huruf.'; } },
    recipient_phone:{ node: $('f-penerima-wa'), test: function (v) { return normalizePhone(v) ? '' : (v.trim() ? 'Nomor penerima belum benar. Contoh: 0812 3456 7890.' : 'Isi nomor HP penerima agar kurir bisa menghubungi.'); } },
    address:        { node: $('f-alamat'),      test: function (v) { v = v.trim(); return v.length >= 15 && v.length <= 500 ? '' : 'Tulis alamat lengkap: jalan, nomor rumah, kelurahan, dan kecamatan.'; } },
    delivery_date:  { node: dateInput,          test: function (v) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return 'Pilih tanggal antar.';
      if (v < dateInput.min) return 'Tanggal ini sudah lewat batas pesan 13.00 WIB. Pilih mulai ' + formatDate(dateInput.min) + '.';
      if (v > dateInput.max) return 'Tanggal antar paling lambat 60 hari dari sekarang.';
      return '';
    } },
    card_message:   { node: $('f-kartu'),       test: function (v) { return v.length <= 160 ? '' : 'Tulisan kartu maksimal 160 huruf.'; } }
  };
  var slotGroup = $('f-slot');

  function formatDate(ymd) {
    try {
      return new Date(ymd + 'T00:00:00Z').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });
    } catch (e) { return ymd; }
  }

  function setError(name, msg) {
    var errNode;
    if (name === 'shipping_zone') {
      $('f-zone-err').textContent = msg || '';
      zoneGroup.setAttribute('aria-invalid', msg ? 'true' : 'false');
      return;
    } else if (name === 'delivery_slot') {
      errNode = $('f-slot-err');
      slotGroup.setAttribute('aria-invalid', msg ? 'true' : 'false');
    } else if (name === 'items') {
      submitErr.textContent = msg;
      return;
    } else {
      var f = fields[name];
      if (!f) return;
      errNode = $(f.node.id + '-err');
      f.node.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }
    errNode.textContent = msg || '';
  }

  function validateField(name) {
    var f = fields[name];
    var msg = f.test(f.node.value);
    setError(name, msg);
    return msg;
  }
  function validateSlot() {
    var picked = form.querySelector('input[name="delivery_slot"]:checked');
    var msg = picked ? '' : (slotInputs.every(function (i) { return i.disabled; }) ? 'Semua waktu hari ini sudah lewat. Pilih tanggal lain.' : 'Pilih waktu antar.');
    setError('delivery_slot', msg);
    return msg;
  }
  function validateAll() {
    var firstBad = null;
    Object.keys(fields).forEach(function (name) {
      if (validateField(name) && !firstBad) firstBad = fields[name].node;
    });
    if (validateZone() && !firstBad) firstBad = (pickedZone() && zoneRow.querySelector('input:checked')) || zoneInputs()[0] || null;
    if (validateSlot() && !firstBad) firstBad = slotInputs.filter(function (i) { return !i.disabled; })[0] || dateInput;
    return firstBad;
  }

  Object.keys(fields).forEach(function (name) {
    var node = fields[name].node;
    node.addEventListener('blur', function () { if (node.value.trim() || node.getAttribute('aria-invalid') === 'true') validateField(name); });
    node.addEventListener('input', function () { if (node.getAttribute('aria-invalid') === 'true') validateField(name); });
  });
  dateInput.addEventListener('change', function () { updateSlots(); validateField('delivery_date'); if (slotGroup.getAttribute('aria-invalid') === 'true') validateSlot(); });
  slotInputs.forEach(function (i) { i.addEventListener('change', validateSlot); });

  // ---------- Tanda visual: centang daun, bagian lengkap, langkah mekar ----------
  // Hanya tampilan; pesan galat tetap diatur validateField/validateSlot di atas.
  var sections = Array.prototype.slice.call(form.querySelectorAll('fieldset.co-card'));
  var payStep = document.querySelector('.co-step[data-step="3"]');
  function fieldOk(name) {
    var f = fields[name];
    return f.node.value.trim() !== '' && !f.test(f.node.value);
  }
  function refreshMarks() {
    Object.keys(fields).forEach(function (name) {
      var wrap = fields[name].node.parentNode;
      if (wrap.classList.contains('co-ctl')) wrap.classList.toggle('is-valid', fieldOk(name));
    });
    var needed = 0, done = 0;
    sections.forEach(function (sec) {
      var names = Object.keys(fields).filter(function (n) { return sec.contains(fields[n].node); });
      var hasSlot = sec.contains(slotGroup);
      var hasZone = sec.contains(zoneGroup);
      var required = hasSlot || hasZone || names.some(function (n) { return fields[n].node.required; });
      var ok = names.every(function (n) { return !fields[n].test(fields[n].node.value); });
      if (hasSlot) ok = ok && !!form.querySelector('input[name="delivery_slot"]:checked');
      if (hasZone) { var z = pickedZone(); ok = ok && !!z && z.fee !== null; }
      if (required) { needed++; if (ok) done++; }
      else ok = ok && names.some(fieldOk);
      sec.classList.toggle('is-complete', ok);
    });
    if (payStep && !payStep.classList.contains('is-current') && !payStep.classList.contains('is-done')) {
      payStep.style.setProperty('--fill', needed ? (done / needed).toFixed(3) : '0');
      payStep.classList.toggle('is-ready', needed > 0 && done === needed);
    }
  }
  form.addEventListener('input', refreshMarks);
  form.addEventListener('change', refreshMarks);

  // Kartu ucapan: sisa huruf
  var kartu = fields.card_message.node;
  function updateCount() {
    var left = 160 - kartu.value.length;
    kartuLeft.textContent = String(Math.max(0, left));
    $('f-kartu-count').classList.toggle('is-low', left <= 20);
  }
  kartu.addEventListener('input', updateCount);

  // Simpan isian yang juga ada di keranjang beranda
  [['kartu', kartu], ['penerima', fields.recipient_name.node], ['alamat', fields.address.node]].forEach(function (pair) {
    pair[1].addEventListener('input', function () {
      if (!state.cart) return;
      state.cart[pair[0]] = pair[1].value;
      saveCart();
    });
  });

  // ---------- Kirim pesanan ----------
  function setBusy(on) {
    state.busy = on;
    // form.elements juga memuat tombol bayar (atribut form=), jadi atur tombol itu sesudahnya.
    Array.prototype.forEach.call(form.elements, function (e) { e.disabled = on; });
    payBtn.classList.toggle('is-busy', on);
    payBtn.disabled = on || !state.ready;
    payBtn.setAttribute('aria-busy', on ? 'true' : 'false');
    form.setAttribute('aria-busy', on ? 'true' : 'false');
    if (on) {
      linesEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    } else {
      updateSlots();
      renderLines();
    }
  }

  function payload() {
    var picked = form.querySelector('input[name="delivery_slot"]:checked');
    return {
      customer_name: cleanText(fields.customer_name.node.value),
      customer_phone: fields.customer_phone.node.value.trim(),
      customer_email: fields.customer_email.node.value.trim(),
      recipient_name: cleanText(fields.recipient_name.node.value),
      recipient_phone: fields.recipient_phone.node.value.trim(),
      address: fields.address.node.value.trim(),
      delivery_date: dateInput.value,
      delivery_slot: picked ? picked.value : '',
      shipping_zone: pickedZone() ? pickedZone().code : '',
      card_message: kartu.value.trim(),
      items: cartIds().map(function (id) { return { id: id, qty: state.cart.items[id].qty }; })
    };
  }

  function waOrderText() {
    var t = 'Halo Kembang Subuh, saya mau pesan:\n';
    cartIds().forEach(function (id) {
      var p = state.products[id] || state.cart.items[id];
      t += '- ' + state.cart.items[id].qty + ' ' + p.name + '\n';
    });
    var z = pickedZone();
    if (z) t += 'Daerah antar: ' + z.label + '\n';
    return t;
  }

  function waNode(label, text) {
    var a = el('a', 'co-help');
    a.href = waLink(text);
    a.innerHTML = '<svg class="co-ico" aria-hidden="true"><use href="#wa"></use></svg>';
    a.appendChild(el('span', null, label));
    return a;
  }

  function handleConflict(items) {
    var msgs = [];
    (items || []).forEach(function (it) {
      var cur = state.cart.items[it.id];
      var name = (state.products[it.id] && state.products[it.id].name) || (cur && cur.name) || 'Satu produk';
      if (!cur) return;
      if (it.reason === 'stock' && it.stock > 0) {
        cur.qty = Math.min(cur.qty, it.stock);
        if (state.products[it.id]) state.products[it.id].stock = it.stock;
        msgs.push('Stok ' + name + ' tinggal ' + it.stock + ', jumlahnya kami sesuaikan.');
      } else {
        delete state.cart.items[it.id];
        delete state.products[it.id];
        msgs.push(name + ' sudah tidak tersedia, jadi kami keluarkan.');
      }
    });
    saveCart();
    if (!cartIds().length) { showEmpty(); }
    showNotice('Keranjangmu sudah kami sesuaikan. Cek lagi sebelum bayar, ya:', msgs);
    notice.scrollIntoView({ block: 'center', behavior: reduced.matches ? 'auto' : 'smooth' });
  }

  function goToOrder(code) {
    window.location.href = 'pesanan.html?kode=' + encodeURIComponent(code);
  }

  function setStep(n) {
    document.querySelectorAll('.co-step').forEach(function (s) {
      var i = Number(s.dataset.step);
      s.classList.toggle('is-done', i < n);
      s.classList.toggle('is-current', i === n);
      s.classList.remove('is-ready');
      if (i === n) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (state.busy || !state.ready) return;
    submitErr.textContent = '';
    var bad = validateAll();
    if (bad) {
      submitErr.textContent = 'Ada data yang belum lengkap. Cek kolom yang ditandai, ya.';
      bad.focus();
      return;
    }
    if (!cartIds().length) { showEmpty(); return; }

    var body = payload();
    setBusy(true);
    payBtn.querySelector('.co-pay-label').textContent = 'Menyiapkan pembayaran…';

    fetch('api/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (d) { return { status: r.status, data: d }; }); })
      .then(function (res) {
        var d = res.data || {};
        if (d.ok && d.order_code) {
          clearCart();
          state.cart = { items: {} };
          setStep(3);
          if (d.mode === 'midtrans') return startSnap(d);
          if (d.mode === 'demo') return openDemo(d);
          goToOrder(d.order_code);
          return;
        }
        setBusy(false);
        if (res.status === 422 && d.fields) {
          var first = null;
          Object.keys(d.fields).forEach(function (k) {
            setError(k, d.fields[k]);
            if (!first) first = k === 'delivery_slot' ? slotInputs[0] : (k === 'shipping_zone' ? zoneInputs()[0] : (fields[k] && fields[k].node));
          });
          submitErr.textContent = d.error || 'Ada data yang perlu dibetulkan.';
          if (first) first.focus();
          return;
        }
        if (res.status === 409) { handleConflict(d.items); renderLines(); return; }
        submitErr.textContent = d.error || 'Pesanan belum bisa dikirim. Coba lagi sebentar.';
        if (res.status === 503 || res.status === 502) {
          showNotice(d.error || 'Pembayaran online belum aktif.', null, waNode('Pesan via WhatsApp', waOrderText()));
        }
      })
      .catch(function () {
        setBusy(false);
        submitErr.textContent = 'Koneksi terputus. Periksa internetmu, lalu coba lagi.';
      });
  });

  // ---------- Midtrans Snap ----------
  function loadSnap(src, clientKey) {
    return new Promise(function (resolve, reject) {
      if (window.snap && typeof window.snap.pay === 'function') { resolve(window.snap); return; }
      var okHost = SNAP_HOSTS.some(function (h) { return String(src).indexOf(h) === 0; });
      if (!okHost) { reject(new Error('host')); return; }
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.setAttribute('data-client-key', clientKey);
      s.onload = function () { window.snap ? resolve(window.snap) : reject(new Error('snap')); };
      s.onerror = function () { reject(new Error('load')); };
      document.head.appendChild(s);
    });
  }

  function startSnap(d) {
    payBtn.querySelector('.co-pay-label').textContent = 'Membuka pembayaran…';
    return loadSnap(d.snap_js, d.client_key).then(function (snap) {
      var done = function () { goToOrder(d.order_code); };
      snap.pay(d.token, { onSuccess: done, onPending: done, onError: done, onClose: done });
    }).catch(function () {
      // Pesanan sudah tersimpan; halaman pesanan punya tombol "Lanjutkan pembayaran".
      goToOrder(d.order_code);
    });
  }

  // ---------- Simulasi pembayaran (mode uji) ----------
  var sheet = $('demo-sheet');
  var demoCode = '';
  var demoBusy = false;

  function openDemo(d) {
    demoCode = d.order_code;
    $('demo-code').textContent = d.order_code;
    $('demo-total').textContent = rupiah(d.total);
    payBtn.querySelector('.co-pay-label').textContent = 'Menunggu pembayaran…';
    if (typeof sheet.showModal === 'function') sheet.showModal();
    else { goToOrder(d.order_code); return; }
    $('demo-ok').focus();
  }

  function demoPay(result) {
    if (demoBusy) return;
    demoBusy = true;
    var btn = result === 'paid' ? $('demo-ok') : $('demo-fail');
    var methodInput = sheet.querySelector('input[name="demo-method"]:checked');
    $('demo-err').textContent = '';
    $('demo-ok').disabled = true;
    $('demo-fail').disabled = true;
    if (result === 'paid') btn.classList.add('is-busy');
    fetch('api/demo-pay.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ kode: demoCode, result: result, method: methodInput ? methodInput.value : 'qris' })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && (d.ok || d.error)) { goToOrder(demoCode); return; }
        throw new Error('bad');
      })
      .catch(function () {
        demoBusy = false;
        btn.classList.remove('is-busy');
        $('demo-ok').disabled = false;
        $('demo-fail').disabled = false;
        $('demo-err').textContent = 'Simulasi belum terkirim. Coba lagi.';
      });
  }

  $('demo-ok').addEventListener('click', function () { demoPay('paid'); });
  $('demo-fail').addEventListener('click', function () { demoPay('failed'); });
  $('demo-close').addEventListener('click', function () { sheet.close(); });
  sheet.addEventListener('close', function () { if (!demoBusy && demoCode) goToOrder(demoCode); });

  // ---------- Mulai ----------
  function prefill() {
    if (typeof state.cart.penerima === 'string') fields.recipient_name.node.value = state.cart.penerima.slice(0, 120);
    if (typeof state.cart.alamat === 'string') fields.address.node.value = state.cart.alamat.slice(0, 500);
    if (typeof state.cart.kartu === 'string') kartu.value = state.cart.kartu.slice(0, 160);
    updateCount();
  }

  function init() {
    state.cart = readCart();
    prefill();
    setupDate();
    refreshMarks();
    if (!cartIds().length) { showEmpty(); return; }

    loadProducts().then(function (d) {
      renderZones(d.shipping_zones);
      state.minDate = d.min_date || '';
      state.mode = d.payment_mode || 'demo';
      setupDate();
      refreshMarks();
      reconcile(Array.isArray(d.products) ? d.products : []);
      if (!cartIds().length) { showEmpty(); return; }
      state.ready = true;
      renderLines(cartIds());
      if (state.mode === 'off') {
        state.ready = false;
        showNotice('Pembayaran online belum aktif. Kamu tetap bisa pesan lewat WhatsApp, ya.', null, waNode('Kirim pesanan via WhatsApp', waOrderText()));
      }
      payBtn.disabled = !state.ready;
    }).catch(function () {
      linesEl.setAttribute('aria-busy', 'false');
      linesEl.textContent = '';
      var retry = el('button', 'btn co-btn-ghost', 'Muat ulang');
      retry.type = 'button';
      retry.addEventListener('click', function () { window.location.reload(); });
      showNotice('Harga terbaru belum bisa dimuat. Periksa koneksi lalu muat ulang halaman.', null, retry);
    });
  }

  init();
})();
