<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('menu/(:segment)', 'Home::product/$1', ['as' => 'public.product']);
$routes->get('login', 'AuthController::login', ['as' => 'login']);
$routes->post('login', 'AuthController::attempt', ['as' => 'login.attempt']);
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth', 'as' => 'logout']);
$routes->group('panel', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', 'Panel\DashboardController::index', ['as' => 'panel']);
    $routes->group('catalog', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\CatalogController::index', ['as' => 'panel.catalog']);
        $routes->post('categories', 'Panel\CatalogController::createCategory', ['as' => 'panel.catalog.categories.create']);
        $routes->post('categories/status', 'Panel\CatalogController::toggleCategory', ['as' => 'panel.catalog.categories.status']);
        $routes->post('categories/update', 'Panel\CatalogController::updateCategory');
        $routes->post('products', 'Panel\CatalogController::createProduct', ['as' => 'panel.catalog.products.create']);
        $routes->post('products/status', 'Panel\CatalogController::toggleProduct', ['as' => 'panel.catalog.products.status']);
        $routes->post('products/update', 'Panel\CatalogController::updateProduct');
        $routes->post('products/images', 'Panel\CatalogController::addImage');
        $routes->post('variants', 'Panel\CatalogController::addVariant', ['as' => 'panel.catalog.variants.create']);
        $routes->post('variants/update', 'Panel\CatalogController::updateVariant');
    });
    $routes->group('master', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\MasterDataController::index', ['as' => 'panel.master']);
        $routes->post('suppliers', 'Panel\MasterDataController::createSupplier');
        $routes->post('suppliers/status', 'Panel\MasterDataController::toggleSupplier');
        $routes->post('suppliers/update', 'Panel\MasterDataController::updateSupplier');
        $routes->post('items', 'Panel\MasterDataController::createItem');
        $routes->post('items/status', 'Panel\MasterDataController::toggleItem');
        $routes->post('items/update', 'Panel\MasterDataController::updateItem');
        $routes->post('conversions', 'Panel\MasterDataController::saveConversion');
        $routes->post('recipes', 'Panel\MasterDataController::saveRecipeItem');
        $routes->post('recipes/remove', 'Panel\MasterDataController::removeRecipeItem');
        $routes->post('toppings', 'Panel\MasterDataController::createTopping');
        $routes->post('toppings/status', 'Panel\MasterDataController::toggleTopping');
        $routes->post('toppings/update', 'Panel\MasterDataController::updateTopping');
        $routes->post('toppings/items', 'Panel\MasterDataController::saveToppingItem');
    });
    $routes->group('settings', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\SettingsController::index', ['as' => 'panel.settings']);
        $routes->post('business', 'Panel\SettingsController::saveBusiness');
        $routes->post('banners', 'Panel\SettingsController::createBanner');
        $routes->post('banners/status', 'Panel\SettingsController::toggleBanner');
        $routes->post('banners/update', 'Panel\SettingsController::updateBanner');
    });
    $routes->group('inventory', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\InventoryController::index', ['as' => 'panel.inventory']);
        $routes->post('receipts', 'Panel\InventoryController::createReceipt');
        $routes->post('receipts/items', 'Panel\InventoryController::addReceiptItem');
        $routes->post('receipts/post', 'Panel\InventoryController::postReceipt');
    });
    $routes->group('shifts', static function ($routes): void {
        $routes->get('/', 'Panel\ShiftController::index', ['as' => 'panel.shifts']);
        $routes->post('open', 'Panel\ShiftController::open');
        $routes->post('cash-movements', 'Panel\ShiftController::cashMovement');
        $routes->post('close', 'Panel\ShiftController::close');
    });
    $routes->group('pos', static function ($routes): void {
        $routes->get('/', 'Panel\PosController::index', ['as' => 'panel.pos']);
        $routes->post('finalize', 'Panel\PosController::finalize');
    });
    $routes->get('transactions', 'Panel\PosController::transactions', ['as' => 'panel.transactions']);
    $routes->get('transactions/(:num)', 'Panel\PosController::receipt/$1');
    $routes->post('transactions/(:num)/refunds', 'Panel\RefundController::submit/$1');
    $routes->group('refunds', ['filter' => 'owner'], static function ($routes): void {
        $routes->post('(:num)/approve', 'Panel\RefundController::approve/$1');
        $routes->post('(:num)/reject', 'Panel\RefundController::reject/$1');
    });
    $routes->group('users', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\UsersController::index', ['as' => 'panel.users']);
        $routes->post('create', 'Panel\UsersController::create', ['as' => 'panel.users.create']);
        $routes->post('status', 'Panel\UsersController::status', ['as' => 'panel.users.status']);
        $routes->post('reset-password', 'Panel\UsersController::resetPassword', ['as' => 'panel.users.reset-password']);
    });
});
