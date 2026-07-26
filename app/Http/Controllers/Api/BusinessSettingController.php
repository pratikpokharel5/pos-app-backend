<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSettingRequest;
use App\Http\Resources\BusinessSettingResource;
use App\Models\BusinessSetting;

class BusinessSettingController extends Controller
{
    public function show(): BusinessSettingResource
    {
        return new BusinessSettingResource($this->settings());
    }

    public function update(BusinessSettingRequest $request): BusinessSettingResource
    {
        $settings = $this->settings();
        $settings->update($request->validated());

        return new BusinessSettingResource($settings);
    }

    private function settings(): BusinessSetting
    {
        return BusinessSetting::query()->firstOrCreate([], [
            'business_name' => 'SalePoint',
            'phone' => 'N/A',
        ]);
    }
}
