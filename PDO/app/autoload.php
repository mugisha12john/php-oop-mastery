<?php
// A small class map keeps the existing PHP stack free of framework dependencies.
require_once __DIR__ . '/Support/Translation.php';
spl_autoload_register(static function (string $class): void {
    $classes = [
        'AuthService' => 'Auth/AuthService.php',
        'PasswordService' => 'Auth/PasswordService.php',
        'CustomerAccountService' => 'Customers/CustomerAccountService.php',
        'CustomerModel' => 'Customers/CustomerModel.php',
        'CustomerPortalService' => 'Customers/CustomerPortalService.php',
        'CustomerController' => 'Http/Controllers/CustomerController.php',
        'FirmModel' => 'Firms/FirmModel.php',
        'BaseModel' => 'Support/BaseModel.php',
        'Money' => 'Lending/Money.php',
        'LendingAccess' => 'Lending/Access.php',
        'DocumentService' => 'Lending/DocumentService.php',
        'LendingService' => 'Lending/LendingService.php',
        'AdminService' => 'Lending/AdminService.php',
    ];
    if (isset($classes[$class])) { require_once __DIR__ . '/' . $classes[$class]; }
});
