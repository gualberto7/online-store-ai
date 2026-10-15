<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\OrderConfirmationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show'])->scoped();
        Route::post('orders/{order}/confirmation', [OrderConfirmationController::class, 'store'])->name('orders.confirm')->scopeBindings();
        Route::post('orders/{order}/cancellation', [OrderCancellationController::class, 'store'])->name('orders.cancel')->scopeBindings();
        Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update'])->scoped();
        Route::resource('products.images', ProductImageController::class)->only(['store', 'destroy'])->scoped();
        Route::resource('products', ProductController::class)->except(['destroy'])->scoped();
        Route::resource('products.variants', ProductVariantController::class)
            ->only(['create', 'store', 'edit', 'update'])->scoped();
        Route::resource('branches.inventory', InventoryController::class)->only(['index', 'store'])->scoped();
        Route::resource('branches', BranchController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->scoped();
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
