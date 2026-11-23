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

  const confirmModal = document.getElementById('pkConfirmModal');
  const confirmTitle = document.getElementById('pkConfirmTitle');
  const confirmMsg = document.getElementById('pkConfirmMsg');
  const confirmIcon = document.getElementById('pkConfirmIcon');
  const confirmSubmitBtn = document.getElementById('pkConfirmSubmit');
  let pendingConfirmForm = null;
  let lastFocusedTrigger = null;

  function syncModalBodyLock() {
    document.body.classList.toggle('pk-modal-open', Boolean(document.querySelector('.pk-modal.open')));
  }

  function closeAllDrawers() {
    if (sidebar && sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
      if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    }
    const openModals = document.querySelectorAll('.pk-modal.open');
    for (let i = 0; i < openModals.length; i += 1) {
      const modal = openModals[i];
      const cancelUrl = modal.getAttribute('data-cancel-url');
      if (cancelUrl) {
        window.location.href = cancelUrl;
        return;
      }
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
    pendingConfirmForm = null;
    syncModalBodyLock();
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
        syncModalBodyLock();
        const firstField = modal.querySelector('input:not([type="hidden"]), select, textarea');
        const closeBtn = modal.querySelector('[data-modal-close]');
        if (firstField) {
          firstField.focus();
        } else if (closeBtn) {
          closeBtn.focus();
        }
      }
    });
  });

  syncModalBodyLock();

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

  if (confirmModal && confirmSubmitBtn) {
    const toneBtnMap = {
      danger: 'btn btn-danger',
      warm: 'btn btn-warm',
      ok: 'btn btn-secondary',
      primary: 'btn btn-primary',
    };
    const toneIconMap = {
      danger: 'pk-confirm-icon pk-confirm-icon--danger',
      warm: 'pk-confirm-icon pk-confirm-icon--warm',
      ok: 'pk-confirm-icon pk-confirm-icon--ok',
      primary: 'pk-confirm-icon pk-confirm-icon--info',
    };

    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
      if (form.dataset.confirmed === '1') return;

      e.preventDefault();
      pendingConfirmForm = form;
      lastFocusedTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;

      const tone = form.getAttribute('data-confirm-tone') || 'primary';
      if (confirmTitle) confirmTitle.textContent = form.getAttribute('data-confirm-title') || 'Konfirmasi';
      if (confirmMsg) confirmMsg.textContent = form.getAttribute('data-confirm') || '';
      if (confirmIcon) confirmIcon.className = toneIconMap[tone] || 'pk-confirm-icon';
      confirmSubmitBtn.className = toneBtnMap[tone] || 'btn btn-primary';
      confirmSubmitBtn.textContent = form.getAttribute('data-confirm-btn') || 'Lanjutkan';

      confirmModal.classList.add('open');
      confirmModal.setAttribute('aria-hidden', 'false');
      syncModalBodyLock();
      confirmSubmitBtn.focus();
    });

    confirmSubmitBtn.addEventListener('click', function () {
      if (!pendingConfirmForm) return;
      const targetForm = pendingConfirmForm;
      pendingConfirmForm = null;
      targetForm.dataset.confirmed = '1';
      confirmModal.classList.remove('open');
      confirmModal.setAttribute('aria-hidden', 'true');
      syncModalBodyLock();
      HTMLFormElement.prototype.submit.call(targetForm);
    });
  }

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

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  document.querySelectorAll('[data-masonry]').forEach(function (grid) {
    const cards = Array.from(grid.querySelectorAll('.pk-tool-card'));
    if (!cards.length) return;

    let debounceId = 0;
    let lastWidth = -1;
    let lastCols = -1;

    function columnCount() {
      const w = window.innerWidth;
      if (w >= 1100) return 4;
      if (w >= 768) return 3;
      return 2;
    }

    function layoutMasonry() {
      const cols = columnCount();
      const gap = parseFloat(getComputedStyle(grid).getPropertyValue('--pk-masonry-gap')) || 12;
      const width = grid.clientWidth;
      if (width <= 0) return;

      const colWidth = Math.floor((width - (cols - 1) * gap) / cols);
      const colHeights = new Array(cols).fill(0);

      for (let i = 0; i < cards.length; i += 1) {
        const card = cards[i];
        const ratio = parseFloat(card.dataset.ratio) || 0.75;
        const height = Math.max(cols <= 2 ? 158 : 0, Math.round(colWidth * ratio));
        let col = 0;
        for (let c = 1; c < cols; c += 1) {
          if (colHeights[c] < colHeights[col]) col = c;
        }
        const x = col * (colWidth + gap);
        const y = colHeights[col];
        card.style.width = colWidth + 'px';
        card.style.height = height + 'px';
        card.style.transform = 'translate3d(' + x + 'px, ' + y + 'px, 0)';
        colHeights[col] += height + gap;
      }

      const tallest = Math.max.apply(null, colHeights.concat([0]));
      grid.style.height = Math.max(0, tallest - gap) + 'px';
      lastWidth = width;
      lastCols = cols;
    }

    function scheduleLayout() {
      if (debounceId) clearTimeout(debounceId);
      debounceId = setTimeout(function () {
        debounceId = 0;
        layoutMasonry();
      }, 120);
    }

    grid.classList.add('is-masonry');
    layoutMasonry();
    if (!reducedMotion.matches) {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          grid.classList.add('is-animated');
        });
      });
    }

    cards.forEach(function (card) {
      const img = card.querySelector('img');
      if (!img) return;
      function syncImgRatio() {
        if (img.naturalWidth > 0 && img.naturalHeight > 0) {
          const actualRatio = img.naturalHeight / img.naturalWidth;
          const prevRatio = parseFloat(card.dataset.ratio) || 0;
          if (Math.abs(actualRatio - prevRatio) >= 0.005) {
            card.dataset.ratio = String(actualRatio);
            card.style.setProperty('--ratio', String(actualRatio));
            scheduleLayout();
          }
        }
      }
      if (img.complete) {
        syncImgRatio();
      } else {
        img.addEventListener('load', syncImgRatio, { once: true });
      }
    });

    if ('ResizeObserver' in window) {
      const ro = new ResizeObserver(function () {
        const w = grid.clientWidth;
        const c = columnCount();
        if (Math.abs(w - lastWidth) >= 2 || c !== lastCols) {
          scheduleLayout();
        }
      });
      ro.observe(grid);
    }

    window.addEventListener('resize', function () {
      if (Math.abs(grid.clientWidth - lastWidth) >= 2 || columnCount() !== lastCols) {
        scheduleLayout();
      }
    });
  });
});
