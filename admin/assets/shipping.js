// Dasbor > Ongkir: centang "Tanya dulu" mematikan isian ongkir (server tetap memeriksa ulang).
(function () {
  function bind(box) {
    var fee = document.getElementById(box.getAttribute('data-ask'));
    if (!fee) return;
    function sync() {
      fee.disabled = box.checked;
      fee.required = !box.checked;
      if (!box.checked && fee.value === '' && fee.dataset.last) fee.value = fee.dataset.last;
      if (box.checked && fee.value !== '') fee.dataset.last = fee.value;
    }
    box.addEventListener('change', function () {
      sync();
      if (!box.checked) fee.focus();
    });
    sync();
  }
  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('input[type="checkbox"][data-ask]'), bind);
    // Kode baru: rapikan saat diketik (huruf kecil, spasi jadi tanda hubung).
    var code = document.getElementById('new-code');
    if (code) code.addEventListener('blur', function () {
      code.value = code.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
