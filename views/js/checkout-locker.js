(function () {
  function normalizeCarrierExtraLayout(wrapper) {
    var host = wrapper.closest('.carrier-extra-content.js-carrier-extra-content.row');
    if (!host) {
      return;
    }

    host.classList.remove('row');
  }

  function initLockerSelector(wrapper) {
    if (wrapper.getAttribute('data-bookurier-locker-init') === '1') {
      return;
    }

    var saveUrl = wrapper.getAttribute('data-save-url');
    var select = wrapper.querySelector('.bookurier-locker-select');
    var status = wrapper.querySelector('.bookurier-locker-status');

    if (!saveUrl || !select || !status) {
      return;
    }

    normalizeCarrierExtraLayout(wrapper);

    function setStatus(message, isError) {
      status.textContent = message || '';
      status.className = isError
        ? 'bookurier-locker-status form-text text-danger'
        : 'bookurier-locker-status form-text text-success';
    }

    function persistSelection() {
      var lockerId = parseInt(select.value || '0', 10);
      if (!lockerId) {
        setStatus('', false);
        return;
      }

      fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'locker_id=' + encodeURIComponent(String(lockerId))
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            throw new Error(payload && payload.message ? payload.message : 'Locker save failed.');
          }

          return payload;
        })
        .then(function () {
          setStatus('', false);
        })
        .catch(function (error) {
          setStatus(error && error.message ? error.message : 'Locker save failed.', true);
        });
    }

    select.addEventListener('change', persistSelection);
    initTomSelect(select, persistSelection);

    wrapper.setAttribute('data-bookurier-locker-init', '1');
  }

  function initTomSelect(select, onChange) {
    if (typeof window.TomSelect !== 'function') {
      return;
    }

    if (select.tomselect) {
      return;
    }

    var tom = new window.TomSelect(select, {
      maxItems: 1,
      create: false,
      persist: false,
      allowEmptyOption: true,
      openOnFocus: true,
      closeAfterSelect: false,
      searchField: ['text'],
      plugins: ['dropdown_input'],
      placeholder: 'Search locker...',
      hidePlaceholder: false
    });

    tom.on('change', function () {
      onChange();
    });
  }

  function boot() {
    var wrappers = document.querySelectorAll('.bookurier-locker-selector');
    for (var i = 0; i < wrappers.length; i += 1) {
      initLockerSelector(wrappers[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  if (window.prestashop && typeof window.prestashop.on === 'function') {
    window.prestashop.on('updatedDeliveryForm', boot);
    window.prestashop.on('updatedCart', boot);
  }
})();
