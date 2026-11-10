// Kembang Subuh: search and filters, cart drawer (saved for checkout), WhatsApp order, same-day cutoff, entrances.
(function () {
  var WA = '6281200000000';
  var CART_KEY = 'ks-cart-v1';
  var CUTOFF = 13 * 60;
  var OPEN = 7 * 60;
  var floral = window.ksFloral || { burst: function () {}, pop: function () {} };

  function rupiah(n) { return 'Rp ' + n.toLocaleString('id-ID'); }
  function waLink(text) { return 'https://wa.me/' + WA + '?text=' + encodeURIComponent(text); }

  // Filters and search
  var cards = Array.prototype.slice.call(document.querySelectorAll('.card'));
  var pills = document.querySelectorAll('.pill');
  var countEl = document.getElementById('result-count');
  var emptyEl = document.getElementById('empty');
  var searchEl = document.getElementById('search');
  var activeCat = 'semua';

  function applyFilter() {
    var q = searchEl.value.trim().toLowerCase();
    var shown = 0;
    cards.forEach(function (card) {
      var inCat = activeCat === 'semua' || card.dataset.cat === activeCat;
      var text = (card.dataset.name + ' ' + card.dataset.cat + ' ' + card.querySelector('.card-note').textContent).toLowerCase();
      var match = inCat && (!q || text.indexOf(q) !== -1);
      card.hidden = !match;
      if (match) { shown += 1; card.classList.add('in'); }
    });
    countEl.textContent = shown + ' produk';
    emptyEl.hidden = shown !== 0;
    pills.forEach(function (p) { p.setAttribute('aria-pressed', p.dataset.filter === activeCat ? 'true' : 'false'); });
  }
  // Filtering in place (pills, search, the empty-state reset) runs inside a same-document View Transition:
  // every visible card gets its own view-transition-name, so cards glide to their new spots while the rest
  // of the page swaps instantly. No API, reduced motion, or the grid off screen: plain applyFilter().
  var reduceMQ = window.matchMedia('(prefers-reduced-motion: reduce)');
  var gridEl = document.getElementById('grid');
  var activeVT = null;
  function nameCards() {
    cards.forEach(function (c) { c.style.viewTransitionName = c.hidden ? '' : 'ks-card-' + c.dataset.id; });
  }
  function filterSmoothly() {
    var r = gridEl.getBoundingClientRect();
    if (!document.startViewTransition || reduceMQ.matches || r.bottom < 0 || r.top > window.innerHeight) { applyFilter(); return; }
    var root = document.documentElement;
    nameCards();
    root.classList.add('ks-filtering');
    var vt = document.startViewTransition(function () { applyFilter(); nameCards(); });
    activeVT = vt;
    var done = function () {
      if (activeVT !== vt) return; // a newer transition has taken over
      activeVT = null;
      root.classList.remove('ks-filtering');
      cards.forEach(function (c) { c.style.viewTransitionName = ''; });
    };
    vt.finished.then(done, done);
  }
  document.querySelectorAll('[data-filter]').forEach(function (el) {
    el.addEventListener('click', function () {
      activeCat = el.dataset.filter;
      searchEl.value = '';
      clearTimeout(typingTimer); // a pending search filter would undo this choice
      // Links (header, categories, footer) also scroll to #koleksi, so they skip the transition.
      if (el.tagName === 'BUTTON') filterSmoothly(); else applyFilter();
      // The reset button in the empty state disappears: keep focus on the matching pill.
      if (emptyEl.contains(el)) {
        var pill = document.querySelector('.pill[data-filter="semua"]');
        if (pill) pill.focus();
      }
    });
  });
  // Typing: wait for a short pause before starting the glide, so fast typing runs one transition
  // instead of skipping a new one in on every key (which made the cards jump). No glide: filter at once.
  var typingTimer = null;
  searchEl.addEventListener('input', function () {
    activeCat = 'semua';
    clearTimeout(typingTimer);
    if (!document.startViewTransition || reduceMQ.matches) { applyFilter(); return; }
    typingTimer = setTimeout(filterSmoothly, 140);
  });
  document.getElementById('search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    document.getElementById('koleksi').scrollIntoView();
  });

  // Cart
  var cart = {};
  var drawer = document.getElementById('keranjang');
  var linesEl = document.getElementById('lines');
  var linesEmpty = document.getElementById('lines-empty');
  var subtotalEl = document.getElementById('subtotal');
  var sendEl = document.getElementById('send');
  var payEl = document.getElementById('pay');
  var countBadge = document.getElementById('cart-count');
  var cartOpen = document.getElementById('cart-open');
  var toastEl = document.getElementById('toast');
  var fields = {
    kartu: document.getElementById('kartu'),
    penerima: document.getElementById('penerima'),
    alamat: document.getElementById('alamat')
  };
  var toastTimer = null;

  // The cart lives in localStorage so checkout.html can read it and a reload keeps it.
  // Shape: {"items":{"<productId>":{"name":"...","price":295000,"qty":1}},"kartu":"","penerima":"","alamat":""}
  function saveCart() {
    var data = { items: cart, kartu: fields.kartu.value, penerima: fields.penerima.value, alamat: fields.alamat.value };
    try { localStorage.setItem(CART_KEY, JSON.stringify(data)); } catch (e) { /* storage blocked: the cart lasts for this page only */ }
  }
  function loadCart() {
    var data = null;
    try { data = JSON.parse(localStorage.getItem(CART_KEY) || 'null'); } catch (e) { data = null; }
    cart = {};
    if (!data || typeof data !== 'object') {
      // Cleared elsewhere (for example checkout after a paid order): clear the card and address too,
      // otherwise the next save would write them back.
      Object.keys(fields).forEach(function (key) { fields[key].value = ''; });
      return;
    }
    var items = data.items && typeof data.items === 'object' ? data.items : {};
    Object.keys(items).forEach(function (id) {
      // Keep only products on this page; name and price always come from the page itself.
      var card = cards.filter(function (c) { return c.dataset.id === id; })[0];
      var qty = parseInt(items[id] && items[id].qty, 10);
      if (!card || !(qty > 0)) return;
      cart[id] = { name: card.dataset.name, price: parseInt(card.dataset.price, 10), qty: Math.min(qty, 99) };
    });
    Object.keys(fields).forEach(function (key) {
      if (typeof data[key] === 'string') fields[key].value = data[key].slice(0, key === 'kartu' ? 160 : 200);
    });
  }

  function toast(text) {
    // The live region stays in the page (never [hidden]) so screen readers announce every message.
    toastEl.textContent = text;
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2400);
  }

  function renderCart() {
    var ids = Object.keys(cart);
    var total = 0;
    var count = 0;
    var text = 'Halo Kembang Subuh, saya mau pesan:\n';
    linesEl.textContent = '';
    ids.forEach(function (id) {
      var item = cart[id];
      var line = item.qty * item.price;
      total += line;
      count += item.qty;
      text += '- ' + item.qty + ' ' + item.name + ' (' + rupiah(line) + ')\n';

      var li = document.createElement('li');
      li.className = 'line';
      li.dataset.id = id;
      var name = document.createElement('span');
      name.className = 'line-name';
      name.textContent = item.name;
      var price = document.createElement('span');
      price.className = 'line-price';
      price.textContent = rupiah(line);
      var stepper = document.createElement('span');
      stepper.className = 'stepper';
      [['-1', '−', 'Kurangi '], ['0', '', ''], ['1', '+', 'Tambah ']].forEach(function (part) {
        if (part[0] === '0') {
          var out = document.createElement('output');
          out.textContent = item.qty;
          out.setAttribute('aria-label', 'Jumlah ' + item.name);
          stepper.appendChild(out);
          return;
        }
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = part[1];
        b.dataset.id = id;
        b.dataset.step = part[0];
        b.setAttribute('aria-label', part[2] + item.name);
        stepper.appendChild(b);
      });
      li.appendChild(name);
      li.appendChild(price);
      li.appendChild(stepper);
      linesEl.appendChild(li);
    });
    linesEmpty.hidden = ids.length > 0;
    subtotalEl.textContent = rupiah(total);
    countBadge.textContent = count;
    countBadge.hidden = count === 0;
    cartOpen.setAttribute('aria-label', count ? 'Buka keranjang, ' + count + ' barang' : 'Buka keranjang');

    text += 'Subtotal ' + rupiah(total) + ' (belum termasuk ongkos antar).';
    if (fields.kartu.value.trim()) text += '\nTulisan di kartu: ' + fields.kartu.value.trim();
    if (fields.penerima.value.trim()) text += '\nPenerima: ' + fields.penerima.value.trim();
    if (fields.alamat.value.trim()) text += '\nAlamat: ' + fields.alamat.value.trim();
    sendEl.href = waLink(text);
    [payEl, sendEl].forEach(function (el) {
      el.classList.toggle('is-off', !ids.length);
      el.setAttribute('aria-disabled', ids.length ? 'false' : 'true');
    });
    saveCart();
  }

  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var card = btn.closest('.card');
      var id = card.dataset.id;
      if (!cart[id]) cart[id] = { name: card.dataset.name, price: parseInt(card.dataset.price, 10), qty: 0 };
      cart[id].qty = Math.min(cart[id].qty + 1, 99);
      renderCart();
      floral.burst(btn);
      floral.pop(countBadge);
      toast(card.dataset.name + ' masuk keranjang');
    });
  });
  // Line motion inside the open drawer: a removed line slides out, the lines below glide up (FLIP),
  // and the changed quantity pops. Transform and opacity only; nothing with reduced motion.
  function lineMotion() { return drawer.open && !reduceMQ.matches && !!document.body.animate; }
  function renderCartFlip() {
    var before = {};
    var moving = lineMotion();
    if (moving) linesEl.querySelectorAll('.line').forEach(function (li) { before[li.dataset.id] = li.getBoundingClientRect().top; });
    renderCart();
    if (!moving) return;
    linesEl.querySelectorAll('.line').forEach(function (li) {
      var top = before[li.dataset.id];
      if (top === undefined) return;
      var dy = top - li.getBoundingClientRect().top;
      if (Math.abs(dy) < 1) return;
      li.animate([{ transform: 'translate3d(0, ' + dy + 'px, 0)' }, { transform: 'none' }], { duration: 300, easing: 'cubic-bezier(.2, .8, .2, 1)' });
    });
  }
  linesEl.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]');
    if (!b) return;
    var id = b.dataset.id;
    var step = parseInt(b.dataset.step, 10);
    if (!cart[id]) return;
    cart[id].qty = Math.min(cart[id].qty + step, 99);
    var after = function () {
      if (step > 0) floral.pop(countBadge);
      var out = linesEl.querySelector('.line[data-id="' + id + '"] output');
      if (out && lineMotion()) out.classList.add('is-bump');
      var again = linesEl.querySelector('button[data-id="' + id + '"][data-step="' + step + '"]');
      (again || document.getElementById('cart-close')).focus();
    };
    if (cart[id].qty <= 0) {
      delete cart[id];
      var gone = b.closest('.line');
      if (gone && lineMotion()) {
        gone.style.pointerEvents = 'none';
        var anim = gone.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translate3d(24px, 0, 0)' }], { duration: 200, easing: 'ease-in', fill: 'forwards' });
        // If another click re-rendered the list meanwhile, this line is already gone: do nothing.
        anim.onfinish = anim.oncancel = function () { if (!gone.isConnected) return; renderCartFlip(); after(); };
        return;
      }
    }
    renderCartFlip();
    after();
  });
  Object.keys(fields).forEach(function (key) { fields[key].addEventListener('input', renderCart); });
  [payEl, sendEl].forEach(function (el) {
    el.addEventListener('click', function (e) {
      // Empty cart: the "Keranjang masih kosong" note is already shown above the buttons.
      if (el.classList.contains('is-off')) e.preventDefault();
      else saveCart();
    });
  });
  // Another tab (for example checkout after a payment) changed the cart: follow it.
  window.addEventListener('storage', function (e) {
    if (e.key !== CART_KEY) return;
    loadCart();
    renderCart();
  });

  // Drawer: slides in from the right (CSS) and slides out again before it closes.
  var closing = false;
  function closeDrawer() {
    if (!drawer.open || closing) return;
    closing = true;
    var finish = function () {
      if (!closing) return;
      closing = false;
      drawer.classList.remove('is-closing');
      drawer.close();
    };
    var onEnd = function (e) {
      if (e.target !== drawer) return;
      drawer.removeEventListener('animationend', onEnd);
      finish();
    };
    drawer.addEventListener('animationend', onEnd);
    drawer.classList.add('is-closing');
    setTimeout(function () { drawer.removeEventListener('animationend', onEnd); finish(); }, 400);
  }
  cartOpen.addEventListener('click', function (e) {
    e.preventDefault();
    if (drawer.open) return;
    // Lines slide in one after another as the drawer opens (CSS, motion allowed only).
    linesEl.querySelectorAll('.line').forEach(function (li, i) { li.classList.add('is-new'); li.style.setProperty('--i', i); });
    drawer.showModal();
  });
  document.getElementById('cart-close').addEventListener('click', closeDrawer);
  drawer.addEventListener('click', function (e) { if (e.target === drawer) closeDrawer(); });
  drawer.addEventListener('cancel', function (e) { e.preventDefault(); closeDrawer(); });

  // The same-day cutoff, always in Jakarta time.
  function jakartaMinutes() {
    var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
    var h = 0, m = 0;
    parts.forEach(function (p) {
      if (p.type === 'hour') h = parseInt(p.value, 10) % 24;
      if (p.type === 'minute') m = parseInt(p.value, 10);
    });
    return h * 60 + m;
  }
  function cutoffText() {
    var now = jakartaMinutes();
    if (now < OPEN) return 'Pesan pagi ini, sampai sore nanti.';
    if (now >= CUTOFF) return 'Pesanan hari ini sudah ditutup. Pesan sekarang, sampai besok sore.';
    var left = CUTOFF - now;
    var h = Math.floor(left / 60);
    var m = left % 60;
    return 'Pesan dalam ' + ((h ? h + ' jam ' : '') + (m ? m + ' menit' : '')).trim() + ' lagi, sampai sore ini juga.';
  }
  function paintCutoff() {
    var text = cutoffText();
    document.querySelectorAll('[data-batas]').forEach(function (el) { el.textContent = text; });
  }
  paintCutoff();
  setInterval(paintCutoff, 30000);

  // Cards and blocks bloom open as they come into view, row by row.
  // With reduced motion the CSS turns this into a short fade with no scaling.
  cards.forEach(function (card, i) { card.style.setProperty('--d', (i % 4) * 60 + 'ms'); });
  var parts = Array.prototype.slice.call(document.querySelectorAll('.cat-row li, .usp-row li, .trust, .cara-head, .cara-row li, .ongkir li, .antar-scene, .tanya-list details, .tile, .antar-photo, main > .divider'));
  parts.forEach(function (el, i) {
    el.setAttribute('data-rise', '');
    el.style.setProperty('--d', (i % 4) * 60 + 'ms');
  });
  var risers = cards.concat(parts);
  if (!('IntersectionObserver' in window)) {
    risers.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        // Reveal what enters the view, and anything already above it after an anchor jump.
        if (entry.isIntersecting || entry.boundingClientRect.bottom < 0) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -5% 0px' });
    risers.forEach(function (el) { io.observe(el); });
  }
  // Tells the head failsafe that the reveal is running, so hidden cards will be shown.
  document.documentElement.classList.add('ks-ready');

  // "Ongkos antar": sync the static list with the zones managed in the dashboard (api/products.php).
  // The markup in index.html stays as the no-JS fallback; any error leaves it untouched.
  (function syncOngkir() {
    var list = document.querySelector('.ongkir');
    if (!list || !window.fetch) return;
    var paid = list.querySelector('.pin .f-bloom');
    var ask = list.querySelector('.pin .f-leaf');
    var tplPaid = paid ? paid.closest('li') : null;
    var tplAsk = ask ? ask.closest('li') : null;
    if (!tplPaid && !tplAsk) return;
    fetch('api/products.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        var zones = data && data.ok && Array.isArray(data.shipping_zones) ? data.shipping_zones : null;
        if (!zones || !zones.length) return;
        var shown = list.querySelector('li.in') !== null;
        var items = [];
        for (var i = 0; i < zones.length; i++) {
          var z = zones[i];
          if (!z || typeof z.label !== 'string' || !z.label) return;
          var askFirst = z.fee === null || z.fee === undefined;
          if (!askFirst && (typeof z.fee !== 'number' || !isFinite(z.fee))) return;
          var tpl = askFirst ? (tplAsk || tplPaid) : (tplPaid || tplAsk);
          var li = tpl.cloneNode(true);
          var span = li.querySelector('span');
          var svg = span && span.querySelector('svg');
          var b = li.querySelector('b');
          if (!span || !svg || !b) return;
          var mark = svg.querySelector('.f-bloom, .f-leaf');
          if (mark) mark.setAttribute('class', askFirst ? 'f-leaf' : 'f-bloom');
          while (span.lastChild && span.lastChild !== svg) span.removeChild(span.lastChild);
          span.appendChild(document.createTextNode(z.label));
          b.textContent = askFirst ? 'Tanya dulu' : (z.fee === 0 ? 'Gratis' : rupiah(z.fee));
          li.classList.remove('in');
          li.setAttribute('data-rise', '');
          li.style.setProperty('--d', (i % 4) * 60 + 'ms');
          items.push(li);
        }
        var frag = document.createDocumentFragment();
        items.forEach(function (li) {
          if (shown || !io) li.classList.add('in');
          frag.appendChild(li);
        });
        Array.prototype.slice.call(list.children).forEach(function (old) { if (io) io.unobserve(old); });
        list.textContent = '';
        list.appendChild(frag);
        if (!shown && io) items.forEach(function (li) { io.observe(li); });
      })
      .catch(function () { /* keep the static list */ });
  })();

  loadCart();
  renderCart();
})();
