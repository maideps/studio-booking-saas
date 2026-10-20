<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberRoleController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Studio routes run on <slug>.TENANCY_DOMAIN, inside that tenant (D-007).
// On any other host they return 404. An architecture test checks that every
// route with `auth` or `guest` middleware is in this group.
Route::middleware('tenant')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);

        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store']);

        Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/members', [MemberController::class, 'index'])
            ->name('members.index')
            ->can('viewAny', User::class);
        Route::patch('/members/{member}/role', MemberRoleController::class)
            ->name('members.role.update')
            ->can('updateRole', 'member');
    });
});
