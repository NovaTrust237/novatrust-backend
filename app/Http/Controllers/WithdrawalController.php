<?php


namespace App\Http\Controllers;


use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
    {
    public function request(Request $req)
    {
        $req->validate(['amount'=>'required|integer|min:100']);
        $user = $req->user();
        $amount = (int)$req->amount;


        if (! $user->activated) {
        return response()->json(['error' => 'Account not activated. Please pay activation fee.'], 403);
        }


        // Check virtual balance availability
        $wallet = $user->wallet;
        if ($wallet->virtual_balance_cfa < $amount) return response()->json(['error'=>'Insufficient virtual balance'], 400);


        $withdrawal = Withdrawal::create(['user_id'=>$user->id,'amount_cfa'=>$amount,'status'=>'pending']);


        // Here the process is manual: send user to contact agent on WhatsApp
        $agent = env('AGENT_WHATSAPP_NUMBER');
        $text = urlencode("Bonjour, je souhaite un retrait. User: {$user->phone}, Montant: {$amount} XAF, ID: {$withdrawal->id}");
        $waLink = "https://wa.me/{$agent}?text={$text}";


        // Optionally notify admin via email, etc.


        return response()->json(['message'=>'Please contact agent to finalize withdrawal','wa_link'=>$waLink,'withdrawal_id'=>$withdrawal->id]);
    }
}
