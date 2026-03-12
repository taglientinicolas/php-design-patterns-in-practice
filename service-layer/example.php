<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\SubscriptionRequiredException;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ----------------------------------------------------------------------------
// Domain exception — no HTTP knowledge
// The controller or exception handler decides the HTTP response.
// ----------------------------------------------------------------------------

final class SubscriptionRequiredException extends \DomainException {}

// ----------------------------------------------------------------------------
// Service — owns the invoice generation logic
// Can be called from a controller, an Artisan command, or a queue job.
// ----------------------------------------------------------------------------

final class InvoiceGenerationService
{
    public function __construct(
        private readonly PdfGenerator $pdf,
    ) {}

    public function generate(User $user, array $data): Invoice
    {
        if (!$user->hasActiveSubscription()) {
            throw new SubscriptionRequiredException(
                "User {$user->id} does not have an active subscription."
            );
        }

        return DB::transaction(function () use ($user, $data) {
            $invoice = Invoice::create([
                'user_id'    => $user->id,
                'amount'     => $data['amount'],
                'currency'   => $data['currency'],
                'due_date'   => $data['due_date'],
                'status'     => 'pending',
            ]);

            foreach ($data['line_items'] as $item) {
                $invoice->lineItems()->create($item);
            }

            $invoice->update([
                'pdf_path' => $this->pdf->generate($invoice),
            ]);

            // Dispatched after commit — prevents sending if the transaction rolls back
            dispatch(new SendInvoiceEmail($invoice, $user))->afterCommit();

            Log::info('Invoice generated.', [
                'invoice_id' => $invoice->id,
                'user_id'    => $user->id,
            ]);

            return $invoice;
        });
    }
}

// ----------------------------------------------------------------------------
// Controller — thin adapter between HTTP and the service
// ----------------------------------------------------------------------------

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceGenerationService $invoices,
    ) {}

    public function store(InvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->generate(
            auth()->user(),
            $request->validated(),
        );

        return response()->json(new InvoiceResource($invoice), 201);
    }
}

// ----------------------------------------------------------------------------
// Artisan command — same service, no HTTP context
// ----------------------------------------------------------------------------

final class GenerateMonthlyInvoicesCommand extends Command
{
    public function handle(InvoiceGenerationService $invoices): void
    {
        User::whereHas('activeSubscription')->each(function (User $user) use ($invoices) {
            try {
                $invoices->generate($user, $this->buildInvoiceData($user));
                $this->info("Invoice generated for user {$user->id}.");
            } catch (SubscriptionRequiredException $e) {
                $this->warn("Skipped user {$user->id}: {$e->getMessage()}");
            }
        });
    }

    private function buildInvoiceData(User $user): array
    {
        // Build from subscription plan — simplified here
        return [
            'amount'     => $user->subscription->monthly_amount,
            'currency'   => 'usd',
            'due_date'   => now()->addDays(30)->toDateString(),
            'line_items' => [['description' => 'Monthly subscription', 'quantity' => 1]],
        ];
    }
}
