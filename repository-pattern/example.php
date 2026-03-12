<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Support\Collection;

// ----------------------------------------------------------------------------
// STEP 1: Interface — domain-oriented, not generic CRUD
// ----------------------------------------------------------------------------

interface InvoiceRepositoryInterface
{
    public function findPaidByUserForCurrentMonth(int $userId): Collection;
    public function findOverdueByUser(int $userId): Collection;
    public function countPendingByUser(int $userId): int;
    public function save(Invoice $invoice): Invoice;
}

// ----------------------------------------------------------------------------
// STEP 2: Eloquent implementation
// ----------------------------------------------------------------------------

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findPaidByUserForCurrentMonth(int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->orderBy('created_at')
            ->get();
    }

    public function findOverdueByUser(int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
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
// Bind in AppServiceProvider::register():
// $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// STEP 3: Service depends on the interface — not on Eloquent directly
// ----------------------------------------------------------------------------

final class InvoiceSummaryService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
    ) {}

    public function getMonthlySummary(int $userId): array
    {
        return [
            'paid_this_month' => $this->invoices->findPaidByUserForCurrentMonth($userId),
            'overdue'         => $this->invoices->findOverdueByUser($userId),
            'pending_count'   => $this->invoices->countPendingByUser($userId),
        ];
    }
}

// ----------------------------------------------------------------------------
// STEP 4: In-memory test double
// Allows unit-testing InvoiceSummaryService without a database connection.
// ----------------------------------------------------------------------------

final class InMemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    private array $invoices = [];

    public function seed(array $invoices): void
    {
        $this->invoices = $invoices;
    }

    public function findPaidByUserForCurrentMonth(int $userId): Collection
    {
        return collect($this->invoices)->filter(
            fn ($i) => $i->user_id === $userId
                && $i->status === 'paid'
                && (int) date('m', strtotime($i->created_at)) === (int) date('m')
        )->values();
    }

    public function findOverdueByUser(int $userId): Collection
    {
        return collect($this->invoices)->filter(
            fn ($i) => $i->user_id === $userId
                && $i->status === 'pending'
                && strtotime($i->due_date) < time()
        )->values();
    }

    public function countPendingByUser(int $userId): int
    {
        return collect($this->invoices)
            ->filter(fn ($i) => $i->user_id === $userId && $i->status === 'pending')
            ->count();
    }

    public function save(Invoice $invoice): Invoice
    {
        $this->invoices[] = $invoice;

        return $invoice;
    }
}

// ----------------------------------------------------------------------------
// Unit test example (PHPUnit)
//
// class InvoiceSummaryServiceTest extends TestCase
// {
//     public function test_monthly_summary_returns_correct_pending_count(): void
//     {
//         $repo = new InMemoryInvoiceRepository();
//         $repo->seed([
//             (object) ['user_id' => 1, 'status' => 'pending', 'due_date' => '2099-01-01', 'created_at' => date('Y-m-d')],
//             (object) ['user_id' => 1, 'status' => 'paid',    'due_date' => '2099-01-01', 'created_at' => date('Y-m-d')],
//             (object) ['user_id' => 1, 'status' => 'pending', 'due_date' => '2099-01-01', 'created_at' => date('Y-m-d')],
//         ]);
//
//         $service = new InvoiceSummaryService($repo);
//         $summary = $service->getMonthlySummary(1);
//
//         $this->assertSame(2, $summary['pending_count']);
//     }
// }
// ----------------------------------------------------------------------------
