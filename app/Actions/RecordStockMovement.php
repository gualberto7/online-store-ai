<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    public function handle(Branch $branch, ProductVariant $variant, User $user, string $type, int $quantity, string $reason): StockMovement
    {
        return DB::transaction(function () use ($branch, $variant, $user, $type, $quantity, $reason): StockMovement {
            /** Lock the branch first to serialize creation of previously untracked stock. */
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            abort_unless($variant->team_id === $branch->team_id, 404);

            $stock = $branch->stocks()->where('product_variant_id', $variant->id)->lockForUpdate()->first();
            if ($stock === null) {
                $stock = new Stock(['quantity' => 0]);
                $stock->product_variant_id = $variant->id;
                $stock->team_id = $branch->team_id;
                $branch->stocks()->save($stock);
            }

            $before = $stock->quantity;
            $after = match ($type) {
                'receipt' => $before + $quantity,
                'issue' => $before - $quantity,
                'adjustment' => $quantity,
                default => throw ValidationException::withMessages(['type' => 'Tipo de movimiento inválido.']),
            };

            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => 'No hay existencias suficientes para esta salida.']);
            }
            if ($after > 2147483647) {
                throw ValidationException::withMessages(['quantity' => 'El saldo supera el máximo permitido.']);
            }
            if ($after === $before) {
                throw ValidationException::withMessages(['quantity' => 'El movimiento debe cambiar las existencias.']);
            }

            $stock->update(['quantity' => $after]);

            return $stock->movements()->create([
                'user_id' => $user->id,
                'type' => $type,
                'quantity_change' => $after - $before,
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $reason,
            ]);
        }, attempts: 3);
    }
}
