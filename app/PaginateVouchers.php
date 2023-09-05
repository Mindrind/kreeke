<?php

namespace App;

use App\Voucher;
use Carbon\Carbon;
use Common\Database\Datasource\Datasource;
use Common\Settings\Settings;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PaginateVouchers
{
    public function execute(array $params)
    {
        $builder = Voucher::query();
        $datasource = new Datasource($builder, $params);
        return $datasource->paginate();
    }
}
