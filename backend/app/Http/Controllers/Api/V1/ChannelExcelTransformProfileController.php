<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelExcelTransformProfileRequest;
use App\Http\Requests\UpdateChannelExcelTransformProfileRequest;
use App\Http\Resources\ChannelExcelTransformProfileResource;
use App\Models\Channel;
use App\Models\ChannelExcelTransformProfile;
use App\Support\ApiResponse;

class ChannelExcelTransformProfileController extends Controller
{
    /**
     * GET /api/v1/by-channel/{channel}/excel-transform
     * - 존재하면 프로필 반환
     * - 없으면 data:null 반환(프론트에서 생성 UI 표시)
     */
    public function show(Channel $channel)
    {
        $profile = $channel->excelTransformProfile()->first();
        if (! $profile) {
            return ApiResponse::success(null);
        }
        return ApiResponse::success(ChannelExcelTransformProfileResource::make($profile));
    }

    /**
     * POST /api/v1/by-channel/{channel}/excel-transform
     * - 채널당 1건만 허용(중복 생성 시 409)
     */
    public function store(Channel $channel, StoreChannelExcelTransformProfileRequest $request)
    {
        if ($channel->excelTransformProfile()->exists()) {
            return ApiResponse::fail('conflict', '이미 프로필이 존재합니다.', 409);
        }

        $data = $request->validated() + ['channel_id' => $channel->id];

        $profile = ChannelExcelTransformProfile::create($data);

        return ApiResponse::success(
            ChannelExcelTransformProfileResource::make($profile),
            '저장되었습니다.',
            201
        );
    }

    /**
     * PUT /api/v1/by-channel/{channel}/excel-transform
     * - 없으면 404
     */
    public function update(Channel $channel, UpdateChannelExcelTransformProfileRequest $request)
    {
        $profile = $channel->excelTransformProfile()->first();
        if (! $profile) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }

        $profile->update($request->validated());

        return ApiResponse::success(
            ChannelExcelTransformProfileResource::make($profile->refresh()),
            '수정되었습니다.'
        );
    }

    /**
     * DELETE /api/v1/by-channel/{channel}/excel-transform
     * - 존재하면 삭제, 없어도 200 OK
     */
    public function destroy(Channel $channel)
    {
        $profile = $channel->excelTransformProfile()->first();
        if ($profile) {
            $profile->delete();
        }
        return ApiResponse::success(null, '삭제되었습니다.');
    }
}
