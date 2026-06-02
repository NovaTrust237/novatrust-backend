<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    private const RATE = 8.5;

    public function simulate(Request $request)
    {
        $data = $request->validate([
            'amount_cfa' => 'required|integer|min:50000|max:50000000',
            'duration_months' => 'required|integer|min:3|max:60',
        ]);

        $monthly = $this->monthlyPayment($data['amount_cfa'], self::RATE, $data['duration_months']);
        $total = $monthly * $data['duration_months'];

        return response()->json([
            'amount_cfa' => (int) $data['amount_cfa'],
            'duration_months' => (int) $data['duration_months'],
            'interest_rate' => self::RATE,
            'monthly_payment_cfa' => $monthly,
            'total_cost_cfa' => $total,
            'total_interest_cfa' => $total - $data['amount_cfa'],
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'amount_cfa' => 'required|integer|min:50000|max:50000000',
            'duration_months' => 'required|integer|min:3|max:60',
            'purpose' => 'required|string|max:255',
            'id_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('id_document')->store('kyc/loans', 'public');
        $monthly = $this->monthlyPayment($data['amount_cfa'], self::RATE, $data['duration_months']);

        $loan = LoanApplication::create([
            'user_id' => $request->user()->id,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'amount_cfa' => $data['amount_cfa'],
            'duration_months' => $data['duration_months'],
            'interest_rate' => self::RATE,
            'monthly_payment_cfa' => $monthly,
            'purpose' => $data['purpose'],
            'id_document_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Demande de prêt soumise. Un agent vous contactera.',
            'loan' => $loan,
        ], 201);
    }

    public function mine(Request $request)
    {
        return response()->json(
            LoanApplication::where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->get()
        );
    }

    private function monthlyPayment(int $principal, float $annualRate, int $months): int
    {
        $r = ($annualRate / 100) / 12;
        if ($r == 0) {
            return (int) round($principal / $months);
        }
        $m = $principal * ($r * pow(1 + $r, $months)) / (pow(1 + $r, $months) - 1);
        return (int) round($m);
    }
}
