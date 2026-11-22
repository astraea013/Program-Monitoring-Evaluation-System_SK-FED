  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $__s): ?>
<script src="<?= e($__s) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($inline_script)): ?>
<script><?= $inline_script ?></script>
<?php endif; ?>
</body>
</html>
