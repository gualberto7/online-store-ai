<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBranchRequest;
use App\Models\Branch;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Branch::class, $currentTeam]);

        return Inertia::render('branches/index', [
            'branches' => $currentTeam->branches()->orderBy('name')->orderBy('id')
                ->get(['id', 'name', 'address', 'is_active']),
            'canManage' => $request->user()->can('create', [Branch::class, $currentTeam]),
        ]);
    }

    public function create(Team $currentTeam): Response
    {
        Gate::authorize('create', [Branch::class, $currentTeam]);

        return Inertia::render('branches/form');
    }

    public function store(SaveBranchRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->branches()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sucursal creada.']);

        return to_route('branches.index', ['current_team' => $currentTeam]);
    }

    public function edit(Team $currentTeam, Branch $branch): Response
    {
        Gate::authorize('update', $branch);

        return Inertia::render('branches/form', [
            'branch' => $branch->only(['id', 'name', 'address', 'is_active']),
        ]);
    }

    public function update(SaveBranchRequest $request, Team $currentTeam, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sucursal actualizada.']);

        return to_route('branches.index', ['current_team' => $currentTeam]);
    }
}
