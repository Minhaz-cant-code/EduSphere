<?php if (is_logged_in()): ?>
        </main>
    </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
