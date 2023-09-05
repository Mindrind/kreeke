<?php

namespace Common\Csv;

use Auth;
use Common\Auth\Jobs\ExportUsersCsv;
use Common\Auth\Jobs\ExportVouchersCsv;
use App\Voucher;

class CommonCsvExportController extends BaseCsvExportController
{
    public function exportUsers()
    {
        return $this->exportUsing(new ExportUsersCsv(Auth::id()));
    }
}
