<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Http\Requests\UpdateChannelRequest;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Support\ApiResponse;

class ChannelController extends Controller
{
    public function index()
    {
        $q = (string) request('q', '');
        $isActive = request()->has('is_active') ? request('is_active') : null;

        $query = Channel::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $list = $query->orderByDesc('id')->get();

        return ApiResponse::success(ChannelResource::collection($list));
    }

    public function show(Channel $channel)
    {
        return ApiResponse::success(ChannelResource::make($channel));
    }

    public function store(StoreChannelRequest $request)
    {
        $data = $request->validated();

        // 선제적 중복 체크 (DB 제약도 함께 있어야 함)
        if (Channel::where('code', $data['code'])->exists()) {
            return ApiResponse::fail('conflict', '채널 코드가 이미 존재합니다.', 409);
        }

        $channel = Channel::create($data);

        return ApiResponse::success(ChannelResource::make($channel), '저장되었습니다.', 201);
    }

    public function update(UpdateChannelRequest $request, Channel $channel)
    {
        $data = $request->validated();

        if (isset($data['code'])) {
            $dup = Channel::where('id', '!=', $channel->id)
                ->where('code', $data['code'])
                ->exists();
            if ($dup) {
                return ApiResponse::fail('conflict', '채널 코드가 이미 존재합니다.', 409);
            }
        }

        $channel->update($data);

        return ApiResponse::success(ChannelResource::make($channel->refresh()), '수정되었습니다.');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return ApiResponse::success(null, '삭제되었습니다.');
    }
}
