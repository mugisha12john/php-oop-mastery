<?php
if (!function_exists('csrf_field')) { http_response_code(404); exit; }
$requestPath = $_SERVER['REQUEST_URI'] ?? app_url('index.php');
$basePath = app_url();
$returnTo = str_starts_with($requestPath, $basePath) ? substr($requestPath, strlen($basePath)) : 'index.php';
?>
<form class="language-switcher no-print" method="POST" action="<?= e(app_url('actions/language.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e(language_return_path($returnTo)) ?>">
    <label class="sr-only" for="site-language"><?= e(t('Language')) ?></label>
    <select id="site-language" name="locale" aria-label="<?= e(t('Language')) ?>">
        <?php foreach (supported_languages() as $code => $name): ?><option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= locale() === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="button secondary small" aria-label="<?= e(t('Apply language')) ?>"><?= e(t('Apply language')) ?></button>
</form>
