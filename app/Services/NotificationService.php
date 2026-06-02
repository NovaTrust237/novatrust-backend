<?php

namespace App\Services;

use App\Jobs\SendNotificationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notifie l'utilisateur (email uniquement, asynchrone via queue).
     */
    public function notify(User $user, string $subject, string $body): void
    {
        $this->sendEmail($user, $subject, $body);
    }

    /**
     * Place le mail en file d'attente — le worker (php artisan queue:work) l'envoie en arrière-plan.
     */
    public function sendEmail(User $user, string $subject, string $body): void
    {
        if (!$user->email) {
            Log::info("[Notification] Pas d'email pour user #{$user->id}, skip mail.");
            return;
        }

        SendNotificationEmail::dispatch(
            $user->email,
            $subject,
            $body,
            $user->name
        );
    }

    /**
     * Envoie un message WhatsApp depuis le numéro NovaTrust (conservé pour usage manuel).
     *
     * Non utilisé par notify() — désactivé pour les notifications sortantes
     * (cf. décision produit : email uniquement).
     */
    public function sendWhatsApp(User $user, string $body): void
    {
        $to = $this->normalize($user->phone);
        if (!$to) {
            Log::info("[Notification] Pas de téléphone exploitable pour user #{$user->id}");
            return;
        }

        $providerUrl = env('WHATSAPP_PROVIDER_URL');
        $providerToken = env('WHATSAPP_PROVIDER_TOKEN');
        $fromNumber = env('NOVATRUST_WHATSAPP_NUMBER', '237673407721');

        if ($providerUrl) {
            try {
                Http::withToken($providerToken)
                    ->acceptJson()
                    ->post($providerUrl, [
                        'from' => $fromNumber,
                        'to' => $to,
                        'message' => $body,
                    ])
                    ->throw();
                Log::info("[Notification] WhatsApp envoyé à {$to} depuis {$fromNumber}");
                return;
            } catch (\Throwable $e) {
                Log::error("[Notification] Échec WhatsApp pour {$to} : " . $e->getMessage());
            }
        }

        Log::info("[Notification][WA-PENDING] from={$fromNumber} to={$to} :: {$body}");
    }

    private function normalize(?string $phone): ?string
    {
        if (!$phone) return null;
        $p = preg_replace('/[^0-9]/', '', $phone);
        if (!$p) return null;
        return $p;
    }
}
