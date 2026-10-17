<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BrowseOrdersTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['Ana'])]
    #[TestWith(['7654'])]
    public function test_search_matches_customer_or_phone_without_exposing_other_teams(string $search): void
    {
        [$user, $branch] = $this->catalog();
        $order = Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'customer_name' => 'Ana Pérez', 'customer_phone' => '76543210']);
        Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'customer_name' => 'Luis']);
        Order::factory()->create(['customer_name' => 'Ana Pérez', 'customer_phone' => '76543210']);

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'q' => $search]))
            ->assertInertia(fn (Assert $page) => $page->component('orders/index')->has('orders.data', 1)->where('orders.data.0.id', $order->id));
    }

    public function test_search_accepts_order_numbers_with_or_without_hash(): void
    {
        [$user, $branch] = $this->catalog();
        $order = Order::factory()->for($branch)->create(['team_id' => $branch->team_id]);
        $foreign = Order::factory()->create();

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'q' => '#'.$order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id));
        $this->get(route('orders.index', [$branch->team, 'q' => (string) $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.id', $order->id));
        $this->get(route('orders.index', [$branch->team, 'q' => '#'.$foreign->id]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
    }

    #[TestWith(['draft', 'draft', false])]
    #[TestWith(['pending', 'confirmed', false])]
    #[TestWith(['completed', 'confirmed', true])]
    #[TestWith(['cancelled', 'cancelled', false])]
    public function test_status_filters_distinguish_pending_collection_from_completed_orders(string $filter, string $status, bool $completed): void
    {
        [$user, $branch] = $this->catalog();
        foreach (['draft', 'confirmed', 'cancelled'] as $state) {
            Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'status' => $state]);
        }
        Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'status' => OrderStatus::Confirmed, 'completed_at' => now()]);

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'status' => $filter]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.status', $status)
                ->where('orders.data.0.completed_at', fn ($value): bool => $completed ? $value !== null : $value === null));
    }

    public function test_branch_filter_includes_inactive_branch_history_and_only_own_branches(): void
    {
        [$user, $branch] = $this->catalog();
        $branch->update(['is_active' => false]);
        $order = Order::factory()->for($branch)->create(['team_id' => $branch->team_id]);
        $other = Branch::factory()->for($branch->team)->create();
        Order::factory()->for($other)->create(['team_id' => $branch->team_id]);
        Branch::factory()->create();

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'branch' => $branch->id]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $order->id)
                ->has('branches', 2)->where('filters.branch', $branch->id));
    }

    public function test_date_range_includes_both_boundary_days_and_excludes_adjacent_days(): void
    {
        [$user, $branch] = $this->catalog();
        foreach (['2026-10-01 23:59:59', '2026-10-02 00:00:00', '2026-10-03 23:59:59', '2026-10-04 00:00:00'] as $createdAt) {
            Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'created_at' => $createdAt]);
        }

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'from' => '2026-10-02', 'to' => '2026-10-03']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)->where('orders.total', 2));
        $this->get(route('orders.index', [$branch->team, 'from' => '2026-10-04']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('orders.index', [$branch->team, 'to' => '2026-10-01']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_filters_combine_and_pagination_preserves_them(): void
    {
        [$user, $branch] = $this->catalog();
        Order::factory()->for($branch)->count(25)->create(['team_id' => $branch->team_id, 'status' => OrderStatus::Confirmed,
            'customer_name' => 'Ana', 'created_at' => '2026-10-02 10:00:00']);
        Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'customer_name' => 'Ana']);
        $filters = ['q' => 'Ana', 'branch' => $branch->id, 'status' => 'pending', 'from' => '2026-10-01', 'to' => '2026-10-03'];

        $this->actingAs($user)->get(route('orders.index', [$branch->team, ...$filters]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 24)->where('orders.total', 25)->where('filters', $filters)
                ->where('orders.next_page_url', function (string $url) use ($filters): bool {
                    parse_str(parse_url($url, PHP_URL_QUERY), $query);

                    return $query === [...array_map(strval(...), $filters), 'page' => '2'];
                }));
        $this->get(route('orders.index', [$branch->team, ...$filters, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    #[TestWith(['q', ['invalid']])]
    #[TestWith(['q', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'])]
    #[TestWith(['status', 'confirmed'])]
    #[TestWith(['from', '2026-02-30'])]
    #[TestWith(['to', 'not-a-date'])]
    #[TestWith(['page', 0])]
    public function test_invalid_filters_are_rejected(string $field, mixed $value): void
    {
        [$user, $branch] = $this->catalog();

        $this->actingAs($user)->get(route('orders.index', [$branch->team, $field => $value]))->assertSessionHasErrors($field);
    }

    public function test_reversed_dates_and_foreign_branches_are_rejected(): void
    {
        [$user, $branch] = $this->catalog();
        $foreign = Branch::factory()->create();

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'branch' => $foreign->id, 'from' => '2026-10-03', 'to' => '2026-10-01']))
            ->assertSessionHasErrors(['branch', 'to']);
    }

    public function test_search_is_bound_and_handles_numbers_outside_the_database_id_range(): void
    {
        [$user, $branch] = $this->catalog();
        Order::factory()->for($branch)->create(['team_id' => $branch->team_id, 'customer_name' => "O'Connor"]);

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'q' => "O'Connor"]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('orders.index', [$branch->team, 'q' => "' OR 1=1 --"]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->get(route('orders.index', [$branch->team, 'q' => str_repeat('9', 100)]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
    }

    public function test_nonmembers_cannot_query_a_teams_orders(): void
    {
        [, $branch] = $this->catalog();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('orders.index', [$branch->team, 'q' => 'Ana']))->assertForbidden();
    }

    /** @return array{User, Branch} */
    private function catalog(): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => TeamRole::Member->value]);

        return [$user, $branch];
    }
}
