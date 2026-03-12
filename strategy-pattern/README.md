# Strategy Pattern

## Overview

The Strategy pattern defines a family of interchangeable algorithms, each in its own class, and selects one at runtime. The caller works against a shared interface; the implementation can be swapped without touching the caller.

In backend systems, the pattern appears wherever the same operation needs to be performed differently depending on context — different discount rules, different tax calculations, different export formats.

---

## The Problem

Without the Strategy pattern, conditional logic that varies by case accumulates in a single method:

```php
// ❌ Grows with every new discount type
public function calculate(Order $order, string $discountType): float
{
    if ($discountType === 'percentage') {
        return $order->subtotal * 0.9;
    } elseif ($discountType === 'fixed') {
        return max(0, $order->subtotal - 20);
    } elseif ($discountType === 'loyalty') {
        return $order->subtotal * (1 - ($order->user->loyaltyPoints() * 0.001));
    }

    return $order->subtotal;
}
```

Each new discount type requires modifying this method. It accumulates knowledge of every strategy, grows harder to test, and becomes a change magnet.

---

## Example

See [`example.php`](./example.php) for a discount calculation system with three concrete strategies and a service that applies any of them uniformly.

---

## Solution

Extract each case into a class that implements a shared interface. Resolve the strategy externally and inject it into the service.

```php
// ✅ Shared interface
interface DiscountStrategyInterface
{
    public function apply(Order $order): float;
}

// ✅ Each strategy is a small, focused class
final class PercentageDiscount implements DiscountStrategyInterface
{
    public function __construct(private readonly float $percentage) {}

    public function apply(Order $order): float
    {
        return round($order->subtotal * (1 - $this->percentage / 100), 2);
    }
}

// ✅ Service receives the resolved strategy — no knowledge of which type
final class OrderPricingService
{
    public function calculateTotal(Order $order, DiscountStrategyInterface $discount): float
    {
        return $discount->apply($order);
    }
}
```

---

## Why It Matters

- **Open/Closed**: new strategies are added as new classes without modifying existing ones
- **Testability**: each strategy is a small, isolated class with a single responsibility
- **Readability**: the calculation logic for each case is named and self-contained

---

## Trade-offs

If there are only two cases and they are unlikely to grow, a simple `if/else` is clearer than three files and an interface. The Strategy pattern introduces structure worth maintaining; apply it when the number of variants is growing or each variant is complex enough to warrant its own class.

Avoid using a strategy class as a vehicle for a one-liner. A `NoDiscountStrategy` that returns `$order->subtotal` may seem excessive, but it makes the null case explicit and allows it to be injected like any other strategy.

---

## Production Notes

**Resolve the strategy before entering business logic.**  
The service that applies the discount should not decide which strategy to use — that selection belongs in a factory or the calling code. Pass the strategy as a dependency.

**Strategies should be stateless when possible.**  
If a strategy carries no per-instance state, it can be safely reused across calls. Stateful strategies require careful handling in containers and service providers.

**The strategy interface should be narrow.**  
A strategy with five methods is likely doing too much. Each strategy interface should represent one replaceable behaviour.
