<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Category::class, $currentTeam]);

        return Inertia::render('categories/index', [
            'categories' => $currentTeam->categories()->orderBy('name')->orderBy('id')
                ->withCount('products')->get(['id', 'name', 'description', 'is_active']),
            'canManage' => $request->user()->can('create', [Category::class, $currentTeam]),
        ]);
    }

    public function create(Team $currentTeam): Response
    {
        Gate::authorize('create', [Category::class, $currentTeam]);

        return Inertia::render('categories/form');
    }

    public function store(SaveCategoryRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->categories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);

        return to_route('categories.index', ['current_team' => $currentTeam]);
    }

    public function edit(Team $currentTeam, Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('categories/form', [
            'category' => $category->only(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function update(SaveCategoryRequest $request, Team $currentTeam, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría actualizada.']);

        return to_route('categories.index', ['current_team' => $currentTeam]);
    }
}
