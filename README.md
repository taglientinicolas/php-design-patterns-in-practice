# php-design-patterns-in-practice

Design patterns applied to real backend use cases in PHP.

Each pattern is presented with a realistic domain example — not a shape hierarchy or a coffee machine. The goal is to show *when* a pattern is useful, *why* it helps, and what trade-offs it introduces.

---

## Why design patterns matter in backend systems

Patterns are solutions to recurring structural problems. They matter not because they are named, but because the problems they solve are real: conditional logic that grows with every new case, object creation tied to specific implementations, cross-cutting concerns scattered across the codebase. These examples show where each pattern fits and where it does not.

---

## Patterns

| Pattern | Problem addressed |
|---|---|
| [Service Layer](./service-layer/README.md) | Business logic accumulating in controllers |
| [Repository Pattern](./repository-pattern/README.md) | Data access logic scattered across the codebase |
| [Factory Pattern](./factory-pattern/README.md) | Object creation logic tied to specific implementations |
| [Strategy Pattern](./strategy-pattern/README.md) | Conditional branching that grows with each new behaviour |
| [Decorator Pattern](./decorator-pattern/README.md) | Adding cross-cutting concerns without modifying existing classes |
| [Event-Driven Pattern](./event-driven-pattern/README.md) | Tightly coupled side effects that complicate core logic |

---

## Scope

These are focused illustrations of how each pattern appears in a realistic backend context. Simplifications are made where necessary and noted in the relevant README.

Service Layer and Repository Pattern overlap with [php-backend-architecture](https://github.com/taglientinicolas/php-backend-architecture), which covers the same patterns at a deeper implementation level with full Laravel application structure. Factory, Strategy, Decorator, and Event-Driven are exclusive to this repository.

**Related repositories**  
For Laravel-specific performance patterns (N+1 queries, caching, indexing), see [laravel-performance-patterns](https://github.com/taglientinicolas/laravel-performance-patterns).  
For a complete webhook processing system demonstrating architecture, queuing, and idempotency, see [laravel-webhook-processor](https://github.com/taglientinicolas/laravel-webhook-processor).

---

## Author

**Nicolas Taglienti** — Backend Engineer, PHP / Laravel  
[linkedin.com/in/nicolastaglienti](https://www.linkedin.com/in/nicolastaglienti/)
