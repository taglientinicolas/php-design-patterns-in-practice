# Repository Pattern

## Overview

The Repository pattern centralises data access logic behind an interface. Callers depend on the interface, not on the underlying ORM or query mechanism. This separates *what data you need* from *how to retrieve it*.

---

## The Problem It Helps Solve

As applications grow, the same query logic tends to appear in multiple places with minor variations. Changing a filter, adding a default scope, or switching from Eloquent to a raw query builder requires finding and updating every occurrence.

A repository collects query logic in one place and exposes a named, domain-oriented API:

```php
// ❌ Same query duplicated across the codebase with minor variations
$reports = Invoice::where('user_id', $userId)
    ->where('status', 'paid')
    ->whereMonth('created_at', now()->month)
    ->orderBy('created_at')
    ->get();

// ✅ Named method on a repository — one place to change
$reports = $this->invoices->findPaidByUserForCurrentMonth($userId);
```

---

## Example

See [`example.php`](./example.php) for a full implementation with an interface, an Eloquent implementation, and an in-memory test double.

---

## Why This Pattern Can Help

- **Centralises query logic**: changes to a query are made in one place
- **Testability**: services can be tested with an in-memory double without touching the database
- **Expressive API**: `findOverdueInvoices()` is more descriptive than a raw query chain

---

## Trade-offs / When Not to Overuse It

The repository layer adds a class and an interface for every entity it covers. If the application has one data source and no need for test doubles — because feature tests against an in-memory SQLite database provide sufficient coverage — the abstraction adds indirection without measurable benefit.

The pattern earns its place when:
- the same query logic is used in multiple services or jobs
- test doubles for fast unit testing are a meaningful requirement
- there is a real possibility of swapping the data source (e.g., read models backed by Elasticsearch or Redis)

**Repository methods should not be generic CRUD.**  
A repository with `find()`, `findAll()`, `save()`, `delete()` is a wrapper with no domain value. Methods should reflect actual use cases: `findOverdueByUser()`, `countPendingByMonth()`.

---

## Production Notes

**Repositories belong below services, not at the controller layer.**  
A controller that calls a repository directly is performing the service's job. Repositories should be dependencies of services, not of controllers.

**Return domain objects, not query builders.**  
Returning a `Builder` instance allows the caller to modify the query, defeating the purpose of the abstraction.

**Consider feature tests over unit tests with in-memory doubles.**  
Laravel makes it straightforward to test against a SQLite in-memory database with real migrations. For many projects, this provides sufficient coverage without the cost of maintaining repository interfaces and test doubles.
