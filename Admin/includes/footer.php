<script>
window.ADMIN_SITE_BASE = <?php echo json_encode(SITE_BASE_URL, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(adminAsset('js/toast.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(adminAsset('js/admin_mock_data.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>window.ADMIN_CSRF_TOKEN = <?php echo json_encode(adminCsrfToken(), JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo htmlspecialchars(adminAsset('js/admin_common.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php foreach ($adminExtraScripts as $src): ?>
    <script src="<?php echo htmlspecialchars(adminAsset($src), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>
</body>
</html>
