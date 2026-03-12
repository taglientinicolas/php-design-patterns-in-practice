<?php

declare(strict_types=1);

namespace App\Discounts;

use App\Models\Order;
use InvalidArgumentException;

// ----------------------------------------------------------------------------
// CONTRACT
// All strategies implement this interface.
// The service only knows about DiscountStrategyInterface — not the concrete types.
// ----------------------------------------------------------------------------

interface DiscountStrategyInterface
{
    public function apply(Order $order): float;
}

// ----------------------------------------------------------------------------
// STRATEGIES
// Each strategy encapsulates one discount calculation.
// Isolated, independently testable, open to extension without modification.
// ----------------------------------------------------------------------------

final class NoDiscount implements DiscountStrategyInterface
{
    public function apply(Order $order): float
    {
        return $order->subtotal;
    }
}

final class PercentageDiscount implements DiscountStrategyInterface
{
    public function __construct(private readonly float $percentage) {}

    public function apply(Order $order): float
    {
        return round($order->subtotal * (1 - $this->percentage / 100), 2);
    }
}

final class FixedAmountDiscount implements DiscountStrategyInterface
{
    public function __construct(private readonly float $amount) {}

    public function apply(Order $order): float
    {
        return max(0.0, round($order->subtotal - $this->amount, 2));
    }
}

final class LoyaltyDiscount implements DiscountStrategyInterface
{
    // Each loyalty point is worth 0.1% discount, capped at 20%.
    private const MAX_DISCOUNT_PERCENT = 20.0;
    private const POINT_VALUE          = 0.001;

    public function apply(Order $order): float
    {
        $points       = $order->user->loyaltyPoints();
        $discountRate = min($points * self::POINT_VALUE, self::MAX_DISCOUNT_PERCENT / 100);

        return round($order->subtotal * (1 - $discountRate), 2);
    }
}

// ----------------------------------------------------------------------------
// FACTORY — resolves the strategy from a key
// The service receives a resolved strategy, not a string key.
// ----------------------------------------------------------------------------

final class DiscountStrategyFactory
{
    public function make(string $type, array $params = []): DiscountStrategyInterface
    {
        return match ($type) {
            'none'    => new NoDiscount(),
            'loyalty' => new LoyaltyDiscount(),

            'percentage' => isset($params['percentage'])
                ? new PercentageDiscount((float) $params['percentage'])
                : throw new InvalidArgumentException(
                    "Discount type 'percentage' requires a 'percentage' param."
                ),

            'fixed' => isset($params['amount'])
                ? new FixedAmountDiscount((float) $params['amount'])
                : throw new InvalidArgumentException(
                    "Discount type 'fixed' requires an 'amount' param."
                ),

            default => throw new InvalidArgumentException(
                "Unknown discount type: '{$type}'."
            ),
        };
    }
}

// ----------------------------------------------------------------------------
// SERVICE — applies whichever strategy it receives
// Has no knowledge of any specific discount type.
// ----------------------------------------------------------------------------

final class OrderPricingService
{
    public function calculateTotal(Order $order, DiscountStrategyInterface $discount): float
    {
        $discounted = $discount->apply($order);
        $tax        = $this->calculateTax($discounted, $order->taxRate());

        return round($discounted + $tax, 2);
    }

    private function calculateTax(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }
}

// ----------------------------------------------------------------------------
// USAGE
// The factory resolves the strategy; the service applies it.
// ----------------------------------------------------------------------------

$factory = new DiscountStrategyFactory();
$service = new OrderPricingService();

// Promo code applied by the controller / checkout flow:
$strategy = $factory->make('percentage', ['percentage' => 15]);
$total    = $service->calculateTotal($order, $strategy);

// Loyalty discount for returning customers:
$strategy = $factory->make('loyalty');
$total    = $service->calculateTotal($order, $strategy);

// No discount:
$strategy = $factory->make('none');
$total    = $service->calculateTotal($order, $strategy);
