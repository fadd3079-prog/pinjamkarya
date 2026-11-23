document.addEventListener('DOMContentLoaded', function () {
  const siteHeader = document.getElementById('site-header');
  const siteToggle = document.querySelector('.site-nav_toggle');
  const siteMenu = document.getElementById('site-menu');

  if (siteToggle && siteMenu) {
    const setSiteNav = function (open) {
      siteToggle.setAttribute('aria-expanded', String(open));
      siteMenu.classList.toggle('is-open', open);
      if (siteHeader) siteHeader.classList.toggle('is-menu-open', open);
    };
    siteToggle.addEventListener('click', function () {
      setSiteNav(siteToggle.getAttribute('aria-expanded') !== 'true');
    });
    siteMenu.addEventListener('click', function (e) {
      if (e.target.closest('a')) setSiteNav(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && siteMenu.classList.contains('is-open')) {
        setSiteNav(false);
        siteToggle.focus();
      }
    });
    document.addEventListener('click', function (e) {
      if (siteMenu.classList.contains('is-open') && !siteMenu.contains(e.target) && !siteToggle.contains(e.target)) {
        setSiteNav(false);
      }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 767) setSiteNav(false);
    });
  }

  if (siteHeader && siteHeader.classList.contains('site-nav--overlay')) {
    const syncHeaderOverlay = function () {
      siteHeader.classList.toggle('is-scrolled', window.scrollY > 12);
    };
    window.addEventListener('scroll', syncHeaderOverlay, { passive: true });
    syncHeaderOverlay();
  }

  const backdrop = document.getElementById('pkBackdrop');
  const sidebar = document.getElementById('pkSidebar');
  const sidebarToggle = document.getElementById('pkSidebarToggle');
  const sidebarClose = document.getElementById('pkSidebarClose');

  let lastFocusedTrigger = null;

  function closeAllDrawers() {
    if (sidebar && sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    }
    document.querySelectorAll('.pk-modal.open').forEach(function (modal) {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    });
    if (backdrop) backdrop.classList.remove('show');
    if (lastFocusedTrigger) {
      lastFocusedTrigger.focus();
      lastFocusedTrigger = null;
    }
  }

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      lastFocusedTrigger = sidebarToggle;
      sidebar.classList.add('open');
      sidebarToggle.setAttribute('aria-expanded', 'true');
      if (backdrop) backdrop.classList.add('show');
      if (sidebarClose) sidebarClose.focus();
    });
  }

  if (sidebarClose) {
    sidebarClose.addEventListener('click', closeAllDrawers);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeAllDrawers);
  }

  document.querySelectorAll('[data-modal-target]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      const targetSelector = trigger.getAttribute('data-modal-target');
      const modal = document.querySelector(targetSelector);
      if (modal) {
        lastFocusedTrigger = trigger;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        const closeBtn = modal.querySelector('[data-modal-close]');
        if (closeBtn) closeBtn.focus();
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', closeAllDrawers);
  });

  document.querySelectorAll('.pk-modal').forEach(function (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        closeAllDrawers();
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllDrawers();
    }
  });

  document.querySelectorAll('.pk-alert-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const alertBox = btn.closest('.pk-alert');
      if (alertBox) alertBox.remove();
    });
  });

  document.querySelectorAll('[data-tab-target]').forEach(function (tabBtn) {
    tabBtn.addEventListener('click', function () {
      const tabContainer = tabBtn.closest('.pk-tabs');
      const targetSelector = tabBtn.getAttribute('data-tab-target');
      const targetPanel = document.querySelector(targetSelector);
      if (!tabContainer || !targetPanel) return;

      tabContainer.querySelectorAll('[data-tab-target]').forEach(function (btn) {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
      });

      document.querySelectorAll('.pk-tab-panel').forEach(function (panel) {
        panel.classList.remove('active');
      });

      tabBtn.classList.add('active');
      tabBtn.setAttribute('aria-selected', 'true');
      targetPanel.classList.add('active');
    });
  });

  const demoButtons = document.querySelectorAll('[data-demo-email]');
  demoButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const emailInput = document.getElementById('email');
      const passInput = document.getElementById('password');
      const submitBtn = document.getElementById('loginSubmitBtn');
      demoButtons.forEach(function (item) {
        item.classList.remove('is-selected');
      });
      btn.classList.add('is-selected');
      if (emailInput && passInput) {
        emailInput.value = btn.getAttribute('data-demo-email');
        passInput.value = btn.getAttribute('data-demo-pass') || 'password';
        if (submitBtn) {
          submitBtn.focus();
        } else {
          emailInput.focus();
        }
      }
    });
  });

  const roleSelect = document.getElementById('roleSelect');
  const mitraSubroleBox = document.getElementById('mitraSubroleBox');
  if (roleSelect && mitraSubroleBox) {
    function syncRoleView() {
      mitraSubroleBox.style.display = roleSelect.value === 'mitra' ? 'block' : 'none';
    }
    roleSelect.addEventListener('change', syncRoleView);
    syncRoleView();
  }

  const tglMulaiInput = document.getElementById('tgl_mulai');
  const tglSelesaiInput = document.getElementById('tgl_selesai');
  const durasiInfoBox = document.getElementById('durasiInfoBox');
  if (tglMulaiInput && tglSelesaiInput) {
    function updateDateConstraints() {
      if (tglMulaiInput.value) {
        tglSelesaiInput.min = tglMulaiInput.value;
        if (tglSelesaiInput.value && tglSelesaiInput.value < tglMulaiInput.value) {
          tglSelesaiInput.value = tglMulaiInput.value;
        }
      }
      if (durasiInfoBox && tglMulaiInput.value && tglSelesaiInput.value) {
        const start = new Date(tglMulaiInput.value);
        const end = new Date(tglSelesaiInput.value);
        const diffDays = Math.round((end - start) / 86400000) + 1;
        const maxDays = parseInt(durasiInfoBox.getAttribute('data-max-hari') || '3', 10);
        if (diffDays > 0) {
          if (diffDays > maxDays) {
            durasiInfoBox.textContent = 'Durasi: ' + diffDays + ' hari (maksimal ' + maxDays + ' hari).';
            durasiInfoBox.className = 'form-help text-danger fw-bold';
          } else {
            durasiInfoBox.textContent = 'Durasi: ' + diffDays + ' hari (maksimal ' + maxDays + ' hari).';
            durasiInfoBox.className = 'form-help text-secondary fw-bold';
          }
        }
      }
    }
    tglMulaiInput.addEventListener('change', updateDateConstraints);
    tglSelesaiInput.addEventListener('change', updateDateConstraints);
    updateDateConstraints();
  }
});
