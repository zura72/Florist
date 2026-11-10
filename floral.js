// Kembang Subuh floral layer: photo skeletons, hero petals, card petals, add-to-cart burst,
// press ripple and the header on scroll. Decorative only: the shop works the same without it.
(function () {
  var SVGNS = 'http://www.w3.org/2000/svg';
  var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
  var COLORS = ['f-petal-mid', 'f-bloom', 'f-petal'];
  var EASE = 'cubic-bezier(.2, .8, .2, 1)';

  function reduced() { return mq.matches; }
  function onMotionChange(fn) {
    if (mq.addEventListener) mq.addEventListener('change', fn);
    else if (mq.addListener) mq.addListener(fn);
  }
  function symbolSvg(cls, viewBox, id) {
    var svg = document.createElementNS(SVGNS, 'svg');
    svg.setAttribute('class', cls);
    svg.setAttribute('viewBox', viewBox);
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    var use = document.createElementNS(SVGNS, 'use');
    use.setAttribute('href', id);
    svg.appendChild(use);
    return svg;
  }
  function petal(color) { return symbolSvg('petal' + (color ? ' ' + color : ''), '0 0 20 24', '#fl-petal'); }

  // Photo skeletons: rose shimmer until each product or category photo has loaded.
  document.querySelectorAll('.card-photo, .cat-photo').forEach(function (box) {
    var img = box.querySelector('img');
    if (!img || img.complete) return;
    box.classList.add('is-loading');
    var done = function () { box.classList.remove('is-loading'); };
    img.addEventListener('load', done, { once: true });
    img.addEventListener('error', done, { once: true });
  });

  // Product cards: three petals waiting in the photo corner, drifting out on hover (CSS).
  if (window.matchMedia('(hover: hover)').matches) {
    document.querySelectorAll('.card-photo').forEach(function (box) {
      var wrap = document.createElement('span');
      wrap.className = 'card-petals';
      wrap.setAttribute('aria-hidden', 'true');
      for (var i = 0; i < 3; i++) wrap.appendChild(petal());
      box.appendChild(wrap);
    });
  }

  // Hero: a few petals fall slowly. Paused when the tab is hidden or the hero is off screen.
  var hero = document.querySelector('main > .hero');
  var layer = null;
  var heroInView = true;
  function sizeLayer() {
    if (layer) layer.style.setProperty('--fall-h', (hero.offsetHeight + 48) + 'px');
  }
  function syncPause() {
    if (layer) layer.classList.toggle('is-paused', document.hidden || !heroInView);
  }
  function buildPetals() {
    if (!hero || layer || reduced()) return;
    layer = document.createElement('div');
    layer.className = 'hero-petals';
    layer.setAttribute('aria-hidden', 'true');
    var count = window.innerWidth < 768 ? 6 : 10;
    for (var i = 0; i < count; i++) {
      var fall = document.createElement('span');
      var dur = 9 + Math.random() * 6;
      fall.className = 'fall';
      fall.style.setProperty('--x', (3 + Math.random() * 94).toFixed(1) + '%');
      fall.style.setProperty('--dur', dur.toFixed(2) + 's');
      fall.style.setProperty('--delay', (-Math.random() * dur).toFixed(2) + 's');
      fall.style.setProperty('--drift', Math.round(10 + Math.random() * 22) + 'px');
      fall.style.setProperty('--s', (0.7 + Math.random() * 0.6).toFixed(2));
      fall.appendChild(petal(COLORS[i % COLORS.length]));
      layer.appendChild(fall);
    }
    sizeLayer();
    hero.appendChild(layer);
    syncPause();
  }
  function dropPetals() {
    if (layer) { layer.remove(); layer = null; }
  }
  if (hero) {
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        heroInView = entries[entries.length - 1].isIntersecting;
        syncPause();
      }).observe(hero);
    }
    document.addEventListener('visibilitychange', syncPause);
    var sizing = false;
    window.addEventListener('resize', function () {
      if (sizing) return;
      sizing = true;
      requestAnimationFrame(function () { sizing = false; sizeLayer(); });
    });
    // Start after the page has loaded so the petals never compete with the hero photo.
    if (document.readyState === 'complete') buildPetals();
    else window.addEventListener('load', buildPetals);
    onMotionChange(function () { if (reduced()) dropPetals(); else buildPetals(); });
  }

  // Header: tucks up slightly with a shadow once the promo bar has scrolled away.
  var site = document.querySelector('header.site');
  var promo = document.querySelector('.promo');
  var scrolled = false;
  var ticking = false;
  function checkHeader() {
    ticking = false;
    var on = window.scrollY > (promo ? promo.offsetHeight : 0) + 24;
    if (on === scrolled) return;
    scrolled = on;
    site.classList.toggle('is-scrolled', on);
  }
  if (site) {
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(checkHeader); }
    }, { passive: true });
    checkHeader();
  }

  // Buttons: a soft blossom opens where the button is pressed.
  document.addEventListener('pointerdown', function (e) {
    if (reduced() || e.button !== 0 || !e.target.closest) return;
    var btn = e.target.closest('.btn');
    if (!btn || btn.classList.contains('is-off')) return;
    var r = btn.getBoundingClientRect();
    var bloom = symbolSvg('ripple', '-12 -12 24 24', '#fl-bloom-solid');
    if (!bloom.animate) return;
    bloom.style.left = (e.clientX - r.left) + 'px';
    bloom.style.top = (e.clientY - r.top) + 'px';
    bloom.style.setProperty('--r', Math.round(Math.max(r.width, r.height) * 1.6) + 'px');
    btn.appendChild(bloom);
    var anim = bloom.animate([
      { transform: 'scale(.1) rotate(0deg)', opacity: 0.3 },
      { transform: 'scale(1) rotate(50deg)', opacity: 0 }
    ], { duration: 600, easing: EASE });
    anim.onfinish = anim.oncancel = function () { bloom.remove(); };
  }, { passive: true });

  // Looping decorations (butterfly, delivery van, footer garland) only move while on screen.
  var hasIO = 'IntersectionObserver' in window;
  var live = document.querySelectorAll('[data-live]');
  if (hasIO) {
    var liveIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { e.target.classList.toggle('is-live', e.isIntersecting); });
    });
    live.forEach(function (el) { liveIO.observe(el); });
  } else {
    live.forEach(function (el) { el.classList.add('is-live'); });
  }

  // Section headings: the sprig beside each title unfurls once it scrolls into view (CSS does the motion).
  var sprigs = document.querySelectorAll('.h-sprig');
  if (hasIO) {
    var sprigIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting || e.boundingClientRect.bottom < 0) {
          e.target.classList.add('is-open');
          sprigIO.unobserve(e.target);
        }
      });
    }, { rootMargin: '0px 0px -10% 0px' });
    sprigs.forEach(function (el) { sprigIO.observe(el); });
  } else {
    sprigs.forEach(function (el) { el.classList.add('is-open'); });
  }

  // Promo bar: cross-fades between its messages. Pauses on hover or focus, while off screen or in a
  // hidden tab, and never runs with reduced motion (the CSS then keeps the plain inline layout).
  var msgs = promo ? promo.querySelectorAll('.promo-msg') : [];
  if (msgs.length > 1) {
    var idx = 0, timer = null, held = false, stopped = false, promoSeen = true;
    var every = (parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--duration-rotate')) || 5) * 1000;
    var step = function () {
      msgs[idx].classList.remove('is-on');
      idx = (idx + 1) % msgs.length;
      msgs[idx].classList.add('is-on');
    };
    var syncPromo = function () {
      var run = !stopped && !held && promoSeen && !document.hidden && !reduced();
      if (run && !timer) timer = setInterval(step, every);
      else if (!run && timer) { clearInterval(timer); timer = null; }
    };
    // A visible pause/play toggle (WCAG 2.2.2), so keyboard and touch users can stop it too.
    // Shown by CSS only while motion is allowed; its space in the bar is reserved from the first paint.
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'promo-toggle';
    toggle.setAttribute('aria-label', 'Jeda pergantian pesan promo');
    toggle.setAttribute('aria-pressed', 'false');
    toggle.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path class="pt-pause" d="M5.5 3.5v9M10.5 3.5v9"/><path class="pt-play" d="M5 3.2v9.6L12.6 8Z"/></svg>';
    toggle.addEventListener('click', function () {
      stopped = !stopped;
      toggle.setAttribute('aria-pressed', stopped ? 'true' : 'false');
      syncPromo();
    });
    promo.appendChild(toggle);
    msgs[0].classList.add('is-on');
    promo.classList.add('is-rotating');
    promo.addEventListener('mouseenter', function () { held = true; syncPromo(); });
    promo.addEventListener('mouseleave', function () { held = false; syncPromo(); });
    promo.addEventListener('focusin', function () { held = true; syncPromo(); });
    promo.addEventListener('focusout', function () { held = false; syncPromo(); });
    document.addEventListener('visibilitychange', syncPromo);
    onMotionChange(syncPromo);
    if (hasIO) new IntersectionObserver(function (entries) { promoSeen = entries[entries.length - 1].isIntersecting; syncPromo(); }).observe(promo);
    syncPromo();
  }

  // Shared with main.js: petal burst from a button, and a pop on the cart count.
  window.ksFloral = {
    burst: function (el) {
      if (reduced() || !el || !document.body.animate) return;
      var r = el.getBoundingClientRect();
      var box = document.createElement('div');
      box.className = 'burst';
      box.setAttribute('aria-hidden', 'true');
      box.style.left = (r.left + r.width / 2) + 'px';
      box.style.top = (r.top + r.height / 2) + 'px';
      document.body.appendChild(box);
      var total = 9;
      var left = total;
      var finish = function () { left -= 1; if (left <= 0) box.remove(); };
      for (var i = 0; i < total; i++) {
        var p = petal(COLORS[i % COLORS.length]);
        box.appendChild(p);
        var angle = (i / total) * Math.PI * 2 + Math.random() * 0.5;
        var dist = 34 + Math.random() * 30;
        var dx = Math.cos(angle) * dist;
        var dy = Math.sin(angle) * dist - 14;
        var rot = Math.random() * 240 - 120;
        var anim = p.animate([
          { transform: 'translate3d(0, 0, 0) rotate(0deg) scale(.4)', opacity: 1 },
          { transform: 'translate3d(' + dx.toFixed(1) + 'px, ' + dy.toFixed(1) + 'px, 0) rotate(' + rot.toFixed(0) + 'deg) scale(1)', opacity: 1, offset: 0.55 },
          { transform: 'translate3d(' + (dx * 1.15).toFixed(1) + 'px, ' + (dy + 24).toFixed(1) + 'px, 0) rotate(' + (rot * 1.5).toFixed(0) + 'deg) scale(.9)', opacity: 0 }
        ], { duration: 720, easing: EASE });
        anim.onfinish = anim.oncancel = finish;
      }
    },
    pop: function (el) {
      if (!el) return;
      el.classList.remove('is-pop');
      void el.offsetWidth;
      el.classList.add('is-pop');
    }
  };
})();
