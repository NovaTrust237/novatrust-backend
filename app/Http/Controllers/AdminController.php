<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LoanApplication;
use App\Models\GrantApplication;
use App\Models\AdminBalanceAdjustment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    protected NotificationService $notifier;

    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    public function clients(Request $request)
    {
        $search = $request->query('search');

        $query = User::where('role', '!=', 'admin')
            ->with('wallet')
            ->orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(25);

        $clients->getCollection()->transform(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'activated' => (bool) $u->activated,
                'balance' => (float) ($u->wallet?->balance_cfa ?? 0),
                'created_at' => $u->created_at,
            ];
        });

        return response()->json($clients);
    }

    public function showClient($id)
    {
        $user = User::with('wallet', 'transactions', 'loanApplications', 'grantApplications')->findOrFail($id);
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'activated' => (bool) $user->activated,
            'balance' => (float) ($user->wallet?->balance_cfa ?? 0),
            'created_at' => $user->created_at,
            'transactions' => $user->transactions()->orderByDesc('id')->limit(50)->get(),
            'loan_applications' => $user->loanApplications,
            'grant_applications' => $user->grantApplications,
        ]);
    }

    public function adjustBalance(Request $request, $id)
    {
        $data = $request->validate([
            'delta_cfa' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        $admin = $request->user();
        $user = User::with('wallet')->findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Impossible de modifier le solde d\'un admin.'], 422);
        }

        if (!$user->wallet) {
            $user->wallet()->create(['balance_cfa' => 0, 'virtual_balance_cfa' => 0]);
            $user->load('wallet');
        }

        $newBalance = $user->wallet->balance_cfa + $data['delta_cfa'];
        if ($newBalance < 0) {
            return response()->json(['message' => 'Le solde ne peut pas devenir négatif.'], 422);
        }

        DB::transaction(function () use ($admin, $user, $data, $newBalance) {
            $user->wallet->balance_cfa = $newBalance;
            $user->wallet->save();

            AdminBalanceAdjustment::create([
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'delta_cfa' => $data['delta_cfa'],
                'reason' => $data['reason'],
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'type' => $data['delta_cfa'] > 0 ? 'admin_credit' : 'admin_debit',
                'amount_cfa' => abs($data['delta_cfa']),
                'meta' => ['admin_id' => $admin->id, 'reason' => $data['reason']],
            ]);
        });

        return response()->json([
            'message' => 'Solde mis à jour avec succès.',
            'new_balance' => (float) $newBalance,
        ]);
    }

    public function toggleActivation(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cette action n\'est pas autorisée sur un admin.'], 422);
        }
        $user->activated = !$user->activated;
        $user->save();
        return response()->json([
            'message' => $user->activated ? 'Compte activé.' : 'Compte désactivé.',
            'activated' => (bool) $user->activated,
        ]);
    }

    public function loanApplications()
    {
        return response()->json(
            LoanApplication::with('user:id,name,phone,email')->orderByDesc('id')->paginate(25)
        );
    }

    public function updateLoanStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);
        $loan = LoanApplication::with('user.wallet')->findOrFail($id);
        $previousStatus = $loan->status;
        $loan->update($data);

        // Crédit automatique si nouvellement approuvé
        if ($data['status'] === 'approved' && $previousStatus !== 'approved') {
            $this->creditUser($loan->user, (int) $loan->amount_cfa, 'loan', [
                'loan_id' => $loan->id,
                'admin_id' => $request->user()->id,
            ]);
            $this->notifier->notify(
                $loan->user,
                'NovaTrust — Prêt approuvé',
                "Bonjour " . ($loan->user->name ?: '') . ",\n\n" .
                "Votre demande de prêt de " . number_format($loan->amount_cfa, 0, ',', ' ') . " XAF a été APPROUVÉE.\n" .
                "Le montant a été crédité sur votre compte NovaTrust.\n\n" .
                ($data['admin_note'] ? "Note: " . $data['admin_note'] . "\n\n" : '') .
                "Cordialement,\nL'équipe NovaTrust."
            );
        } elseif ($data['status'] === 'rejected' && $previousStatus !== 'rejected') {
            $this->notifier->notify(
                $loan->user,
                'NovaTrust — Prêt refusé',
                "Bonjour " . ($loan->user->name ?: '') . ",\n\n" .
                "Nous regrettons de vous informer que votre demande de prêt de " . number_format($loan->amount_cfa, 0, ',', ' ') . " XAF n'a pas été retenue.\n\n" .
                ($data['admin_note'] ? "Motif: " . $data['admin_note'] . "\n\n" : '') .
                "Cordialement,\nL'équipe NovaTrust."
            );
        }

        return response()->json(['message' => 'Statut mis à jour.', 'loan' => $loan->fresh()]);
    }

    public function grantApplications()
    {
        return response()->json(
            GrantApplication::with('user:id,name,phone,email')->orderByDesc('id')->paginate(25)
        );
    }

    public function updateGrantStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);
        $grant = GrantApplication::with('user.wallet')->findOrFail($id);
        $previousStatus = $grant->status;
        $grant->update($data);

        if ($data['status'] === 'approved' && $previousStatus !== 'approved') {
            $this->creditUser($grant->user, (int) $grant->requested_amount_cfa, 'grant', [
                'grant_id' => $grant->id,
                'admin_id' => $request->user()->id,
            ]);
            $this->notifier->notify(
                $grant->user,
                'NovaTrust — Subvention approuvée',
                "Bonjour " . ($grant->user->name ?: '') . ",\n\n" .
                "Excellente nouvelle ! Votre projet \"" . $grant->project_title . "\" a été retenu.\n" .
                "Une subvention de " . number_format($grant->requested_amount_cfa, 0, ',', ' ') . " XAF a été créditée sur votre compte NovaTrust.\n\n" .
                ($data['admin_note'] ? "Note: " . $data['admin_note'] . "\n\n" : '') .
                "Cordialement,\nL'équipe NovaTrust."
            );
        } elseif ($data['status'] === 'rejected' && $previousStatus !== 'rejected') {
            $this->notifier->notify(
                $grant->user,
                'NovaTrust — Subvention refusée',
                "Bonjour " . ($grant->user->name ?: '') . ",\n\n" .
                "Votre projet \"" . $grant->project_title . "\" n'a pas été retenu pour cette session de financement.\n\n" .
                ($data['admin_note'] ? "Motif: " . $data['admin_note'] . "\n\n" : '') .
                "Cordialement,\nL'équipe NovaTrust."
            );
        }

        return response()->json(['message' => 'Statut mis à jour.', 'grant' => $grant->fresh()]);
    }

    /**
     * Liste des scalpings en cours.
     */
    public function activeScalpings()
    {
        $users = User::where('role', '!=', 'admin')
            ->whereNotNull('investment_start_at')
            ->where('investment_processed', false)
            ->with('wallet')
            ->orderByDesc('investment_start_at')
            ->get();

        $duration = \App\Http\Controllers\PaymentController::INVESTMENT_DURATION_SECONDS;
        $multiplier = \App\Http\Controllers\PaymentController::INVESTMENT_MULTIPLIER;

        return response()->json($users->map(function ($u) use ($duration, $multiplier) {
            $start = $u->investment_start_at;
            $principal = (int) ($u->wallet?->investment_principal_cfa ?? 0);
            $elapsed = $start ? max(0, time() - $start->getTimestamp()) : 0;
            $remaining = max(0, $duration - $elapsed);
            $progress = min(1, $elapsed / max(1, $duration));
            $current = (int) round($principal * (1 + ($multiplier - 1) * $progress));
            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'started_at' => $start,
                'elapsed_seconds' => $elapsed,
                'remaining_seconds' => $remaining,
                'progress' => round($progress, 4),
                'principal_cfa' => $principal,
                'projected_balance_cfa' => $current,
                'target_balance_cfa' => (int) round($principal * $multiplier),
            ];
        }));
    }

    public function stats()
    {
        return response()->json([
            'total_clients' => User::where('role', '!=', 'admin')->count(),
            'activated_clients' => User::where('role', '!=', 'admin')->where('activated', true)->count(),
            'pending_loans' => LoanApplication::where('status', 'pending')->count(),
            'pending_grants' => GrantApplication::where('status', 'pending')->count(),
            'active_scalpings' => User::where('role', '!=', 'admin')
                ->whereNotNull('investment_start_at')
                ->where('investment_processed', false)
                ->count(),
            'total_balance' => (float) Wallet::sum('balance_cfa'),
        ]);
    }

    private function creditUser(User $user, int $amount, string $type, array $meta = []): void
    {
        if (!$user->wallet) {
            $user->wallet()->create(['balance_cfa' => 0, 'virtual_balance_cfa' => 0]);
            $user->load('wallet');
        }
        $user->wallet->balance_cfa += $amount;
        $user->wallet->save();

        Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount_cfa' => $amount,
            'meta' => $meta,
        ]);
    }

    /**
     * Créer un nouveau compte admin
     */
    public function createAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => 'admin',
            'activated' => true,
        ]);

        $token = $admin->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Admin créé avec succès.',
            'user' => $admin->only('id', 'name', 'email', 'phone', 'role'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Lister tous les admins
     */
    public function listAdmins()
    {
        $admins = User::where('role', 'admin')->select('id', 'name', 'email', 'phone', 'created_at')->get();
        return response()->json($admins);
    }
}
