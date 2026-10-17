<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceStorefrontOrder
{
    public function __construct(private CreateOrder $createOrder, private ConfirmOrder $confirmOrder) {}

    /** @param array{branch_id: int, customer_name: string, customer_email: string, customer_phone: string, notes: string|null, token: string} $data */
    public function handle(StorefrontCart $cart, array $data): Order
    {
        $team = $cart->team();
        abort_unless($team !== null, 404);

        return DB::transaction(function () use ($cart, $data, $team): Order {
            $branch = Branch::query()->where('team_id', $team->id)->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();
            $existing = Order::query()->where('checkout_token', $data['token'])->where('team_id', $team->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            if (! $branch->is_active) {
                throw ValidationException::withMessages(['branch_id' => 'La sucursal está inactiva.']);
            }
            $quantities = $cart->quantities();
            if ($quantities === [] || $cart->variants()->count() !== count($quantities)) {
                throw ValidationException::withMessages(['cart' => 'Revisa el carrito: hay productos no disponibles o está vacío.']);
            }
            $items = [];
            foreach ($quantities as $id => $quantity) {
                $items[] = ['variant_id' => $id, 'quantity' => $quantity];
            }
            $order = $this->createOrder->handle($team, null, ['branch_id' => $branch->id, 'customer_name' => $data['customer_name'], 'notes' => $data['notes'], 'items' => $items]);
            $order->customer_email = $data['customer_email'];
            $order->customer_phone = $data['customer_phone'];
            $order->checkout_token = $data['token'];
            $order->save();

            return $this->confirmOrder->handle($order, null);
        }, attempts: 3);
    }
}
