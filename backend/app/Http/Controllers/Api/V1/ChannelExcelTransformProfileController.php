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

    public function store(StoreChannelExcelTransformProfileRequest $request, Channel $channel)
    {
        $data = $request->validated();
        $profile = $channel->excelTransformProfile()->create($data);
        return response()->json(['data' => $profile], 201);
    }

    public function update(UpdateChannelExcelTransformProfileRequest $request, Channel $channel)
    {
        $data = $request->validated();
        $profile = $channel->excelTransformProfile; // unique(채널당 1건)
        abort_if(!$profile, 404);
        $profile->update($data);
        return response()->json(['data' => $profile]);
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
