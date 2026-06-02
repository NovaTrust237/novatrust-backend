<?php
namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Activation;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    // 48h, +200% (multiplicateur 3)
    public const INVESTMENT_DURATION_SECONDS = 48 * 3600;
    public const INVESTMENT_MULTIPLIER = 3.0; // 1 + 200%
    public const MIN_INVEST_CFA = 50000;

    protected $ps;
    protected NotificationService $notifier;

    public function __construct(PaymentService $ps, NotificationService $notifier)
    {
        $this->ps = $ps;
        $this->notifier = $notifier;
    }

    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_cfa' => 'required|numeric|min:50000|max:1000000',
            'reference' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = $request->user();
        if (!$user->wallet) {
            $user->wallet()->create(['balance_cfa' => 0, 'virtual_balance_cfa' => 0]);
            $user->load('wallet');
        }

        $user->wallet->balance_cfa += $request->amount_cfa;
        $user->wallet->save();

        Deposit::create([
            'user_id' => $user->id,
            'amount_cfa' => $request->amount_cfa,
            'reference' => $request->reference,
            'provider' => 'mobile_money_simulated',
            'provider_tx' => 'SIMULATED-' . now()->format('YmdHis'),
            'status' => 'paid',
            'credited' => true,
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dépôt effectué avec succès.',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    public function activate(Request $request)
    {
        $user = $request->user();
        if ($user->activated) {
            return response()->json([
                'message' => 'Votre compte est déjà activé.',
                'user' => $this->formatUserResponse($user),
            ]);
        }

        $user->wallet->balance_cfa *= 1.5;
        $user->wallet->save();
        $user->activated = true;
        $user->save();

        Activation::create([
            'user_id' => $user->id,
            'amount_cfa' => 20000,
            'status' => 'paid',
            'reference' => 'SIMULATED-ACT-' . now()->format('YmdHis'),
        ]);

        return response()->json([
            'message' => 'Compte activé ! Bonus de 50% appliqué.',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    public function invest(Request $request)
    {
        $user = $request->user();
        $user->load('wallet');

        if (!$user->wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé.'], 400);
        }
        if ($user->wallet->balance_cfa < self::MIN_INVEST_CFA) {
            return response()->json(['message' => 'Solde insuffisant pour démarrer un scalping.'], 400);
        }
        if ($user->investment_start_at !== null && !$user->investment_processed) {
            return response()->json(['message' => 'Un scalping est déjà en cours.'], 400);
        }

        // Snapshot du capital de départ
        $user->wallet->investment_principal_cfa = (int) $user->wallet->balance_cfa;
        $user->wallet->save();

        $user->investment_start_at = now();
        $user->investment_processed = false;
        $user->has_invested = true;
        $user->save();

        return response()->json([
            'message' => 'Scalping démarré ! Rendement de +200% dans 48 heures.',
            'user' => $this->formatUserResponse($user),
            'meta' => $this->investmentMeta($user),
        ]);
    }

    public function finalizeInvestment(Request $request)
    {
        $user = $request->user();
        $user->load('wallet');

        if (!$user->wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé.'], 400);
        }
        if ($user->investment_start_at === null || $user->investment_processed) {
            return response()->json(['message' => 'Aucun scalping en cours à finaliser.'], 400);
        }

        $elapsed = $this->elapsedSeconds($user->investment_start_at);
        if ($elapsed < self::INVESTMENT_DURATION_SECONDS) {
            $remaining = self::INVESTMENT_DURATION_SECONDS - $elapsed;
            return response()->json([
                'message' => "Scalping non terminé. Temps restant : {$remaining} secondes.",
                'remaining_seconds' => $remaining,
            ], 400);
        }

        $principal = (int) ($user->wallet->investment_principal_cfa ?: $user->wallet->balance_cfa);
        $newBalance = (int) round($principal * self::INVESTMENT_MULTIPLIER);
        $profit = $newBalance - $principal;

        $user->wallet->balance_cfa = $newBalance;
        $user->wallet->investment_principal_cfa = 0;
        $user->wallet->save();

        $user->investment_processed = true;
        $user->investment_start_at = null;
        $user->save();

        Transaction::create([
            'user_id' => $user->id,
            'type' => 'gain',
            'amount_cfa' => $profit,
            'meta' => ['source' => 'scalping_48h', 'principal' => $principal],
        ]);

        $this->notifier->notify(
            $user,
            'NovaTrust — Scalping terminé',
            "Bonjour " . ($user->name ?: '') . ",\n\n" .
            "Votre session de scalping est terminée. Vous avez gagné " . number_format($profit, 0, ',', ' ') . " XAF.\n" .
            "Nouveau solde : " . number_format($newBalance, 0, ',', ' ') . " XAF.\n\n" .
            "Merci d'utiliser NovaTrust."
        );

        return response()->json([
            'message' => "Scalping finalisé ! +" . number_format($profit, 0, ',', ' ') . " XAF de rendement.",
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Arrête prématurément un scalping en cours et crédite le solde
     * du montant projeté à l'instant T.
     */
    public function stopInvestment(Request $request)
    {
        $user = $request->user();
        $user->load('wallet');

        if (!$user->wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé.'], 400);
        }
        if ($user->investment_start_at === null || $user->investment_processed) {
            return response()->json(['message' => 'Aucun scalping en cours à arrêter.'], 400);
        }

        $principal = (int) ($user->wallet->investment_principal_cfa ?: $user->wallet->balance_cfa);
        $elapsed = $this->elapsedSeconds($user->investment_start_at);
        $progress = min(1, $elapsed / self::INVESTMENT_DURATION_SECONDS);
        $current = (int) round($principal * (1 + (self::INVESTMENT_MULTIPLIER - 1) * $progress));
        $profit = $current - $principal;

        $user->wallet->balance_cfa = $current;
        $user->wallet->investment_principal_cfa = 0;
        $user->wallet->save();

        $user->investment_processed = true;
        $user->investment_start_at = null;
        $user->save();

        \App\Models\Transaction::create([
            'user_id' => $user->id,
            'type' => 'gain',
            'amount_cfa' => max(0, $profit),
            'meta' => [
                'source' => 'scalping_stopped_early',
                'principal' => $principal,
                'elapsed_seconds' => $elapsed,
                'progress' => round($progress, 4),
            ],
        ]);

        $this->notifier->notify(
            $user,
            'NovaTrust — Scalping arrêté',
            "Bonjour " . ($user->name ?: '') . ",\n\n" .
            "Vous avez choisi d'arrêter votre scalping avant la fin de la session.\n" .
            "Progression : " . round($progress * 100, 2) . "%\n" .
            "Gain verrouillé : " . number_format(max(0, $profit), 0, ',', ' ') . " XAF\n" .
            "Nouveau solde : " . number_format($current, 0, ',', ' ') . " XAF\n\n" .
            "Cordialement,\nL'équipe NovaTrust."
        );

        return response()->json([
            'message' => "Scalping arrêté. Gain verrouillé : " . number_format(max(0, $profit), 0, ',', ' ') . " XAF.",
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Etat courant du scalping (pour ticker frontend).
     */
    public function investmentStatus(Request $request)
    {
        $user = $request->user();
        $user->load('wallet');
        return response()->json($this->investmentMeta($user));
    }

    /**
     * Calcule l'écart en secondes entre maintenant et une date passée,
     * de manière robuste vis-à-vis du signe (Carbon 3 renvoie un diff signé).
     */
    private function elapsedSeconds($startedAt): int
    {
        if (!$startedAt) return 0;
        $startTs = $startedAt instanceof \Carbon\Carbon
            ? $startedAt->getTimestamp()
            : \Carbon\Carbon::parse($startedAt)->getTimestamp();
        return max(0, time() - $startTs);
    }

    private function investmentMeta($user): array
    {
        $start = $user->investment_start_at;
        $principal = (int) ($user->wallet->investment_principal_cfa ?? 0);
        $active = $start !== null && !$user->investment_processed;
        $elapsed = $active ? $this->elapsedSeconds($start) : 0;
        $remaining = $active ? max(0, self::INVESTMENT_DURATION_SECONDS - $elapsed) : 0;
        $progress = $active ? min(1, $elapsed / self::INVESTMENT_DURATION_SECONDS) : 0;
        $current = $active
            ? (int) round($principal * (1 + (self::INVESTMENT_MULTIPLIER - 1) * $progress))
            : (int) ($user->wallet->balance_cfa ?? 0);

        return [
            'active' => $active,
            'duration_seconds' => self::INVESTMENT_DURATION_SECONDS,
            'multiplier' => self::INVESTMENT_MULTIPLIER,
            'started_at' => $start,
            'elapsed_seconds' => $elapsed,
            'remaining_seconds' => $remaining,
            'progress' => $progress,
            'principal_cfa' => $principal,
            'projected_balance_cfa' => $current,
            'target_balance_cfa' => (int) round($principal * self::INVESTMENT_MULTIPLIER),
        ];
    }

    private function formatUserResponse($user)
    {
        $user->loadMissing('wallet');
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role ?? 'client',
            'activated' => (bool) $user->activated,
            'balance' => (float) ($user->wallet?->balance_cfa ?? 0),
            'investment_start_at' => $user->investment_start_at?->toDateTimeString(),
            'investment_processed' => (bool) $user->investment_processed,
        ];
    }

    public function webhook(Request $req)
    {
        if (!$this->ps->verifyWebhook($req)) {
            return response('Invalid signature', 403);
        }
        return response('ok', 200);
    }
}
