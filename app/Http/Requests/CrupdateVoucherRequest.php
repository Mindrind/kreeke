<?php

namespace App\Http\Requests;

use Auth;
use Common\Core\BaseFormRequest;
use Illuminate\Validation\Rule;

class CrupdateVoucherRequest extends BaseFormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        $required = $this->getMethod() === 'POST' ? 'required' : '';
        $ignore = $this->getMethod() === 'PUT' ? $this->route('voucher')->id : '';
        $userId = $this->route('voucher') ? $this->route('voucher')->user_id : Auth::id();

        return [
            'name' => [
                $required, 'string', 'min:3',
                Rule::unique('vouchers')->where('user_id', $userId)->ignore($ignore)
            ],
        ];
    }
}
