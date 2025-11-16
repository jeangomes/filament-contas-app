<?php

namespace App\Providers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Listeners\LogAuthAction;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        Attempting::class => [LogAuthAction::class],
        Authenticated::class => [LogAuthAction::class],
        Failed::class => [LogAuthAction::class],
        Lockout::class => [LogAuthAction::class],
        Login::class => [LogAuthAction::class],
        Logout::class => [LogAuthAction::class],
        OtherDeviceLogout::class => [LogAuthAction::class],
        PasswordReset::class => [LogAuthAction::class],
        Registered::class => [SendEmailVerificationNotification::class, LogAuthAction::class],
        Verified::class => [LogAuthAction::class],
        //'App\Events\NewCustomerSaved'=> ['App\Listeners\NotifyNewCustomerSaved'],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
