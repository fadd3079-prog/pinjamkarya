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

<?= render_public_footer() ?>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/pinjamkarya.js?v=<?= filemtime(ROOT_PATH . '/assets/js/pinjamkarya.js') ?>" defer></script>
</body>
</html>
