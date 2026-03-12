# Event-Driven Pattern

## Overview

The Event-Driven pattern decouples the originator of an action from the code that reacts to it. Instead of calling side effects directly, the originating class dispatches an event. Any number of listeners can respond to that event independently, without the originator knowing they exist.

---

## The Problem It Helps Solve

Side effects accumulate in service methods. A user registration flow starts with one notification and grows into several:

```php
// ❌ Service grows with every new side effect
public function register(RegisterUserData $data): User
{
    $user = User::create([...]);

    Mail::to($user)->send(new WelcomeEmail($user));
    $this->analytics->track('user.registered', $user);
    $this->auditLog->record('user_created', $user);
    Slack::notify("New user: {$user->email}");

    return $user;
}
```

Each new side effect requires modifying the service. The service needs to know about the mailer, the analytics system, the audit logger, and Slack. These are separate responsibilities with no business relationship to registration itself.

---

## Example

See [`example.php`](./example.php) for a `UserRegistered` event dispatched from a service, with three listeners that respond to it independently.

---

## Why This Pattern Can Help

- **Decoupled side effects**: the registration service fires one event; listeners are added, removed, or modified without touching the service
- **Single responsibility**: the service handles registration; each listener handles one side effect
- **Asynchronous processing**: listeners can be queued, offloading slow operations (email sending, analytics calls) from the request cycle
- **Discoverability**: all reactions to `UserRegistered` are visible in the event's listener list, not buried in a service method

---

## Trade-offs / When Not to Overuse It

Events make the flow harder to trace compared to explicit calls. When debugging a registration issue, finding which listeners fired and in what order requires knowledge of the event system configuration. This is a real cost in teams unfamiliar with the codebase.

Events are well-suited for **side effects** — operations that are supplementary to the core action. They are not a good fit for **core logic** that must complete before the operation can succeed. If sending a welcome email is optional, use an event. If creating a default workspace is required for a valid account, do it explicitly in the service.

---

## Production Notes

**Queue slow listeners.**  
Listeners that perform I/O (email, HTTP, database writes) should implement `ShouldQueue`. This offloads the work from the HTTP request cycle and provides retry handling.

**Defer queued listeners until after the transaction commits.**  
A queued listener can begin processing before the surrounding transaction commits, reading data that does not yet exist. Set `public bool $afterCommit = true;` on the listener class to defer dispatch. Alternatively, dispatch the event after the transaction returns, or use `DB::afterCommit()` for synchronous listeners. Note that `dispatch()->afterCommit()` applies to standalone Job dispatch and does not affect event listeners.

**Events should carry sufficient context.**  
Listeners should not need to query the database to get basic information about the event. Include the relevant model or its key data directly on the event class.

**Keep events as value objects.**  
An event class should carry data about what happened — not execute logic or trigger additional effects. An event that dispatches another event in its constructor is difficult to reason about.
