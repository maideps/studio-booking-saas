<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Classes\ClassTemplateRequest;
use App\Models\ClassTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's class templates. Authorization is on the routes
 * (ClassTemplatePolicy).
 */
final class ClassTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return Inertia::render('Classes/Index', [
            'templates' => ClassTemplate::query()->orderBy('name')->get()->map(fn (ClassTemplate $template): array => [
                ...$this->fields($template),
                'canUpdate' => $actor->can('update', $template),
            ]),
            'canCreate' => $actor->can('create', ClassTemplate::class),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Classes/Form', ['template' => null]);
    }

    public function store(ClassTemplateRequest $request): RedirectResponse
    {
        ClassTemplate::query()->create($request->validated());

        return redirect()->route('classes.index')->with('status', __('Class created.'));
    }

    public function edit(ClassTemplate $classTemplate): Response
    {
        return Inertia::render('Classes/Form', ['template' => $this->fields($classTemplate)]);
    }

    public function update(ClassTemplateRequest $request, ClassTemplate $classTemplate): RedirectResponse
    {
        $classTemplate->update($request->validated());

        return redirect()->route('classes.index')->with('status', __('Class updated.'));
    }

    /**
     * @return array{id: int, name: string, description: string|null, duration_minutes: int, capacity: int}
     */
    private function fields(ClassTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'duration_minutes' => $template->duration_minutes,
            'capacity' => $template->capacity,
        ];
    }
}
