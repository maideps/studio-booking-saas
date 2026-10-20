<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClassSessionController;
use App\Http\Controllers\ClassTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberRoleController;
use App\Http\Controllers\StudioSettingsController;
use App\Http\Controllers\WaitlistController;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WaitlistEntry;
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

    // The invitation link works for guests and for the invited member signed in.
    Route::get('/join/{token}', [JoinController::class, 'show'])->name('join.show');
    Route::post('/join/{token}', [JoinController::class, 'store'])->name('join.store');

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/members', [MemberController::class, 'index'])
            ->name('members.index')
            ->can('viewAny', User::class);
        Route::patch('/members/{member}/role', MemberRoleController::class)
            ->name('members.role.update')
            ->can('updateRole', 'member');

        Route::post('/invitations', [InvitationController::class, 'store'])
            ->name('invitations.store')
            ->can('create', Invitation::class);
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])
            ->name('invitations.destroy')
            ->can('delete', 'invitation');

        // Authorized in the controller: the studio is the tenant context, not a route parameter.
        Route::get('/settings', [StudioSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [StudioSettingsController::class, 'update'])->name('settings.update');

        Route::get('/classes', [ClassTemplateController::class, 'index'])
            ->name('classes.index')
            ->can('viewAny', ClassTemplate::class);
        Route::get('/classes/create', [ClassTemplateController::class, 'create'])
            ->name('classes.create')
            ->can('create', ClassTemplate::class);
        Route::post('/classes', [ClassTemplateController::class, 'store'])
            ->name('classes.store')
            ->can('create', ClassTemplate::class);
        Route::get('/classes/{classTemplate}/edit', [ClassTemplateController::class, 'edit'])
            ->name('classes.edit')
            ->can('update', 'classTemplate');
        Route::put('/classes/{classTemplate}', [ClassTemplateController::class, 'update'])
            ->name('classes.update')
            ->can('update', 'classTemplate');

        Route::get('/schedule', [ClassSessionController::class, 'index'])
            ->name('schedule.index')
            ->can('viewAny', ClassSession::class);
        Route::get('/schedule/create', [ClassSessionController::class, 'create'])
            ->name('schedule.create')
            ->can('create', ClassSession::class);
        Route::post('/schedule', [ClassSessionController::class, 'store'])
            ->name('schedule.store')
            ->can('create', ClassSession::class);
        Route::get('/schedule/{classSession}/edit', [ClassSessionController::class, 'edit'])
            ->name('schedule.edit')
            ->can('update', 'classSession');
        Route::put('/schedule/{classSession}', [ClassSessionController::class, 'update'])
            ->name('schedule.update')
            ->can('update', 'classSession');

        Route::post('/schedule/{classSession}/bookings', [BookingController::class, 'store'])
            ->name('bookings.store')
            ->can('create', [Booking::class, 'classSession']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])
            ->name('bookings.destroy')
            ->can('cancel', 'booking');

        Route::post('/schedule/{classSession}/waitlist', [WaitlistController::class, 'store'])
            ->name('waitlist.store')
            ->can('create', [WaitlistEntry::class, 'classSession']);
        Route::delete('/waitlist/{waitlistEntry}', [WaitlistController::class, 'destroy'])
            ->name('waitlist.destroy')
            ->can('delete', 'waitlistEntry');
    });
});
