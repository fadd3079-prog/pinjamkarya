<?php if (!empty($minimal_layout)): ?>
</main>
<?php elseif (!empty($public_layout)): ?>
    </div>
</main>
<?= render_public_footer() ?>
<?php else: ?>
            </div>
        </main>
    </div>
</div>
<?php endif; ?>

<?php if (empty($minimal_layout)): ?>
<div id="pkConfirmModal" class="pk-modal" role="dialog" aria-modal="true" aria-labelledby="pkConfirmTitle" aria-hidden="true">
    <div class="pk-modal-dialog pk-modal-dialog--confirm">
        <div class="pk-confirm-head">
            <span class="pk-confirm-icon" id="pkConfirmIcon" aria-hidden="true"><?= svg_icon('alert-triangle', 18) ?></span>
            <div class="pk-confirm-copy">
                <h2 id="pkConfirmTitle" class="pk-confirm-title">Konfirmasi</h2>
                <p id="pkConfirmMsg" class="pk-confirm-msg"></p>
            </div>
        </div>
        <div class="pk-confirm-actions">
            <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
            <button type="button" class="btn btn-primary" id="pkConfirmSubmit">Lanjutkan</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/pinjamkarya.js?v=<?= filemtime(ROOT_PATH . '/assets/js/pinjamkarya.js') ?>" defer></script>
</body>
</html>
