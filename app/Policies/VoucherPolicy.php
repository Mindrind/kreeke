<?php

namespace App\Policies;

use App\Voucher;
use Common\Auth\BaseUser;
use Common\Core\Policies\BasePolicy;

class VoucherPolicy extends BasePolicy
{
    public function index(BaseUser $user, $userId = null)
    {
        return $user->hasPermission('voucher.view') || $user->id === (int) $userId;
    }

    public function show(BaseUser $user, Voucher $voucher)
    {
        return $user->hasPermission('voucher.view') || $voucher->user_id === $user->id;
    }

    public function store(BaseUser $user)
    {
        return $user->hasPermission('voucher.create');
    }

    public function update(BaseUser $user, Voucher $voucher)
    {
        return $user->hasPermission('voucher.update') || $voucher->user_id === $user->id;
    }

    public function destroy(BaseUser $user, $voucherIds)
    {
        if ($user->hasPermission('voucher.delete')) {
            return true;
        } else {
            $dbCount = app(Voucher::class)
                ->whereIn('id', $voucherIds)
                ->where('user_id', $user->id)
                ->count();
            return $dbCount === count($voucherIds);
        }
    }
}
