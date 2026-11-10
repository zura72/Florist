// Kembang Subuh — halaman status pesanan: cek status tiap 4 detik (maks. 2 menit),
// tampilkan bunga mekar (lunas), kuncup bergoyang (menunggu), atau bunga layu (gagal).
(function () {
  'use strict';

  var WA = '6281200000000';
  var POLL_MS = 4000;
  var POLL_LIMIT_MS = 2 * 60 * 1000;
  var SNAP_HOSTS = ['https://app.sandbox.midtrans.com/', 'https://app.midtrans.com/'];
  var FINAL = { paid: 1, failed: 1, expired: 1, refunded: 1 };

  var $ = function (id) { return document.getElementById(id); };
  var code = new URLSearchParams(window.location.search).get('kode') || '';
  var card = $('ps-card');
  var live = $('ps-live');
  var startedAt = Date.now();
  var timer = null;
  var lastStatus = '';
  var lastData = null;
  var busy = false;
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var bursted = false;

  function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
  function formatDate(ymd) {
    try {
      return new Date(ymd + 'T00:00:00Z').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    } catch (e) { return ymd; }
  }
  function show(id, on) { $(id).hidden = !on; }
  function setWa(text) { $('ps-wa').href = 'https://wa.me/' + WA + '?text=' + encodeURIComponent(text); }

  function setArt(kind) {
    ['paid', 'pending', 'failed'].forEach(function (k) {
      var art = $('art-' + k);
      var on = k === kind;
      // SVG elements have no .hidden property, so use the attribute directly.
      if (on && art.hasAttribute('hidden')) {
        art.removeAttribute('hidden');
        if (k !== 'pending') { art.classList.remove('is-play'); void art.getBoundingClientRect(); art.classList.add('is-play'); }
        if (k === 'paid') burst();
      } else if (!on) {
        art.setAttribute('hidden', '');
      }
    });
    $('ps-skel').hidden = true;
  }

  // Hujan kelopak sekali di sekitar bunga mekar (maks. 16 kelopak, WAAPI transform/opacity, mati saat kurangi gerakan).
  function burst() {
    var layer = $('ps-burst');
    if (bursted || reduced.matches || !layer || typeof layer.animate !== 'function') return;
    bursted = true;
    var N = window.innerWidth < 480 ? 12 : 16;
    var tones = ['c1', 'c2', 'c3', 'c4'];
    for (var i = 0; i < N; i++) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'ps-confetti ' + tones[i % tones.length]);
      svg.setAttribute('viewBox', '0 0 14 18');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', '#confetti-petal');
      svg.appendChild(use);
      layer.appendChild(svg);
      var ang = (i / N) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
      var dist = 70 + Math.random() * 70;
      var dx = Math.cos(ang) * dist;
      var dy = Math.sin(ang) * dist * 0.8;
      var rot = (Math.random() - 0.5) * 540;
      var sc = 0.7 + Math.random() * 0.5;
      var a = svg.animate([
        { transform: 'translate(0,0) rotate(0deg) scale(.3)', opacity: 0 },
        { transform: 'translate(' + (dx * 0.7).toFixed(1) + 'px,' + (dy * 0.7 - 18).toFixed(1) + 'px) rotate(' + (rot * 0.6).toFixed(0) + 'deg) scale(' + sc.toFixed(2) + ')', opacity: 1, offset: 0.45 },
        { transform: 'translate(' + dx.toFixed(1) + 'px,' + (dy + 46).toFixed(1) + 'px) rotate(' + rot.toFixed(0) + 'deg) scale(' + sc.toFixed(2) + ')', opacity: 0 }
      ], { duration: 1300 + Math.random() * 500, delay: 380 + Math.random() * 160, easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'backwards' });
      a.onfinish = (function (node) { return function () { node.remove(); }; })(svg);
    }
  }

  // Linimasa: Dibayar, Dirangkai, Diantar, Sampai. done = langkah selesai; langkah berikutnya = sedang berjalan.
  var TRACK_DONE = { baru: 1, dirangkai: 1, diantar: 2, selesai: 4 };
  function renderTrack(d, st) {
    var track = $('ps-track');
    var showIt = (st === 'pending' || st === 'paid') && d.order_status !== 'batal';
    track.hidden = !showIt;
    if (!showIt) return;
    var done = st === 'paid' ? (TRACK_DONE[d.order_status] || 1) : 0;
    Array.prototype.forEach.call(track.children, function (li, i) {
      var isReached = i < done;
      var isNow = i === done;
      li.classList.toggle('is-reached', isReached);
      li.classList.toggle('is-now', isNow);
      if (isNow) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
      li.querySelector('.ps-tk-sr').textContent = isNow ? 'Sedang: ' : (isReached ? 'Selesai: ' : 'Belum: ');
    });
  }

  var COPY = {
    pending: {
      title: 'Menunggu pembayaran',
      lead: function (d) { return 'Selesaikan pembayaran ' + rupiah(d.total) + ' supaya bunga untuk ' + (d.recipient || 'penerima') + ' bisa segera kami rangkai.'; }
    },
    paid: {
      title: 'Pembayaran berhasil. Terima kasih!',
      lead: function (d) {
        var steps = { baru: 'sedang kami siapkan', dirangkai: 'sedang dirangkai', diantar: 'sedang dalam perjalanan', selesai: 'sudah sampai', batal: 'dibatalkan. Tim kami akan menghubungimu' };
        return 'Bunga untuk ' + (d.recipient || 'penerima') + ' ' + (steps[d.order_status] || 'sedang kami siapkan') + '. Kami kirim foto buket lewat WhatsApp sebelum diantar.';
      }
    },
    failed: {
      title: 'Pembayaran belum berhasil',
      lead: function () { return 'Tidak ada dana yang terpotong. Kamu bisa pesan ulang, atau chat kami kalau butuh bantuan.'; }
    },
    expired: {
      title: 'Waktu pembayaran habis',
      lead: function () { return 'Batas waktu bayar sudah lewat, jadi pesanan ini kami tutup. Yuk, pesan ulang. Bunganya masih ada.'; }
    },
    refunded: {
      title: 'Dana sudah dikembalikan',
      lead: function () { return 'Pengembalian dana untuk pesanan ini sudah diproses. Waktu masuknya mengikuti bank atau e-wallet yang kamu pakai.'; }
    }
  };

  function renderSummary(d) {
    var ul = $('ps-items');
    ul.textContent = '';
    (d.items || []).forEach(function (it) {
      var li = document.createElement('li');
      var a = document.createElement('span'); a.textContent = it.name;
      var b = document.createElement('span'); b.textContent = '× ' + it.qty;
      li.appendChild(a); li.appendChild(b);
      ul.appendChild(li);
    });
    $('ps-recipient').textContent = d.recipient || '–';
    $('ps-when').textContent = formatDate(d.delivery_date) + ', ' + (d.delivery_slot || '');
    $('ps-sub').textContent = rupiah(d.subtotal);
    $('ps-ship').textContent = rupiah(d.shipping_fee) + (d.shipping_zone ? ' · ' + d.shipping_zone : '');
    $('ps-total').textContent = rupiah(d.total);
    show('ps-sum', true);
  }

  function render(d) {
    lastData = d;
    var st = COPY[d.payment_status] ? d.payment_status : 'pending';
    var changed = st !== lastStatus;
    var copy = COPY[st];
    $('ps-title').textContent = copy.title;
    $('ps-lead').textContent = copy.lead(d);
    $('ps-code-val').textContent = d.order_code;
    show('ps-code', true);
    card.setAttribute('aria-busy', 'false');
    document.title = copy.title + ' | Kembang Subuh';

    setArt(st === 'paid' ? 'paid' : (st === 'pending' ? 'pending' : 'failed'));

    var pay = st === 'pending' ? d.pay : null;
    show('ps-pay', !!(pay && pay.mode === 'midtrans'));
    show('ps-demo-ok', !!(pay && pay.mode === 'demo'));
    show('ps-demo-fail', !!(pay && pay.mode === 'demo'));
    show('ps-demo-badge', !!(pay && pay.mode === 'demo'));
    show('ps-shop', st === 'failed' || st === 'expired' || st === 'paid' || st === 'refunded');
    $('ps-shop').textContent = st === 'paid' ? 'Belanja lagi' : 'Pesan ulang';
    setWa('Halo Kembang Subuh, saya butuh bantuan untuk pesanan ' + d.order_code + '.');
    renderSummary(d);
    renderTrack(d, st);

    if (changed) {
      if (lastStatus && !reduced.matches) { card.classList.remove('is-swap'); void card.offsetWidth; card.classList.add('is-swap'); }
      live.textContent = copy.title + '. ' + copy.lead(d);
      lastStatus = st;
    }
  }

  function renderError(title, lead) {
    card.setAttribute('aria-busy', 'false');
    $('ps-title').textContent = title;
    $('ps-lead').textContent = lead;
    setArt('failed');
    show('ps-shop', true);
    $('ps-shop').textContent = 'Kembali ke toko';
    show('ps-poll', false);
    live.textContent = title + '. ' + lead;
  }

  function stopPolling(showRecheck) {
    clearTimeout(timer);
    timer = null;
    show('ps-poll', false);
    show('ps-recheck', !!showRecheck);
  }

  function schedule() {
    clearTimeout(timer);
    if (Date.now() - startedAt >= POLL_LIMIT_MS) { stopPolling(true); return; }
    show('ps-poll', true);
    show('ps-recheck', false);
    timer = setTimeout(fetchStatus, POLL_MS);
  }

  function fetchStatus() {
    return fetch('api/order-status.php?kode=' + encodeURIComponent(code), { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, data: d }; }); })
      .then(function (res) {
        var d = res.data || {};
        if (res.status === 404) { stopPolling(false); renderError('Pesanan tidak ditemukan', 'Periksa lagi tautanmu, atau chat kami dengan menyebutkan kode pesanan.'); return; }
        if (!d.ok) { schedule(); return; }
        render(d);
        if (FINAL[d.payment_status]) stopPolling(false); else schedule();
      })
      .catch(function () {
        $('ps-err').textContent = lastData ? '' : 'Koneksi terputus. Kami coba lagi otomatis.';
        schedule();
      });
  }

  function restartPolling() {
    startedAt = Date.now();
    $('ps-err').textContent = '';
    fetchStatus();
  }

  // ---------- Lanjutkan pembayaran Midtrans ----------
  function loadSnap(src, clientKey) {
    return new Promise(function (resolve, reject) {
      if (window.snap && typeof window.snap.pay === 'function') { resolve(window.snap); return; }
      if (!SNAP_HOSTS.some(function (h) { return String(src).indexOf(h) === 0; })) { reject(new Error('host')); return; }
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.setAttribute('data-client-key', clientKey);
      s.onload = function () { window.snap ? resolve(window.snap) : reject(new Error('snap')); };
      s.onerror = function () { reject(new Error('load')); };
      document.head.appendChild(s);
    });
  }

  function setBtnBusy(btn, on) {
    busy = on;
    btn.classList.toggle('is-busy', on);
    btn.disabled = on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  $('ps-pay').addEventListener('click', function () {
    var pay = lastData && lastData.pay;
    if (busy || !pay || pay.mode !== 'midtrans') return;
    var btn = $('ps-pay');
    setBtnBusy(btn, true);
    loadSnap(pay.snap_js, pay.client_key).then(function (snap) {
      var after = function () { setBtnBusy(btn, false); restartPolling(); };
      snap.pay(pay.token, { onSuccess: after, onPending: after, onError: after, onClose: after });
    }).catch(function () {
      setBtnBusy(btn, false);
      $('ps-err').textContent = 'Halaman pembayaran belum bisa dibuka. Coba lagi atau chat kami.';
    });
  });

  // ---------- Simulasi (mode uji) ----------
  function demo(result) {
    if (busy) return;
    var btn = result === 'paid' ? $('ps-demo-ok') : $('ps-demo-fail');
    setBtnBusy(btn, true);
    fetch('api/demo-pay.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ kode: code, result: result, method: 'qris' })
    }).then(function (r) { return r.json(); })
      .then(function () { setBtnBusy(btn, false); restartPolling(); })
      .catch(function () { setBtnBusy(btn, false); $('ps-err').textContent = 'Simulasi belum terkirim. Coba lagi.'; });
  }
  $('ps-demo-ok').addEventListener('click', function () { demo('paid'); });
  $('ps-demo-fail').addEventListener('click', function () { demo('failed'); });
  $('ps-recheck').addEventListener('click', restartPolling);

  // ---------- Mulai ----------
  if (!/^KS-\d{8}-[0-9A-F]{8}$/.test(code)) {
    renderError('Kode pesanan tidak dikenali', 'Buka halaman ini dari tautan setelah checkout, atau chat kami dengan menyebutkan kode pesananmu.');
    return;
  }
  setWa('Halo Kembang Subuh, saya butuh bantuan untuk pesanan ' + code + '.');
  fetchStatus();
})();
