<?php
// These learning examples must not bypass the web application's account controls.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
