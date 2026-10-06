<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in_to_access_inventory(): void
    {
        $branch = Branch::factory()->create();

        $this->get(route('branches.inventory.index', [$branch->team, $branch]))->assertRedirect(route('login'));
    }

    public function test_inventory_includes_untracked_variants_and_only_the_selected_branch_stock(): void
    {
        [$user, $branch, $variant] = $this->inventory(TeamRole::Member);
        ProductVariant::factory()->for($variant->product)->create(['sku' => 'ZZ-UNTRACKED']);
        $otherBranch = Branch::factory()->for($branch->team)->create();
        Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        Stock::factory()->create(['branch_id' => $otherBranch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 30]);
        ProductVariant::factory()->create();

        $this->actingAs($user)->get(route('branches.inventory.index', [$branch->team, $branch]))
            ->assertInertia(fn (Assert $page) => $page->component('inventory/index')
                ->has('variants.data', 2)->where('variants.data.0.stocks.0.quantity', 5)
                ->has('variants.data.1.stocks', 0)->where('canManage', false));
    }

    public function test_receipts_create_stock_and_record_the_actor_reason_and_balances(): void
    {
        [$user, $branch, $variant] = $this->inventory();

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'receipt', 'quantity' => 10, 'reason' => 'Compra inicial',
            'team_id' => 999, 'branch_id' => 999, 'user_id' => 999,
        ])->assertRedirect();

        $stock = $branch->stocks()->sole();
        $this->assertSame(10, $stock->quantity);
        $this->assertSame($variant->id, $stock->product_variant_id);
        $this->assertDatabaseHas('stock_movements', ['stock_id' => $stock->id, 'user_id' => $user->id, 'type' => 'receipt', 'quantity_change' => 10, 'balance_before' => 0, 'balance_after' => 10, 'reason' => 'Compra inicial']);
    }

    #[TestWith(['receipt', 3, 13, 3])]
    #[TestWith(['issue', 4, 6, -4])]
    #[TestWith(['issue', 10, 0, -10])]
    #[TestWith(['adjustment', 7, 7, -3])]
    #[TestWith(['adjustment', 0, 0, -10])]
    #[TestWith(['adjustment', 12, 12, 2])]
    public function test_movements_update_balances_and_keep_a_history(string $type, int $quantity, int $expectedBalance, int $expectedChange): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $stock = Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 10]);

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => $type, 'quantity' => $quantity, 'reason' => 'Movimiento',
        ])->assertRedirect();

        $this->assertSame($expectedBalance, $stock->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['stock_id' => $stock->id, 'quantity_change' => $expectedChange, 'balance_before' => 10, 'balance_after' => $expectedBalance]);
    }

    public function test_insufficient_stock_does_not_change_the_balance_or_history(): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $stock = Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 3]);

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'issue', 'quantity' => 4, 'reason' => 'Salida',
        ])->assertSessionHasErrors(['quantity' => 'No hay existencias suficientes para esta salida.']);

        $this->assertSame(3, $stock->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_untracked_variant_cannot_be_issued_and_rolls_back_stock_creation(): void
    {
        [$user, $branch, $variant] = $this->inventory();

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'issue', 'quantity' => 1, 'reason' => 'Salida',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_is_independent_between_branches(): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $otherBranch = Branch::factory()->for($branch->team)->create();
        $otherStock = Stock::factory()->create(['branch_id' => $otherBranch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 20]);

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'receipt', 'quantity' => 5, 'reason' => 'Compra',
        ])->assertRedirect();

        $this->assertSame(20, $otherStock->fresh()->quantity);
        $this->assertDatabaseHas('stocks', ['branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
    }

    public function test_members_can_read_but_cannot_register_movements(): void
    {
        [$user, $branch, $variant] = $this->inventory(TeamRole::Member);

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'receipt', 'quantity' => 5, 'reason' => 'Compra',
        ])->assertForbidden();

        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cross_business_branches_cannot_be_accessed(): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $otherBranch = Branch::factory()->create();

        $this->actingAs($user)->get(route('branches.inventory.index', [$branch->team, $otherBranch]))->assertNotFound();
        $this->post(route('branches.inventory.store', [$branch->team, $otherBranch]), [
            'variant_id' => $variant->id, 'type' => 'receipt', 'quantity' => 5, 'reason' => 'Compra',
        ])->assertNotFound();

        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_variants_from_another_business_cannot_be_added(): void
    {
        [$user, $branch] = $this->inventory();
        $otherVariant = ProductVariant::factory()->create();

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $otherVariant->id, 'type' => 'receipt', 'quantity' => 5, 'reason' => 'Compra',
        ])->assertSessionHasErrors('variant_id');

        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[TestWith(['receipt', 0])]
    #[TestWith(['issue', -1])]
    #[TestWith(['adjustment', -1])]
    #[TestWith(['receipt', '1.5'])]
    #[TestWith(['receipt', 2147483648])]
    public function test_invalid_quantities_are_rejected(string $type, int|string $quantity): void
    {
        [$user, $branch, $variant] = $this->inventory();

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => $type, 'quantity' => $quantity, 'reason' => 'Compra',
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_missing_fields_and_invalid_types_are_rejected(): void
    {
        [$user, $branch, $variant] = $this->inventory();

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [])
            ->assertSessionHasErrors(['variant_id', 'type', 'quantity', 'reason']);
        $this->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => 'invalid', 'quantity' => 1, 'reason' => str_repeat('a', 501),
        ])->assertSessionHasErrors(['type', 'reason']);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[TestWith([2147483647, 'receipt', 1, 'El saldo supera el máximo permitido.'])]
    #[TestWith([10, 'adjustment', 10, 'El movimiento debe cambiar las existencias.'])]
    public function test_overflow_and_unchanged_adjustments_are_rejected(int $balance, string $type, int $quantity, string $message): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $stock = Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => $balance]);

        $this->actingAs($user)->post(route('branches.inventory.store', [$branch->team, $branch]), [
            'variant_id' => $variant->id, 'type' => $type, 'quantity' => $quantity, 'reason' => 'Conteo',
        ])->assertSessionHasErrors(['quantity' => $message]);

        $this->assertSame($balance, $stock->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_history_is_scoped_to_the_branch_and_includes_actor_and_variant(): void
    {
        [$user, $branch, $variant] = $this->inventory();
        $stock = Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $stock->movements()->create(['user_id' => $user->id, 'type' => 'receipt', 'quantity_change' => 5, 'balance_before' => 0, 'balance_after' => 5, 'reason' => 'Compra']);
        $otherStock = Stock::factory()->create();
        $otherStock->movements()->create(['user_id' => $user->id, 'type' => 'receipt', 'quantity_change' => 1, 'balance_before' => 0, 'balance_after' => 1, 'reason' => 'Otra sucursal']);

        $this->actingAs($user)->get(route('branches.inventory.index', [$branch->team, $branch]))
            ->assertInertia(fn (Assert $page) => $page->has('movements', 1)
                ->where('movements.0.user.name', $user->name)->where('movements.0.stock.variant.sku', 'AAA-1'));
    }

    /** @return array{User, Branch, ProductVariant} */
    private function inventory(TeamRole $role = TeamRole::Admin): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => $role->value]);
        $product = Product::factory()->for($branch->team)->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'AAA-1']);

        return [$user, $branch, $variant];
    }
}
