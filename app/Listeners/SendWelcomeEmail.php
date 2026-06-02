<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private NotificationService $notifier)
    {
    }

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        $body = "Bonjour " . ($user->name ?: '') . ",\n\n" .
            "Bienvenue sur NovaTrust ! Votre compte a été créé avec succès.\n\n" .
            "Prochaines étapes :\n" .
            "  • Contactez votre agent pour effectuer votre premier dépôt.\n" .
            "  • Une fois votre compte activé, vous accédez au scalping 48h (+200%),\n" .
            "    aux prêts bancaires et aux subventions de projets.\n\n" .
            "À très bientôt,\n" .
            "L'équipe NovaTrust.";

        $this->notifier->notify($user, 'Bienvenue sur NovaTrust', $body);
    }
}
