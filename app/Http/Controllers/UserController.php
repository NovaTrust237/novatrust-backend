<?php
namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user()->load('wallet');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role ?? 'client',
            'activated' => (bool) $user->activated,
            'has_invested' => (bool) $user->has_invested,
            'investment_start_at' => $user->investment_start_at,
            'investment_processed' => (bool) $user->investment_processed,
            'balance' => (float) ($user->wallet?->balance_cfa ?? 0),
            'investment_principal_cfa' => (int) ($user->wallet?->investment_principal_cfa ?? 0),
            'wallet' => $user->wallet,
        ]);
    }

    public function transactions(Request $request)
    {
        $tx = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        return response()->json($tx);
    }

    /**
     * Mise à jour du profil (nom, email, téléphone, mot de passe).
     * Le changement de mot de passe nécessite le mot de passe actuel.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if (!empty($data['password'])) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
            }
            $user->password = $data['password']; // hashé via $casts
        }

        $user->name = $data['name'] ?? $user->name;
        $user->email = $data['email'] ?? $user->email;
        $user->phone = $data['phone'];
        $user->save();

        $user->loadMissing('wallet');

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role ?? 'client',
                'activated' => (bool) $user->activated,
                'balance' => (float) ($user->wallet?->balance_cfa ?? 0),
            ],
        ]);
    }
}
