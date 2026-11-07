<?php

namespace App\Actions\Assets;

use App\Models\Brand;
use App\Models\Vendor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AssetInput
{
    public static function validate(array $input, bool $partial = false): array
    {
        $id = ['required', 'integer', 'min:1'];
        $optionalId = ['nullable', 'integer', 'min:1'];
        $date = ['date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => $id, 'asset_type_id' => $id, 'physical_location_id' => $id,
            'status_id' => $optionalId, 'condition_id' => $optionalId,
            'purchase_date' => ['required', ...$date],
            'acquisition_cost' => ['required', 'string', 'regex:/\A[0-9]{1,16}(?:\.[0-9]{1,2})?\z/'],
            'serial_number' => ['nullable', 'string', 'max:255'], 'invoice_number' => ['nullable', 'string', 'max:255'],
            'brand_id' => $optionalId, 'vendor_id' => $optionalId,
            'warranty_ends_on' => ['nullable', ...$date],
            'attributes' => ['nullable', 'array'],
        ];
        if ($partial) {
            $rules = array_map(fn (array $rules) => ['sometimes', ...$rules], $rules);
        }

        return Validator::make($input, $rules, attributes: __('assets.fields'))->validate();
    }

    public static function validateReferences(array $data, bool $forWrite): void
    {
        foreach (['brand_id' => Brand::class, 'vendor_id' => Vendor::class] as $key => $model) {
            if (isset($data[$key]) && ! $model::query()->where('is_active', true)->when($forWrite, fn ($query) => $query->sharedLock())->find($data[$key])) {
                throw ValidationException::withMessages([$key => __('assets.invalid_reference')]);
            }
        }
    }
}
