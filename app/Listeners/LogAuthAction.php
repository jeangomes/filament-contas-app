<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Request;

class LogAuthAction
{
    /**
     * @param object $event
     * @param array<string>|null $context
     */
    public function handle(object $event, ?array $context = null): void
    {
        if (!config('authlog.enabled')) {
            return;
        }
        if (!in_array(get_class($event), (array)config('authlog.events'))) {
            return;
        }
        /** @var User $userEvent */
        $userEvent = $this->getUserParameter($event);
        $description = class_basename($event) === 'Login' ? 'Login efetuado' : class_basename($event);
        $email = $this->getEmailParameter($event) ? ' - ' . $this->getEmailParameter($event) : '';
        $spa_app = request()->has('spa_description') ? ' - ' . request()->string('spa_description') : '';
        activity()
            ->causedBy($userEvent)
            ->useLog('Auth Events' . $spa_app)
            ->withProperties(is_array($context) ? json_encode($context) : null)
            ->log($description . $email);
    }

    /**
     * @param object $event
     * @return string|null
     */
    private function getEmailParameter(object $event): ?string
    {
        if (isset($event->credentials)) {
            return $event->credentials['email'] ?? $event->credentials['login'] ?? null;
        }

        if (isset($event->request) && ($event->request->has('email') || $event->request->has('login'))) {
            return $event->request->email ?? $event->request->login;
        }

        return null;
    }

    /**
     * @param object $event
     * @return mixed
     */
    private function getUserParameter(object $event): mixed
    {
        if (isset($event->user)) {
            return $event->user;
        }

        if (Request::user()) {
            return Request::user();
        }

        /*if (Auth::guard('customer')->check()) {
            return Auth::guard('customer')->user();
        }*/
        return null;
    }
}
