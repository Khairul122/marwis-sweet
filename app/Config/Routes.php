<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('menu/(:segment)', 'Home::product/$1', ['as' => 'public.product']);
$routes->get('login', 'AuthController::login', ['as' => 'login']);
$routes->get('ulasan/foto/(:num)', 'ReviewController::photo/$1', ['as' => 'reviews.photo']);
$routes->get('ulasan/(:segment)', 'ReviewController::form/$1', ['as' => 'reviews.form']);
$routes->post('ulasan/(:segment)', 'ReviewController::submit/$1', ['as' => 'reviews.submit']);
$routes->post('login', 'AuthController::attempt', ['as' => 'login.attempt']);
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth', 'as' => 'logout']);
$routes->group('panel', ['filter' => ['auth', 'returnto']], static function ($routes): void {
    $routes->get('/', 'Panel\DashboardController::index', ['as' => 'panel']);
    $routes->group('audit', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\AuditController::index', ['as' => 'panel.audit']);
    });
    $routes->group('data', static function ($routes): void {
        $routes->get('receipt/create', 'Panel\CrudController::create/receipt', ['filter' => 'owner']);
        $routes->get('(:segment)/create', 'Panel\CrudController::create/$1');
        $routes->get('(:segment)/(:num)/edit', 'Panel\CrudController::edit/$1/$2');
    });
    $routes->group('catalog', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\CatalogController::index', ['as' => 'panel.catalog']);
        $routes->post('categories', 'Panel\CatalogController::createCategory', ['as' => 'panel.catalog.categories.create']);
        $routes->post('categories/status', 'Panel\CatalogController::toggleCategory', ['as' => 'panel.catalog.categories.status']);
        $routes->post('categories/update', 'Panel\CatalogController::updateCategory');
        $routes->post('categories/delete', 'Panel\CatalogController::deleteCategory');
        $routes->post('products', 'Panel\CatalogController::createProduct', ['as' => 'panel.catalog.products.create']);
        $routes->post('products/status', 'Panel\CatalogController::toggleProduct', ['as' => 'panel.catalog.products.status']);
        $routes->post('products/update', 'Panel\CatalogController::updateProduct');
        $routes->post('products/delete', 'Panel\CatalogController::deleteProduct');
        $routes->post('products/images', 'Panel\CatalogController::addImage');
        $routes->post('products/images/update', 'Panel\CatalogController::updateImage');
        $routes->post('products/images/order', 'Panel\CatalogController::moveImage');
        $routes->post('products/images/delete', 'Panel\CatalogController::deleteImage');
        $routes->post('variants', 'Panel\CatalogController::addVariant', ['as' => 'panel.catalog.variants.create']);
        $routes->post('variants/update', 'Panel\CatalogController::updateVariant');
    });
    $routes->group('master', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\MasterDataController::index', ['as' => 'panel.master']);
        $routes->post('suppliers', 'Panel\MasterDataController::createSupplier');
        $routes->post('suppliers/status', 'Panel\MasterDataController::toggleSupplier');
        $routes->post('suppliers/update', 'Panel\MasterDataController::updateSupplier');
        $routes->post('suppliers/delete', 'Panel\MasterDataController::deleteSupplier');
        $routes->post('items', 'Panel\MasterDataController::createItem');
        $routes->post('items/status', 'Panel\MasterDataController::toggleItem');
        $routes->post('items/update', 'Panel\MasterDataController::updateItem');
        $routes->post('items/delete', 'Panel\MasterDataController::deleteItem');
        $routes->post('conversions', 'Panel\MasterDataController::saveConversion');
        $routes->post('conversions/remove', 'Panel\MasterDataController::removeConversion');
        $routes->get('recipes/(:num)', 'Panel\MasterDataController::recipe/$1');
        $routes->post('recipes', 'Panel\MasterDataController::saveRecipeItem');
        $routes->post('recipes/remove', 'Panel\MasterDataController::removeRecipeItem');
        $routes->post('toppings', 'Panel\MasterDataController::createTopping');
        $routes->post('toppings/status', 'Panel\MasterDataController::toggleTopping');
        $routes->post('toppings/update', 'Panel\MasterDataController::updateTopping');
        $routes->post('toppings/delete', 'Panel\MasterDataController::deleteTopping');
        $routes->post('toppings/items/remove', 'Panel\MasterDataController::removeToppingItem');
        $routes->post('toppings/items', 'Panel\MasterDataController::saveToppingItem');
    });
    $routes->group('settings', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\SettingsController::index', ['as' => 'panel.settings']);
        $routes->post('business', 'Panel\SettingsController::saveBusiness');
        $routes->post('sales-policy', 'Panel\SettingsController::saveSalesPolicy');
        $routes->post('banners', 'Panel\SettingsController::createBanner');
        $routes->post('banners/status', 'Panel\SettingsController::toggleBanner');
        $routes->post('banners/update', 'Panel\SettingsController::updateBanner');
        $routes->post('banners/delete', 'Panel\SettingsController::deleteBanner');
    });
    $routes->group('inventory', ['filter' => 'inventorystaff'], static function ($routes): void {
        $routes->get('/', 'Panel\InventoryController::index', ['as' => 'panel.inventory']);
        $routes->get('adjustments', 'Panel\StockAdjustmentController::index', ['as' => 'panel.inventory.adjustments']);
        $routes->get('adjustments/new', 'Panel\StockAdjustmentController::new');
        $routes->get('adjustments/(:num)', 'Panel\StockAdjustmentController::show/$1');
        $routes->post('adjustments', 'Panel\StockAdjustmentController::create');
        $routes->post('adjustments/items', 'Panel\StockAdjustmentController::saveLine');
        $routes->post('adjustments/(:num)/submit', 'Panel\StockAdjustmentController::submit/$1');
        $routes->post('adjustments/(:num)/approve', 'Panel\StockAdjustmentController::approve/$1', ['filter' => 'owner']);
        $routes->post('adjustments/(:num)/reject', 'Panel\StockAdjustmentController::reject/$1', ['filter' => 'owner']);
        $routes->post('adjustments/post', 'Panel\StockAdjustmentController::post');
        $routes->post('adjustments/(:num)/reverse', 'Panel\StockAdjustmentController::reverse/$1', ['filter' => 'owner']);
        $routes->get('receipts/(:num)', 'Panel\InventoryController::receipt/$1');
        $routes->get('receipts/new', 'Panel\InventoryController::newReceipt');
        $routes->post('receipts', 'Panel\InventoryController::createReceipt');
        $routes->post('receipts/items', 'Panel\InventoryController::addReceiptItem');
        $routes->post('receipts/post', 'Panel\InventoryController::postReceipt');
        $routes->post('receipts/(:num)/submit', 'Panel\InventoryController::submitReceipt/$1');
        $routes->post('receipts/(:num)/approve', 'Panel\InventoryController::approveReceipt/$1', ['filter' => 'owner']);
        $routes->post('receipts/(:num)/reject', 'Panel\InventoryController::rejectReceipt/$1', ['filter' => 'owner']);
    });
    $routes->group('opnames', static function ($routes): void {
        $routes->get('/', 'Panel\StockOpnameController::index', ['as' => 'panel.opnames']);
        $routes->post('create', 'Panel\StockOpnameController::create');
        $routes->post('suggestions/refresh', 'Panel\StockOpnameController::refreshSuggestions');
        $routes->post('suggestions/start', 'Panel\StockOpnameController::startSuggestions');
        $routes->post('suggestions/(:num)/dismiss', 'Panel\StockOpnameController::dismissSuggestion/$1');
        $routes->post('(:num)/counts', 'Panel\StockOpnameController::saveCounts/$1');
        $routes->post('(:num)/evidence', 'Panel\StockOpnameController::uploadEvidence/$1');
        $routes->get('(:num)/evidence', 'Panel\StockOpnameController::evidence/$1');
        $routes->post('(:num)/submit', 'Panel\StockOpnameController::submit/$1');
        $routes->post('(:num)/cancel', 'Panel\StockOpnameController::cancel/$1');
        $routes->post('(:num)/approve', 'Panel\StockOpnameController::approve/$1', ['filter' => 'owner']);
        $routes->post('(:num)/reject', 'Panel\StockOpnameController::reject/$1', ['filter' => 'owner']);
    });
    $routes->group('expenses', static function ($routes): void {
        $routes->get('/', 'Panel\ExpenseController::index', ['as' => 'panel.expenses']);
        $routes->post('create', 'Panel\ExpenseController::create');
        $routes->post('(:num)/update', 'Panel\ExpenseController::update/$1');
        $routes->post('(:num)/submit', 'Panel\ExpenseController::submit/$1');
        $routes->post('(:num)/cancel', 'Panel\ExpenseController::cancel/$1');
        $routes->get('(:num)/evidence', 'Panel\ExpenseController::evidence/$1');
        $routes->post('(:num)/approve', 'Panel\ExpenseController::approve/$1', ['filter' => 'owner']);
        $routes->post('(:num)/pay', 'Panel\ExpenseController::pay/$1', ['filter' => 'owner']);
        $routes->post('(:num)/reject', 'Panel\ExpenseController::reject/$1', ['filter' => 'owner']);
        $routes->post('categories', 'Panel\ExpenseController::createCategory', ['filter' => 'owner']);
        $routes->post('categories/status', 'Panel\ExpenseController::toggleCategory', ['filter' => 'owner']);
    });
    $routes->group('accounting', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\AccountingController::index', ['as' => 'panel.accounting']);
        $routes->post('journals', 'Panel\AccountingController::manualJournal');
        $routes->post('opening-balances', 'Panel\AccountingController::openingBalance');
        $routes->post('periods/close', 'Panel\AccountingController::closePeriod');
        $routes->post('periods/(:num)/reopen', 'Panel\AccountingController::reopenPeriod/$1');
    });
    $routes->group('reports', static function ($routes): void {
        $routes->get('/', 'Panel\ReportController::index', ['as' => 'panel.reports']);
        $routes->get('sales.pdf', 'Panel\ReportController::pdf', ['as' => 'panel.reports.sales.pdf']);
        $routes->get('(:alpha).pdf', 'Panel\ReportController::operationalPdf/$1');
    });
    $routes->group('shifts', ['filter' => 'cashier'], static function ($routes): void {
        $routes->get('/', 'Panel\ShiftController::index', ['as' => 'panel.shifts']);
        $routes->post('open', 'Panel\ShiftController::open');
        $routes->post('cash-movements', 'Panel\ShiftController::cashMovement');
        $routes->post('close', 'Panel\ShiftController::close');
    });
    $routes->group('shift-reviews', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\ShiftReviewController::index', ['as' => 'panel.shift-reviews']);
        $routes->post('(:num)/approve', 'Panel\ShiftReviewController::decide/$1/approve');
        $routes->post('(:num)/reject', 'Panel\ShiftReviewController::decide/$1/reject');
    });
    $routes->group('pos', ['filter' => 'cashier'], static function ($routes): void {
        $routes->get('/', 'Panel\PosController::index', ['as' => 'panel.pos']);
        $routes->post('orders', 'Panel\PosController::saveOrder', ['as' => 'panel.pos.orders.save']);
        $routes->post('orders/(:num)/cancel', 'Panel\PosController::cancelOrder/$1', ['as' => 'panel.pos.orders.cancel']);
        $routes->post('finalize', 'Panel\PosController::finalize');
    });
    $routes->get('transactions', 'Panel\PosController::transactions', ['as' => 'panel.transactions']);
    $routes->get('transactions/(:num).pdf', 'Panel\PosController::receiptPdf/$1', ['as' => 'panel.transactions.receipt.pdf']);
    $routes->get('transactions/(:num)', 'Panel\PosController::receipt/$1');
    $routes->post('transactions/(:num)/refunds', 'Panel\RefundController::submit/$1');
    $routes->post('transactions/(:num)/review-link', 'Panel\PosController::reviewLink/$1');
    $routes->group('refunds', ['filter' => 'owner'], static function ($routes): void {
        $routes->post('(:num)/approve', 'Panel\RefundController::approve/$1');
        $routes->post('(:num)/reject', 'Panel\RefundController::reject/$1');
    });
    $routes->group('users', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\UsersController::index', ['as' => 'panel.users']);
        $routes->post('create', 'Panel\UsersController::create', ['as' => 'panel.users.create']);
        $routes->post('update', 'Panel\UsersController::update');
        $routes->post('status', 'Panel\UsersController::status', ['as' => 'panel.users.status']);
        $routes->post('reset-password', 'Panel\UsersController::resetPassword', ['as' => 'panel.users.reset-password']);
    });
    $routes->group('reviews', ['filter' => 'owner'], static function ($routes): void {
        $routes->get('/', 'Panel\ReviewController::index', ['as' => 'panel.reviews']);
        $routes->post('(:num)/moderate', 'Panel\ReviewController::moderate/$1');
    });
});
