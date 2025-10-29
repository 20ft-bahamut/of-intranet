<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelExcelTransformProfileRequest;
use App\Http\Requests\UpdateChannelExcelTransformProfileRequest;
use App\Http\Resources\ChannelExcelTransformProfileResource;
use App\Models\Channel;
use App\Models\ChannelExcelTransformProfile;

class ChannelExcelTransformProfileController extends Controller
{
    // 현재값 보기 (GET /api/v1/channels/{channel}/excel-transform)
    public function show(Channel $channel)
    {
        $profile = ChannelExcelTransformProfile::where('channel_id', $channel->id)->first();

        if (!$profile) {
            return response()->json(['message' => 'not_found'], 404);
        }
        return new ChannelExcelTransformProfileResource($profile);
    }

    // 생성 (POST /api/v1/channels/{channel}/excel-transform)
    public function store(StoreChannelExcelTransformProfileRequest $request, Channel $channel)
    {
        // 채널당 1개 정책: 이미 있으면 409
        $exists = ChannelExcelTransformProfile::where('channel_id', $channel->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'conflict_already_exists'], 409);
        }

        $data = $request->validated();
        $data['channel_id'] = $channel->id;

        $profile = ChannelExcelTransformProfile::create($data);
        return (new ChannelExcelTransformProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    // 수정 (PUT/PATCH /api/v1/channels/{channel}/excel-transform)
    public function update(UpdateChannelExcelTransformProfileRequest $request, Channel $channel)
    {
        $profile = ChannelExcelTransformProfile::where('channel_id', $channel->id)->first();
        if (!$profile) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $profile->fill($request->validated());
        $profile->save();

        return new ChannelExcelTransformProfileResource($profile);
    }

    // 삭제 (DELETE /api/v1/channels/{channel}/excel-transform)
    public function destroy(Channel $channel)
    {
        $profile = ChannelExcelTransformProfile::where('channel_id', $channel->id)->first();
        if (!$profile) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $profile->delete();
        return response()->json(['message' => 'deleted'], 200);
    }
}
