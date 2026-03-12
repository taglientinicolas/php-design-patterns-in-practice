# Decorator Pattern

## Overview

The Decorator pattern wraps an object with another object that implements the same interface, adding behaviour before or after delegating to the wrapped instance. It adds capabilities to a class without modifying or subclassing it.

In backend systems, the most common applications are adding **caching**, **logging**, or **metrics** around an existing service or repository.

---

## The Problem It Helps Solve

Cross-cutting concerns — caching, logging, timing — need to be attached to data access or service logic without coupling them to the core implementation. The alternatives are:

- **Modifying the original class**: mixes caching logic with query logic; two responsibilities in one class
- **Subclassing**: creates fragile inheritance hierarchies; multiple concerns require multiple levels of inheritance
- **Decorator**: wraps transparently, stacks cleanly, keeps each class focused on one thing

```php
// ❌ Caching mixed into the repository
public function findOverdueByUser(int $userId): Collection
{
    return Cache::remember("invoices.overdue.{$userId}", 300, fn () =>
        Invoice::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->get()
    );
}
```

Now the repository has two jobs: querying and caching. Removing or changing the caching strategy requires modifying the repository.

---

## Example

See [`example.php`](./example.php) for a `CachingInvoiceRepository` that wraps an `InvoiceRepositoryInterface` and adds a caching layer transparently.

---

## Why This Pattern Can Help

- **Single responsibility**: the Eloquent repository queries; the caching decorator caches
- **Composable**: a logging decorator can wrap the caching decorator, which wraps the Eloquent repository
- **Transparent**: callers use `InvoiceRepositoryInterface`; they are unaware of which layers are present
- **Swappable**: caching can be enabled or disabled by composing a different dependency graph at the container level

---

## Trade-offs / When Not to Overuse It

The Decorator pattern is worth it when the cross-cutting concern is meaningful, stable, and genuinely separate from the core logic. If the caching key logic is trivial and the interface changes frequently, maintaining a decorator may produce more churn than value.

Decorators work best when the interface is stable. A repository interface that changes every sprint makes maintaining a decorator costly.

---

## Production Notes

**Wire the decorator in the service container, not in the decorated class.**  
The decision to add caching belongs in the composition root (service provider), not in the class being cached.

```php
// AppServiceProvider::register()
$this->app->bind(InvoiceRepositoryInterface::class, function ($app) {
    return new CachingInvoiceRepository(
        new EloquentInvoiceRepository(),
        $app->make(CacheInterface::class),
    );
});
```

**Invalidate cache when data changes.**  
A read decorator that caches query results must coordinate with write operations. Either tag cache entries and flush by tag, or use versioned cache keys tied to `updated_at`. See the [laravel-performance-patterns](https://github.com/taglientinicolas/laravel-performance-patterns) repository for cache versioning patterns.

**Stack decorators intentionally.**  
Wrapping a logging decorator around a caching decorator means reads are logged when the cache is missed. Wrapping the caching decorator around the logging decorator means every read is logged regardless of cache. The order matters.
