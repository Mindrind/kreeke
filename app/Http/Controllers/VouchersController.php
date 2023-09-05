<?php

namespace App\Http\Controllers;

use App\Voucher;
use App\PaginateVouchers;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VouchersController extends BaseController
{
    public function generateVouchers(Request $request){



        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
            
        }
    
        $vouchers = [];
        $amount = $request->input('amount');
        $quantity = $request->input('quantity');
    
        for ($i = 0; $i < $quantity; $i++) {
            $voucherCode = Str::random(10);
            $voucher = new Voucher;
            $voucher->code = $voucherCode;
            $voucher->amount = $amount;
            $voucher->save();
            $vouchers[] = $voucher;
        }
        return response()->json(['status' => 'success', 'vouchers' => $vouchers], 200);
    }

    public function getVouchers(Request $request)
    {
        $paginate = new PaginateVouchers();
        $result =  $paginate->execute($request->all());

        return $this->success(['pagination' => $result]);
    }

    public function getUsedVouchers(Request $request)
    {
        $vouchers = Voucher::where('is_used', true)->get();

        return response()->json(['data' => $vouchers], 200);
    }

    public function getVoucherByCode(Request $request)
{
    $voucherCode = $request->voucher;
    $voucher = Voucher::where('code', $voucherCode)->firstOrFail();

    return response()->json(['voucher' => $voucher], 200);
}

    public function csvExport()
    {
        $fileName = 'vouchers.csv';
        $vouchers = Voucher::all();

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Id', 'Code', 'Amount', 'Is Used', 'Used At', 'Used By');

        $callback = function() use($vouchers, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($vouchers as $voucher) {
                $row['Id']  = $voucher->id;
                $row['Code']    = $voucher->code;
                $row['Amount']    = $voucher->amount;
                $row['Is Used']  = $voucher->is_used ? "Yes" : "No";
                $row['Used At']  = $voucher->used_at;
                $row['Used By']  = empty($voucher->user) ? "" : $voucher->user->display_name;

                fputcsv($file, array($row['Id'], $row['Code'], $row['Amount'], $row['Is Used'], $row['Used At'], $row['Used By']));
            }

            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function destroy(string $ids) 
    {
        $voucherIds = explode(',', $ids);
        $vouchers = Voucher::whereIn('id', $voucherIds)->get();

        foreach($vouchers as $voucher) {
            Voucher::destroy($voucher->id);
        }
        return response()->json(['message' => 'Vouchers deleted successfully'], 200);
    }
}
