<?php

namespace App\Actions\Voucher;

use App\Voucher;
use Auth;

class CrupdateVoucher
{
    /**
     * @var Voucher
     */
    private $voucher;

    /**
     * @param Voucher $voucher
     */
    public function __construct(Voucher $voucher)
    {
        $this->voucher = $voucher;
    }

    /**
     * @param array $data
     * @param Voucher $voucher
     * @return Voucher
     */
    public function execute($data, $voucher = null)
    {
        if ( ! $voucher) {
            $voucher = $this->voucher->newInstance([
                 'user_id' => Auth::id(),
            ]);
        }

        $attributes = [
            'name' => $data['name'],
        ];

        $voucher->fill($attributes)->save();

        return $voucher;
    }
}