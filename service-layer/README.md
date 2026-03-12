# Service Layer

## Overview

The Service Layer pattern introduces a dedicated class to own business logic, keeping controllers thin and domain rules reusable. It sits between the HTTP layer and the data layer, and is one of the most consistently useful patterns in backend systems.

---

## The Problem It Helps Solve

Controllers collect logic over time. Validation rules, conditional flows, external API calls, and side effects end up in the same method. The result is code that:

- cannot be tested without bootstrapping an HTTP request
- cannot be reused from a console command, a queue job, or another service
- mixes HTTP concerns (requests, responses, redirects) with domain concerns (business rules, state transitions)

---

## Example

See [`example.php`](./example.php) for a realistic invoice generation flow.

```php
// ❌ Controller doing too much
public function generate(Request $request): JsonResponse
{
    $user = auth()->user();

    if (!$user->hasActiveSubscription()) {
        return response()->json(['error' => 'No active subscription'], 403);
    }

    $invoice = Invoice::create([...]);
    // PDF generation, email sending, logging — all inline
}

// ✅ Controller delegates; service owns the domain logic
public function generate(InvoiceRequest $request): JsonResponse
{
    $invoice = $this->invoices->generate(auth()->user(), $request->validated());

    return response()->json(new InvoiceResource($invoice), 201);
}
```

---

## Why This Pattern Can Help

- **Testability**: service classes can be unit-tested without an HTTP context
- **Reusability**: the same logic runs from a controller, a CLI command, or a queue job
- **Readability**: the controller is a thin adapter; the service reads like a use case
- **Single responsibility**: HTTP handling and domain logic live in separate classes

---

## Trade-offs / When Not to Overuse It

The Service Layer pattern adds a class. For simple CRUD operations with no business rules, a service class is ceremony without value — the controller can interact with the model directly.

The pattern becomes valuable when:
- there are multiple steps or conditions involved
- the operation needs to be callable from more than one entry point
- side effects (events, emails, jobs) are involved

Avoid creating a service per model (`UserService`, `ProductService`) as a convention. Create services around operations or use cases (`InvoiceGenerationService`, `SubscriptionRenewalService`).

---

## Production Notes

**Services should not know about HTTP.**  
No `request()`, no `response()`, no `abort()`. Pass plain values in; return domain objects or throw domain exceptions.

**Throw domain exceptions, not HTTP exceptions.**  
A service method that throws `HttpException(403)` is coupled to the transport layer. Throw `SubscriptionRequiredException` and let the exception handler map it to `403`.

**Side effects inside a `DB::transaction` need care.**  
Sending an email or dispatching a job inside a transaction that may roll back can produce side effects for an operation that never completed. Use `dispatch()->afterCommit()` (Laravel 8+) or dispatch after the transaction returns.
