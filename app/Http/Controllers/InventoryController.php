<?php

namespace App\Http\Controllers;

use App\Actions\RecordStockMovement;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, Team $currentTeam, Branch $branch): Response
    {
        Gate::authorize('viewAny', [Branch::class, $currentTeam]);

        return Inertia::render('inventory/index', [
            'branch' => $branch->only(['id', 'name', 'is_active']),
            'variants' => ProductVariant::query()->where('team_id', $currentTeam->id)
                ->with(['product:id,name,is_active', 'stocks' => fn ($query) => $query->where('branch_id', $branch->id)])
                ->orderBy('sku')->paginate(24),
            'movements' => StockMovement::query()->whereHas('stock', fn ($query) => $query->where('branch_id', $branch->id))
                ->with(['user:id,name', 'stock.variant:id,name,sku,product_id', 'stock.variant.product:id,name'])
                ->orderByDesc('id')->limit(20)->get(),
            'canManage' => $request->user()->can('update', $branch),
        ]);
    }

    public function store(StoreStockMovementRequest $request, Team $currentTeam, Branch $branch, RecordStockMovement $recordMovement): RedirectResponse
    {
        $variant = ProductVariant::query()->where('team_id', $currentTeam->id)->findOrFail($request->integer('variant_id'));
        $recordMovement->handle($branch, $variant, $request->user(), $request->validated('type'), $request->integer('quantity'), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Movimiento registrado.']);

        return back();
    }
}
