<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Enums\OfflinePaymentMethod;
use App\Modules\Commerce\Support\Gst;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Staff record a course a learner paid for outside the website. */
class RecordOfflinePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('gstin'))) {
            $gstin = Str::upper(trim($this->input('gstin')));
            $this->merge(['gstin' => $gstin === '' ? null : $gstin]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The learner's id (uuid).
            'user' => ['required', 'string', Rule::exists('users', 'uuid')],
            // The course's slug.
            'course' => ['required', 'string', Rule::exists('courses', 'slug')],
            'edition_id' => ['nullable', 'integer'],
            // Amount received in paise, GST included. At most the course price.
            'amount' => ['required', 'integer', 'min:100'],
            'method' => ['required', Rule::enum(OfflinePaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            // Billing details for the GST invoice.
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'state_code' => ['required', 'string', Rule::in(array_keys(Gst::STATES))],
            'gstin' => ['nullable', 'string', 'size:15'],
            'business_name' => ['nullable', 'required_with:gstin', 'string', 'max:160'],
            'address' => ['nullable', 'required_with:gstin', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => 'The payment date can’t be in the future.',
            'business_name.required_with' => 'Enter the registered business name for this GSTIN.',
            'address.required_with' => 'Enter the business address for the tax invoice.',
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $gstin = $this->input('gstin');
            if (is_string($gstin) && $gstin !== '' && ! Gst::validGstin($gstin, (string) $this->input('state_code'))) {
                $validator->errors()->add('gstin', 'This GSTIN isn’t valid for the selected state.');
            }
        }];
    }
}
