<?php
require_once __DIR__ . '/../app/Support/Translation.php';
$english = translations('en');
foreach (['fr','rw'] as $language) {
    $catalog = translations($language);
    if (array_keys($catalog) !== array_keys($english)) { throw new RuntimeException('Catalog keys differ: ' . $language); }
    foreach ($english as $key => $value) {
        if (!is_string($value)) { continue; }
        preg_match_all('/:[a-z_]+/', $value, $source);
        preg_match_all('/:[a-z_]+/', $catalog[$key], $target);
        sort($source[0]); sort($target[0]);
        if ($source[0] !== $target[0] || $catalog[$key] === '') { throw new RuntimeException('Invalid translation: ' . $language . '/' . $key); }
    }
}
$_SESSION['locale'] = 'fr';
if (t('Loan request #42') !== 'Demande de prêt n°42') { throw new RuntimeException('Dynamic title translation failed.'); }
if (!str_contains(t('Firm name is required and must be at most 120 characters.'), 'Nom de la société')) { throw new RuntimeException('Validation translation failed.'); }
if (format_number('10000.50',2) !== '10 000,50') { throw new RuntimeException('French number formatting failed.'); }
$_SESSION['locale'] = 'rw';
if (format_date('2026-10-20') !== '20 Ukwakira 2026') { throw new RuntimeException('Kinyarwanda date formatting failed.'); }
if (format_number('10000.50',2) !== '10.000,50') { throw new RuntimeException('Kinyarwanda number formatting failed.'); }
$_SESSION['locale'] = '../../fr';
if (locale() !== 'en') { throw new RuntimeException('Unsupported locale did not fall back to English.'); }
echo 'PASS: matching translation catalogs, preserved placeholders, localized validation, dates, numbers, and safe English fallback.' . PHP_EOL;
