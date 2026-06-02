<?php


namespace App\Console\Commands;


use Illuminate\Console\Command;
use App\Models\Deposit;
use App\Models\Transaction;

class ApplyGains extends Command
{
    protected $signature = 'apply:gains';
    protected $description = 'Apply 80% gain to deposits paid >= 3 hours ago and not credited yet';


    public function handle()
    {
        $deposits = Deposit::where('status','paid')
        ->where('credited', false)
        ->where('paid_at', '<=', now()->subHours(3))
        ->get();


        foreach ($deposits as $d) {
        $user = $d->user;
        $wallet = $user->wallet;


        // interpretation: final virtual amount = deposit * 1.8 (deposit + 80%)
        $virtualAdd = intval(round($d->amount_cfa * 1.8));


        $wallet->virtual_balance_cfa += $virtualAdd;
        $wallet->save();


        $d->credited = true;
        $d->save();


        Transaction::create([
        'user_id'=>$user->id,
        'type'=>'gain',
        'amount_cfa'=>$virtualAdd,
        'meta'=>['deposit_id'=>$d->id]
        ]);


        $this->info("Applied gain for deposit {$d->id} -> +{$virtualAdd} XAF to user {$user->id}");
        }


        return 0;
    }
}
