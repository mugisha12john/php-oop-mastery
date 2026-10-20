<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_post();
$language = post_string('locale');
if (!isset(supported_languages()[$language])) {
    http_response_code(400);
    exit(e(t('Invalid language.')));
}
$return = language_return_path(post_string('return_to'));
$_SESSION['locale'] = $language;
setcookie('mlms_language', $language, ['expires' => time() + 31536000, 'path' => app_url(),
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
redirect($return);
