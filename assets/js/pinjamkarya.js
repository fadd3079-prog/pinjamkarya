/* =========================================================
   PINJAMKARYA UI — progressive interactions
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {
  /* ---------- Landing navigation & overlay header ---------- */
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

  /* ---------- App sidebar, modals & confirm dialog ---------- */
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

  /* ---------- Flash alerts & tabs ---------- */
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

  /* ---------- Login demo filler & registration sub-roles ---------- */
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

  /* ---------- Reservation date range & duration ---------- */
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
          durasiInfoBox.textContent = 'Durasi: ' + diffDays + ' hari (maksimal ' + maxDays + ' hari).';
          durasiInfoBox.className = diffDays > maxDays
            ? 'form-help text-danger fw-bold'
            : 'form-help text-secondary fw-bold';
        }
      }
    }
    tglMulaiInput.addEventListener('change', updateDateConstraints);
    tglSelesaiInput.addEventListener('change', updateDateConstraints);
    updateDateConstraints();
  }

  /* ---------- Custom accessible select ---------- */
  const customSelects = [];
  let selectUid = 0;

  function closeAllSelects(exceptWrap) {
    for (let i = 0; i < customSelects.length; i += 1) {
      const item = customSelects[i];
      if (item.wrap !== exceptWrap && item.wrap.classList.contains('is-open')) {
        item.wrap.classList.remove('is-open', 'pk-select--up');
        item.trigger.setAttribute('aria-expanded', 'false');
      }
    }
  }

  document.querySelectorAll('select.form-select:not([multiple])').forEach(function (selectEl) {
    if (selectEl.closest('.pk-select')) return;
    selectUid += 1;

    const wrap = document.createElement('div');
    wrap.className = 'pk-select';
    if (selectEl.disabled) {
      wrap.classList.add('is-disabled');
    }

    selectEl.parentNode.insertBefore(wrap, selectEl);
    wrap.appendChild(selectEl);

    selectEl.classList.add('pk-select-native');
    selectEl.setAttribute('tabindex', '-1');
    selectEl.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'pk-select-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const menuId = 'pkSelectMenu_' + selectUid;
    trigger.setAttribute('aria-controls', menuId);
    if (selectEl.disabled) {
      trigger.disabled = true;
    }

    const labelledBy = selectEl.getAttribute('aria-label');
    if (labelledBy) {
      trigger.setAttribute('aria-label', labelledBy);
    }

    const valueWrap = document.createElement('span');
    valueWrap.className = 'pk-select-value';

    const valueLabel = document.createElement('span');
    valueLabel.className = 'pk-select-value-text';

    const valueCount = document.createElement('span');
    valueCount.className = 'pk-select-count';
    valueCount.hidden = true;

    valueWrap.appendChild(valueLabel);
    valueWrap.appendChild(valueCount);

    const chevron = document.createElement('span');
    chevron.className = 'pk-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    chevron.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    trigger.appendChild(valueWrap);
    trigger.appendChild(chevron);

    const menu = document.createElement('div');
    menu.id = menuId;
    menu.className = 'pk-select-menu';
    menu.setAttribute('role', 'listbox');

    const optionButtons = [];

    function syncSelectedDisplay() {
      const selectedIdx = selectEl.selectedIndex >= 0 ? selectEl.selectedIndex : 0;
      const optEl = selectEl.options[selectedIdx];
      valueLabel.textContent = optEl ? optEl.textContent.trim() : '';
      const countVal = optEl ? optEl.getAttribute('data-count') : null;
      if (countVal !== null && countVal !== '') {
        valueCount.textContent = countVal;
        valueCount.hidden = false;
      } else {
        valueCount.textContent = '';
        valueCount.hidden = true;
      }

      for (let i = 0; i < optionButtons.length; i += 1) {
        const btn = optionButtons[i];
        const isSel = i === selectedIdx;
        btn.classList.toggle('is-selected', isSel);
        btn.setAttribute('aria-selected', isSel ? 'true' : 'false');
      }
    }

    Array.from(selectEl.options).forEach(function (opt, idx) {
      const optBtn = document.createElement('button');
      optBtn.type = 'button';
      optBtn.className = 'pk-select-option';
      optBtn.setAttribute('role', 'option');
      optBtn.setAttribute('data-value', opt.value);
      optBtn.setAttribute('data-index', String(idx));
      if (opt.disabled) {
        optBtn.disabled = true;
      }

      const mainRow = document.createElement('span');
      mainRow.className = 'pk-select-opt-label';
      mainRow.textContent = opt.textContent.trim();
      optBtn.appendChild(mainRow);

      const metaRight = document.createElement('span');
      metaRight.className = 'pk-select-opt-right';

      const countAttr = opt.getAttribute('data-count');
      if (countAttr !== null && countAttr !== '') {
        const badge = document.createElement('span');
        badge.className = 'pk-select-count';
        badge.textContent = countAttr;
        metaRight.appendChild(badge);
      }

      const check = document.createElement('span');
      check.className = 'pk-select-check';
      check.setAttribute('aria-hidden', 'true');
      check.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3.5 8.2l3 3 6-6.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      metaRight.appendChild(check);

      optBtn.appendChild(metaRight);

      optBtn.addEventListener('click', function () {
        if (opt.disabled) return;
        const prevVal = selectEl.value;
        selectEl.selectedIndex = idx;
        syncSelectedDisplay();
        closeAllSelects(null);
        trigger.focus();

        if (prevVal !== opt.value) {
          selectEl.dispatchEvent(new Event('change', { bubbles: true }));
          if (selectEl.hasAttribute('data-auto-submit') && selectEl.form) {
            if (typeof selectEl.form.requestSubmit === 'function') {
              selectEl.form.requestSubmit();
            } else {
              selectEl.form.submit();
            }
          }
        }
      });

      optionButtons.push(optBtn);
      menu.appendChild(optBtn);
    });

    wrap.appendChild(trigger);
    wrap.appendChild(menu);
    syncSelectedDisplay();

    if (selectEl.id) {
      document.querySelectorAll('label[for="' + selectEl.id + '"]').forEach(function (lbl) {
        lbl.addEventListener('click', function (e) {
          e.preventDefault();
          if (!trigger.disabled) trigger.focus();
        });
      });
    }

    function openMenu() {
      if (trigger.disabled) return;
      closeAllSelects(wrap);
      wrap.classList.remove('pk-select--up');
      const rect = trigger.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      if (spaceBelow < 220 && rect.top > spaceBelow) {
        wrap.classList.add('pk-select--up');
      }
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function () {
      if (wrap.classList.contains('is-open')) {
        closeAllSelects(null);
      } else {
        openMenu();
      }
    });

    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        openMenu();
        const activeBtn = menu.querySelector('.pk-select-option.is-selected:not(:disabled)') || menu.querySelector('.pk-select-option:not(:disabled)');
        if (activeBtn) activeBtn.focus();
      }
    });

    menu.addEventListener('keydown', function (e) {
      const enabledOpts = optionButtons.filter(function (b) { return !b.disabled; });
      const curIdx = enabledOpts.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = enabledOpts[Math.min(enabledOpts.length - 1, Math.max(0, curIdx + 1))];
        if (next) next.focus();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = enabledOpts[Math.max(0, curIdx - 1)];
        if (prev) prev.focus();
      } else if (e.key === 'Home') {
        e.preventDefault();
        if (enabledOpts[0]) enabledOpts[0].focus();
      } else if (e.key === 'End') {
        e.preventDefault();
        if (enabledOpts[enabledOpts.length - 1]) enabledOpts[enabledOpts.length - 1].focus();
      } else if (e.key === 'Escape' || e.key === 'Tab') {
        closeAllSelects(null);
        if (e.key === 'Escape') trigger.focus();
      }
    });

    selectEl.addEventListener('change', syncSelectedDisplay);
    if (selectEl.form) {
      selectEl.form.addEventListener('reset', function () {
        setTimeout(syncSelectedDisplay, 0);
      });
    }

    customSelects.push({ wrap: wrap, trigger: trigger, menu: menu });
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pk-select')) {
      closeAllSelects(null);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllDrawers();
      closeAllSelects(null);
    }
  });
});
