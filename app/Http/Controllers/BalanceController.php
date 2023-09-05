<?php

namespace App\Http\Controllers;

use Common\Core\BaseController;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Voucher;
use Illuminate\Support\Facades\Auth;


class BalanceController extends BaseController
{
   

    public function recharge(Request $request)
    {


        $voucherCode = $request->input('voucherCode');
        $voucher = Voucher::where('code', $voucherCode)->first();

        if (!$voucher) {
            return response()->json(['error' => 'Invalid voucher code'], 400);
        }

        if ($voucher->is_used) {
            return response()->json(['error' => 'Voucher code already used'], 400);
        }

        

        $user = Auth::user();
        
        if(!$user){

            return response()->json(['error' => "Invalid user"], 400);
        }
        
        $user->balance += $voucher->amount;
        $user->save();

        $voucher->is_used = true;
        $voucher->used_at = now();
        $voucher->used_by = auth()->user()->id;
        $voucher->save();

        return response()->json(['success' => 'Balance recharged successfully', 'user' => $user], 200);
    }


}
