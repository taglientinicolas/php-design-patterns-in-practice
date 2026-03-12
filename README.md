# php-design-patterns-in-practice

A collection of design pattern examples grounded in real backend systems.

Each pattern is presented with a production-oriented use case rather than an abstract textbook example. The goal is to show *when* a pattern is useful, *why* it helps, and what trade-offs it introduces.

---

## Patterns

| Pattern | Problem it helps solve |
|---|---|
| [Service Layer](./service-layer/README.md) | Business logic accumulating in controllers |
| [Repository Pattern](./repository-pattern/README.md) | Data access logic scattered across the codebase |
| [Factory Pattern](./factory-pattern/README.md) | Object creation logic tied to specific implementations |
| [Strategy Pattern](./strategy-pattern/README.md) | Conditional branching that grows with each new behaviour |
| [Decorator Pattern](./decorator-pattern/README.md) | Adding cross-cutting concerns without modifying existing classes |
| [Event-Driven Pattern](./event-driven-pattern/README.md) | Tightly coupled side effects that complicate core logic |

---

## What this is not

These examples are not exhaustive implementations. They are focused illustrations of how each pattern appears in a realistic backend context. Simplifications are made where necessary and noted in the relevant README.

This repository complements [php-backend-architecture](https://github.com/taglientinicolas/php-backend-architecture), which covers structural architecture patterns at the application layer.

---

## Author

**Nicolas Taglienti** — Backend Engineer, PHP / Laravel  
[linkedin.com/in/nicolastaglienti](https://www.linkedin.com/in/nicolastaglienti/)
