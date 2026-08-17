<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSettingRequest;
use App\Http\Resources\BusinessSettingResource;
use App\Models\BusinessSetting;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
    public function show(Request $request): BusinessSettingResource
    {
        return new BusinessSettingResource($this->settings($request));
    }

    public function update(BusinessSettingRequest $request): BusinessSettingResource
    {
        $settings = $this->settings($request);
        $data = $request->validated();

        $settings->business->update([
            'name' => $data['business_name'],
            'logo' => $data['logo'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ]);

        $settings->update([
            'tax_enabled' => $data['tax_enabled'] ?? false,
            'default_tax_rate' => $data['default_tax_rate'] ?? 0,
            'online_payment_enabled' => $data['online_payment_enabled'] ?? true,
        ]);

        return new BusinessSettingResource($settings->refresh()->load('business'));
    }

    private function settings(Request $request): BusinessSetting
    {
        return BusinessSetting::query()->firstOrCreate([
            'business_id' => $this->businessId($request),
        ])->load('business');
    }
}
