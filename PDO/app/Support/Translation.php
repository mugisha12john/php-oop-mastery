<?php
/** English message keys keep templates readable; values live in resources/lang/. */
function supported_languages(): array
{
    return ['en' => 'English', 'fr' => 'Français', 'rw' => 'Kinyarwanda'];
}

function locale(): string
{
    $value = $_SESSION['locale'] ?? $_COOKIE['mlms_language'] ?? 'en';
    return is_string($value) && isset(supported_languages()[$value]) ? $value : 'en';
}

function translations(?string $language = null): array
{
    static $catalogs = [];
    $language ??= locale();
    if (!isset(supported_languages()[$language])) { $language = 'en'; }
    return $catalogs[$language] ??= require dirname(__DIR__, 2) . '/resources/lang/' . $language . '.php';
}

function t(string $message, array $parameters = []): string
{
    $catalog = translations();
    $translation = $catalog[$message] ?? translations('en')[$message] ?? null;
    if ($translation === null && !$parameters) {
        // Resolve parameterized validation/ledger messages from the existing services.
        foreach (translation_templates() as $template) {
            $pattern = preg_quote($template, '~');
            $pattern = preg_replace('/\\\\:([a-z_]+)/', '(?P<$1>.+?)', $pattern);
            // Colons are not escaped on every PHP version.
            $pattern = preg_replace('/(?<!\\\\):([a-z_]+)/', '(?P<$1>.+?)', $pattern);
            if (preg_match('~^' . $pattern . '$~usD', $message, $matches)) {
                foreach ($matches as $key => $value) { if (is_string($key)) { $parameters[$key] = $key === 'field' ? t($value) : $value; } }
                if ($template === 'The requested amount must be between :min and :max RWF for this product.') {
                    foreach (['min','max'] as $key) { $parameters[$key] = format_number(str_replace(',', '', $parameters[$key]), 2); }
                }
                $translation = $catalog[$template] ?? $template;
                break;
            }
        }
    }
    $translation ??= $message;
    $replace = [];
    foreach ($parameters as $name => $value) { $replace[':' . $name] = (string) $value; }
    return strtr($translation, $replace);
}

function translation_templates(): array
{
    return [
        ':field is required and must be at most :max characters.',
        'The requested amount must be between :min and :max RWF for this product.',
        'This product allows a repayment term of up to :term months.',
        'Loan request #:id', 'Loan #:id', 'Application #:id',
        'Repayment posted: :receipt',
        'Error fetching customers: :details', 'Error fetching customer: :details',
        'Error creating customer: :details', 'Error updating customer: :details',
        'Error deleting customer: :details', 'Error searching customers: :details',
        'Error updating KYC status: :details',
    ];
}

function status_label(string $status): string
{
    $key = 'status.' . strtoupper($status);
    return isset(translations('en')[$key]) ? t($key) : t(str_replace('_', ' ', $status));
}

function role_label(string $role): string
{
    return t('role.' . strtoupper($role));
}

function enum_label(string $value): string
{
    $key = 'enum.' . strtoupper($value);
    return isset(translations('en')[$key]) ? t($key) : t(str_replace('_', ' ', $value));
}

function audit_label(string $value): string
{
    $key = 'audit.' . strtoupper($value);
    return isset(translations('en')[$key]) ? t($key) : str_replace('_', ' ', $value);
}

function format_number($number, int $decimals = 0): string
{
    $language = locale();
    return number_format((float) $number, $decimals, $language === 'en' ? '.' : ',',
        $language === 'fr' ? ' ' : ($language === 'rw' ? '.' : ','));
}

function format_date(?string $value, bool $withTime = false, bool $weekday = false): string
{
    if (!$value) { return '—'; }
    try { $date = new DateTimeImmutable($value, new DateTimeZone('Africa/Kigali')); }
    catch (Exception $error) { return $value; }
    $months = translations()['date.months'];
    $text = $date->format('d') . ' ' . $months[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
    if ($weekday) { $text = translations()['date.days'][(int) $date->format('w')] . ', ' . $text; }
    return $text . ($withTime ? ', ' . $date->format('H:i') : '');
}

function javascript_translations(): array
{
    return ['locale' => locale(), 'productHint' => t(':min – :max RWF · Up to :term months · :rate% flat interest, applied once.'),
        'estimate' => t('Estimated total: :total RWF · About :monthly RWF / month. Final terms depend on approval.'),
        'estimateEmpty' => t('Enter an amount and term to see an estimate.')];
}
