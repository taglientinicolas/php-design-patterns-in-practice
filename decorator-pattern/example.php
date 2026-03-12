<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

// ----------------------------------------------------------------------------
// CONTRACT — shared by the real implementation and all decorators
// ----------------------------------------------------------------------------

interface InvoiceRepositoryInterface
{
    public function findOverdueByUser(int $userId): Collection;
    public function findPaidByUserForCurrentMonth(int $userId): Collection;
    public function countPendingByUser(int $userId): int;
    public function save(Invoice $invoice): Invoice;
}

// ----------------------------------------------------------------------------
// REAL IMPLEMENTATION — only knows about querying
// ----------------------------------------------------------------------------

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findOverdueByUser(int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get();
    }

    public function findPaidByUserForCurrentMonth(int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();
    }

    public function countPendingByUser(int $userId): int
    {
        return Invoice::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();
    }

    public function save(Invoice $invoice): Invoice
    {
        $invoice->save();

        return $invoice;
    }
}

// ----------------------------------------------------------------------------
// DECORATOR 1: Caching
// Wraps the real repository and caches read results.
// Write operations delegate directly and do not cache.
// ----------------------------------------------------------------------------

final class CachingInvoiceRepository implements InvoiceRepositoryInterface
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly InvoiceRepositoryInterface $inner,
        private readonly CacheInterface             $cache,
    ) {}

    public function findOverdueByUser(int $userId): Collection
    {
        return $this->cache->remember(
            "invoices.overdue.user.{$userId}",
            self::TTL_SECONDS,
            fn () => $this->inner->findOverdueByUser($userId),
        );
    }

    public function findPaidByUserForCurrentMonth(int $userId): Collection
    {
        $key = sprintf('invoices.paid.user.%d.%s', $userId, now()->format('Y-m'));

        return $this->cache->remember(
            $key,
            self::TTL_SECONDS,
            fn () => $this->inner->findPaidByUserForCurrentMonth($userId),
        );
    }

    public function countPendingByUser(int $userId): int
    {
        return $this->cache->remember(
            "invoices.pending.count.user.{$userId}",
            self::TTL_SECONDS,
            fn () => $this->inner->countPendingByUser($userId),
        );
    }

    public function save(Invoice $invoice): Invoice
    {
        // Invalidate all read caches for this user after a write.
        // Status changes (e.g. pending → paid) affect overdue, pending count,
        // and the paid-this-month query — all three must be cleared.
        $this->cache->forget("invoices.overdue.user.{$invoice->user_id}");
        $this->cache->forget("invoices.pending.count.user.{$invoice->user_id}");
        $this->cache->forget(
            sprintf('invoices.paid.user.%d.%s', $invoice->user_id, now()->format('Y-m'))
        );

        return $this->inner->save($invoice);
    }
}

// ----------------------------------------------------------------------------
// DECORATOR 2: Logging
// Logs the elapsed time for every read operation.
//
// Stack order matters: Logging(Caching(Eloquent)) means the timer measures
// how long the caching layer takes to respond — fast on a hit, slow on a miss.
// This does not distinguish between hits and misses; it measures wall time only.
// To log hit/miss status, the caching decorator would need to expose that signal.
// ----------------------------------------------------------------------------

final class LoggingInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $inner,
    ) {}

    public function findOverdueByUser(int $userId): Collection
    {
        return $this->timed('findOverdueByUser', fn () => $this->inner->findOverdueByUser($userId));
    }

    public function findPaidByUserForCurrentMonth(int $userId): Collection
    {
        return $this->timed('findPaidByUserForCurrentMonth', fn () => $this->inner->findPaidByUserForCurrentMonth($userId));
    }

    public function countPendingByUser(int $userId): int
    {
        return $this->timed('countPendingByUser', fn () => $this->inner->countPendingByUser($userId));
    }

    public function save(Invoice $invoice): Invoice
    {
        return $this->inner->save($invoice);
    }

    private function timed(string $method, callable $fn): mixed
    {
        $start  = microtime(true);
        $result = $fn();
        $ms     = round((microtime(true) - $start) * 1000, 2);

        Log::debug("InvoiceRepository::{$method}", ['duration_ms' => $ms]);

        return $result;
    }
}

// ----------------------------------------------------------------------------
// COMPOSITION — wired in AppServiceProvider::register()
//
// Stack: Logging → Caching → Eloquent
// All callers receive InvoiceRepositoryInterface.
//
// $this->app->bind(InvoiceRepositoryInterface::class, function ($app) {
//     return new LoggingInvoiceRepository(
//         new CachingInvoiceRepository(
//             new EloquentInvoiceRepository(),
//             $app->make(CacheInterface::class),
//         ),
//     );
// });
// ----------------------------------------------------------------------------
