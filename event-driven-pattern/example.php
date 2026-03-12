<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// ----------------------------------------------------------------------------
// EVENT — value object describing what happened
// Carries the data listeners need; does not execute logic.
// ----------------------------------------------------------------------------

final class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $registrationSource, // e.g. 'web', 'api', 'oauth'
    ) {}
}

// ----------------------------------------------------------------------------
// SERVICE — dispatches one event; knows nothing about side effects
// ----------------------------------------------------------------------------

final class UserRegistrationService
{
    public function register(RegisterUserData $data): User
    {
        $user = User::create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => bcrypt($data->password),
        ]);

        // One dispatch. All side effects are handled by listeners.
        UserRegistered::dispatch($user, $data->source);

        return $user;
    }
}

// ----------------------------------------------------------------------------
// LISTENER 1: Send welcome email
// Queued — offloads email delivery from the request cycle.
//
// $afterCommit = true ensures the job is only dispatched after the database
// transaction (if any) that triggered the event has committed. Without this,
// a queued listener may start processing before the User row is visible.
// ----------------------------------------------------------------------------

final class SendWelcomeEmail implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeEmail($event->user));
    }
}

// ----------------------------------------------------------------------------
// LISTENER 2: Track registration in analytics
// Queued — analytics calls should not slow down the registration response.
// ----------------------------------------------------------------------------

final class TrackRegistrationInAnalytics implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->analytics->track('user.registered', [
            'user_id' => $event->user->id,
            'source'  => $event->registrationSource,
        ]);
    }
}

// ----------------------------------------------------------------------------
// LISTENER 3: Write audit log
// Synchronous — audit records should be written immediately and reliably.
// Does not implement ShouldQueue.
// ----------------------------------------------------------------------------

final class WriteAuditLog
{
    public function handle(UserRegistered $event): void
    {
        Log::channel('audit')->info('User registered.', [
            'user_id' => $event->user->id,
            'email'   => $event->user->email,
            'source'  => $event->registrationSource,
        ]);
    }
}

// ----------------------------------------------------------------------------
// REGISTRATION — EventServiceProvider
// Declares which listeners respond to UserRegistered.
// Adding a new side effect means adding a listener here, not modifying the service.
//
// protected $listen = [
//     UserRegistered::class => [
//         SendWelcomeEmail::class,
//         TrackRegistrationInAnalytics::class,
//         WriteAuditLog::class,
//     ],
// ];
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// TRANSACTION SAFETY
//
// $afterCommit = true on the listener class (shown above) is the idiomatic way
// to defer queued listener execution until after the surrounding transaction
// commits. This is a property on the listener, not on the job or event dispatch.
//
// If you need to dispatch the event itself after a transaction (e.g. to also
// trigger synchronous listeners safely), use DB::afterCommit():
//
// DB::transaction(function () use ($data) {
//     $user = User::create([...]);
//     DB::afterCommit(fn () => UserRegistered::dispatch($user, $data->source));
//     return $user;
// });
//
// Note: dispatch()->afterCommit() applies to standalone Job dispatch, not to
// Event::dispatch(). Do not confuse the two.
// ----------------------------------------------------------------------------
