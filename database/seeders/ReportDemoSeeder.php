<?php

namespace Database\Seeders;

use App\Actions\CancelOrder;
use App\Actions\CompleteOrder;
use App\Actions\ConfirmOrder;
use App\Actions\CreateOrder;
use App\Actions\RecordStockMovement;
use App\Actions\ReturnOrderItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ReportDemoSeeder extends Seeder
{
    private const MARKER = '[DEMO-REPORTS:v1]';

    public function run(): void
    {
        $end = CarbonImmutable::now()->startOfSecond();
        $start = $end->subMonthsNoOverflow(6)->startOfMonth();
        $team = Team::query()->where('slug', 'tienda-demo')->where('is_personal', false)->firstOrFail();
        $user = $team->members()->where('email', 'admin@test.com')->firstOrFail();
        $variants = ProductVariant::query()->where('team_id', $team->id)->where('is_active', true)
            ->where('price', '>', 0)->where('price', '<=', 1500)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))->orderBy('id')->get();
        if ($variants->isEmpty()) {
            throw new RuntimeException('Se necesita al menos una variante activa con precio entre 0 y 1500 Bs. Ejecuta primero los seeders del catálogo.');
        }

        [$orders, $refunds] = DB::transaction(function () use ($team, $user, $variants, $start, $end): array {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $branches = [];
            $events = [];
            foreach (['Centro', 'Norte'] as $branchIndex => $name) {
                $branch = $this->at($start, fn () => $team->branches()->firstOrCreate(
                    ['name' => 'Demo · '.$name, 'address' => self::MARKER.' Sucursal de demostración para reportes.'],
                    ['is_active' => true],
                ));
                $branch->setRelation('team', $team);
                if (! $branch->is_active) {
                    continue;
                }
                $branches[] = $branch;
                foreach ($variants as $index => $variant) {
                    if (! $branch->stocks()->where('product_variant_id', $variant->id)->exists()) {
                        $this->at($start->setTime(7, 0), fn () => app(RecordStockMovement::class)->handle(
                            $branch, $variant, $user, 'receipt', 30 + ($index % 5) * 10, self::MARKER.' Existencias iniciales de demostración.',
                        ));
                    }
                }
                for ($day = $start; $day->startOfDay()->lte($end); $day = $day->addDay()) {
                    if ($day->isSunday() && $day->day !== 1) {
                        continue;
                    }
                    $count = 1 + ($day->day + $day->month + $branchIndex) % 3;
                    if ($day->month >= 9 && $day->day % 7 === 0) {
                        $count++;
                    }
                    for ($slot = 0; $slot < $count; $slot++) {
                        $at = $day->setTime(8 + $slot * 2, $branchIndex * 10);
                        if ($at->addMinutes(45)->gt($end)) {
                            continue;
                        }
                        $token = $this->token("{$team->id}/{$branchIndex}/{$day->toDateString()}/{$slot}");
                        $seed = (int) hexdec(substr(hash('sha256', $token), 0, 7));
                        $kind = $seed % 20 === 0 ? 'cancelled' : 'completed';
                        if ($day->gte($end->subDays(7)->startOfDay()) && in_array($seed % 20, [1, 2], true)) {
                            $kind = $seed % 20 === 1 ? 'draft' : 'pending';
                        }
                        $events[] = ['type' => 'order', 'at' => $at, 'branch' => $branch, 'token' => $token, 'seed' => $seed, 'kind' => $kind];
                        $returnAt = $at->addDays(2)->setTime(18, 30);
                        if ($kind === 'completed' && $seed % 11 === 0 && $returnAt->lte($end)) {
                            $events[] = ['type' => 'return', 'at' => $returnAt, 'branch' => $branch, 'token' => $token, 'seed' => $seed, 'kind' => $kind];
                        }
                    }
                }
            }
            usort($events, static fn (array $left, array $right): int => $left['at'] <=> $right['at']);
            $orders = 0;
            $refunds = 0;
            foreach ($events as $event) {
                if ($event['type'] === 'order') {
                    $orders += (int) $this->seedOrder($event['branch'], $user, $variants, $event['at'], $event['token'], $event['seed'], $event['kind']);
                } else {
                    $refunds += (int) $this->seedReturn($user, $event['at'], $event['token'], $event['seed']);
                }
            }
            foreach ($branches as $branch) {
                foreach ($variants as $index => $variant) {
                    if ($index % 8 !== 0) {
                        continue;
                    }
                    $stock = $branch->stocks()->where('product_variant_id', $variant->id)->first();
                    $reason = self::MARKER.' Salida de muestra para agotados '.$end->toDateString().'.';
                    if ($stock !== null && $stock->quantity > 0 && ! $stock->movements()->where('reason', $reason)->exists()) {
                        $this->at($end, fn () => app(RecordStockMovement::class)->handle($branch, $variant, $user, 'issue', $stock->quantity, $reason));
                    }
                }
            }

            return [$orders, $refunds];
        });

        $this->command->info("Período demo: {$start->toDateString()} al {$end->toDateString()}. Pedidos nuevos: {$orders}; devoluciones nuevas: {$refunds}.");
    }

    /** @param Collection<int, ProductVariant> $variants */
    private function seedOrder(Branch $branch, User $user, Collection $variants, CarbonImmutable $at, string $token, int $seed, string $kind): bool
    {
        if (Order::query()->where('checkout_token', $token)->exists()) {
            return false;
        }
        $items = [];
        for ($index = 0; $index <= $seed % 3; $index++) {
            $poolSize = $seed % 3 === 0 ? $variants->count() : min(12, $variants->count());
            $variant = $variants->slice(($seed + $index * 17) % $poolSize, 1)->sole();
            $quantity = 1 + intdiv($seed, 7 + $index) % 3;
            $items[$variant->id] = ['variant_id' => $variant->id, 'quantity' => ($items[$variant->id]['quantity'] ?? 0) + $quantity];
        }
        $order = $this->at($at, fn () => app(CreateOrder::class)->handle($branch->team, $user, [
            'branch_id' => $branch->id, 'customer_name' => 'Cliente demo '.str_pad((string) ($seed % 200 + 1), 3, '0', STR_PAD_LEFT),
            'notes' => self::MARKER.' Operación ficticia para reportes.', 'items' => array_values($items),
        ]));
        $this->at($at, fn () => $order->forceFill(['checkout_token' => $token])->save());
        if ($kind === 'draft') {
            return true;
        }
        foreach ($items as $item) {
            $variant = $variants->where('id', $item['variant_id'])->sole();
            $balance = (int) Stock::query()->where('branch_id', $branch->id)->where('product_variant_id', $variant->id)->value('quantity');
            if ($balance < $item['quantity']) {
                $this->at($at->addMinutes(5), fn () => app(RecordStockMovement::class)->handle($branch, $variant, $user, 'receipt', 50, self::MARKER.' Reposición de demostración.'));
            }
        }
        $order = $this->at($at->addMinutes(10), fn () => app(ConfirmOrder::class)->handle($order, $user));
        if ($kind === 'cancelled') {
            $this->at($at->addMinutes(30), fn () => app(CancelOrder::class)->handle($order, $user, self::MARKER.' Cancelación de demostración.'));
        } elseif ($kind === 'completed') {
            $method = PaymentMethod::cases()[$seed % 3];
            $this->at($at->addMinutes(45), fn () => app(CompleteOrder::class)->handle($order, $user, $method, $method === PaymentMethod::Cash ? null : 'DEMO-'.substr($token, 0, 8)));
        }

        return true;
    }

    private function seedReturn(User $user, CarbonImmutable $at, string $token, int $seed): bool
    {
        $order = Order::query()->where('checkout_token', $token)->where('status', OrderStatus::Confirmed)->whereNotNull('completed_at')->first();
        $returnToken = $this->token($token.'/return');
        if ($order === null || $order->returns()->where('request_token', $returnToken)->exists()) {
            return false;
        }
        $item = $order->items()->orderBy('id')->firstOrFail();
        if ((int) $order->returns()->where('order_item_id', $item->id)->sum('quantity') >= $item->quantity) {
            return false;
        }
        $this->at($at, fn () => app(ReturnOrderItem::class)->handle($order, $user, [
            'order_item_id' => $item->id, 'quantity' => 1, 'restock' => $seed % 2 === 0,
            'reason' => self::MARKER.' Devolución de demostración.', 'payment_method' => $order->payment_method ?? PaymentMethod::Cash,
            'payment_reference' => 'DEMO-REEMBOLSO', 'request_token' => $returnToken,
        ]));

        return true;
    }

    private function token(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'online-store/demo-reports/v1/'.$key)->toString();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function at(CarbonImmutable $time, Closure $callback): mixed
    {
        $previous = Date::getTestNow();
        Date::setTestNow($time);
        try {
            return $callback();
        } finally {
            Date::setTestNow($previous);
        }
    }
}
