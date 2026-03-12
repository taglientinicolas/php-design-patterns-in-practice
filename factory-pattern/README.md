# Factory Pattern

## Overview

The Factory pattern encapsulates object creation logic. Instead of instantiating a concrete class directly at the call site, a factory centralises the decision of *which* class to create and *how* to configure it. This decouples the caller from specific implementations.

---

## The Problem It Helps Solve

When a system supports multiple implementations of the same behaviour — multiple notification channels, multiple payment providers, multiple file parsers — the creation logic tends to accumulate as `if/switch` chains at the point of use:

```php
// ❌ Creation logic scattered across the codebase
if ($channel === 'email') {
    $sender = new EmailNotificationSender($config['email']);
} elseif ($channel === 'sms') {
    $sender = new SmsNotificationSender($config['sms']);
} elseif ($channel === 'slack') {
    $sender = new SlackNotificationSender($config['slack']);
}
```

When a new channel is added, every occurrence of this block must be updated. The factory consolidates it.

---

## Example

See [`example.php`](./example.php) for a `NotificationSenderFactory` that resolves the correct sender based on a channel name.

---

## Why This Pattern Can Help

- **Centralised creation logic**: adding a new implementation means updating the factory, not every call site
- **Decoupled callers**: services depend on the interface (`NotificationSenderInterface`), not on concrete classes
- **Testable**: the factory can be replaced or bypassed in tests without affecting service logic

---

## Trade-offs / When Not to Overuse It

A factory class is worth its existence only when there are multiple implementations to choose between and that choice changes at runtime. If there is only one implementation and it never changes, a factory is indirection with no payoff.

Laravel's service container can resolve dependencies automatically using bindings. For cases where the implementation is determined at configuration time (not at runtime), a container binding may be cleaner than an explicit factory class.

---

## Production Notes

**The factory should return the interface, not the concrete type.**  
Callers that receive a `NotificationSenderInterface` are insulated from which implementation they received.

**Throw a descriptive exception for unknown keys.**  
A factory that silently returns `null` for an unrecognised channel hides configuration bugs. Throw an exception with a clear message.

**In Laravel, consider using the container with tagged bindings.**  
For more complex resolution logic, registering implementations in the service container and using tags or contextual binding can replace an explicit factory class while keeping resolution centralised.
