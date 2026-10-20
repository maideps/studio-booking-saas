<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Studio\UpdateStudioSettingsRequest;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current studio's settings. The studio always comes from the tenant
 * context, never from the request, so an owner can only edit their own.
 */
final class StudioSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function edit(Request $request): Response
    {
        $tenant = $this->authorizedTenant();

        return Inertia::render('Settings/Edit', [
            'settings' => [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'contact_email' => $tenant->contact_email,
                'cancellation_window_hours' => $tenant->cancellation_window_hours,
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(UpdateStudioSettingsRequest $request): RedirectResponse
    {
        $this->authorizedTenant()->update($request->validated());

        return redirect()->route('settings.edit')->with('status', __('Settings saved.'));
    }

    private function authorizedTenant(): Tenant
    {
        $tenant = $this->context->currentOrFail();
        Gate::authorize('update', $tenant);

        return $tenant;
    }
}
