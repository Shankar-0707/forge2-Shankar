<?php

namespace App\Providers;

use App\Events\CommentAdded;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Listeners\LogTicketActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register the single audit-trail listener for all activity events
        Event::listen([
            TicketCreated::class,
            TicketAssigned::class,
            TicketStatusChanged::class,
            CommentAdded::class,
        ], LogTicketActivity::class);
    }
}
