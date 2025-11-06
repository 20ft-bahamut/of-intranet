<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelExcelFieldMappingRequest;
use App\Http\Requests\UpdateChannelExcelFieldMappingRequest;
use App\Http\Resources\ChannelExcelFieldMappingResource;
use App\Models\Channel;
use App\Models\ChannelExcelFieldMapping;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ChannelExcelFieldMappingController extends Controller
{
    /**
     * 목록 + 간단 검색 (?q=, ?field_key=)
     */
    public function index(Request $request, Channel $channel)
    {
        $q        = (string) $request->query('q', '');
        $fieldKey = (string) $request->query('field_key', '');

        $items = $channel->excelFieldMappings()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('field_key', 'like', "%{$q}%")
                        ->orWhere('selector_value', 'like', "%{$q}%");
                });
            })
            ->when($fieldKey !== '', fn ($qq) => $qq->where('field_key', mb_strtolower($fieldKey)))
            ->orderBy('field_key')
            ->get();

        return ApiResponse::success(
            data: ChannelExcelFieldMappingResource::collection($items),
            message: '필드 매핑 목록'
        );
    }

    /**
     * 생성 (채널 내 field_key 유니크)
     */
    public function store(StoreChannelExcelFieldMappingRequest $request, Channel $channel)
    {
        $payload = $request->validated();
        // 서버측도 소문자 정규화 보장
        $payload['field_key']  = mb_strtolower($payload['field_key']);
        $payload['channel_id'] = $channel->id;

        try {
            // 유니크 제약 위반 시 23000
            $item = ChannelExcelFieldMapping::create($payload);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return ApiResponse::conflict('해당 field_key의 매핑이 이미 존재합니다.');
            }
            return ApiResponse::serverError('필드 매핑 생성 중 오류가 발생했습니다.');
        }

        return ApiResponse::created(
            data: new ChannelExcelFieldMappingResource($item),
            message: '필드 매핑이 생성되었습니다.'
        );
    }

    /**
     * 단건 조회
     */
    public function show(Channel $channel, ChannelExcelFieldMapping $mapping)
    {
        if ($mapping->channel_id !== $channel->id) {
            return ApiResponse::forbidden('채널 범위를 벗어났습니다.');
        }

        return ApiResponse::success(
            data: new ChannelExcelFieldMappingResource($mapping),
            message: '필드 매핑 조회'
        );
    }

    /**
     * 갱신
     */
    public function update(
        UpdateChannelExcelFieldMappingRequest $request,
        Channel $channel,
        ChannelExcelFieldMapping $mapping
    ) {
        if ($mapping->channel_id !== $channel->id) {
            return ApiResponse::forbidden('채널 범위를 벗어났습니다.');
        }

        $data = $request->validated();
        if (isset($data['field_key'])) {
            $data['field_key'] = mb_strtolower($data['field_key']);
        }

        $mapping->fill($data);

        try {
            $mapping->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return ApiResponse::conflict('해당 field_key의 매핑이 이미 존재합니다.');
            }
            return ApiResponse::serverError('필드 매핑 갱신 중 오류가 발생했습니다.');
        }

        return ApiResponse::success(
            data: new ChannelExcelFieldMappingResource($mapping),
            message: '필드 매핑이 갱신되었습니다.'
        );
    }

    /**
     * 삭제
     */
    public function destroy(Channel $channel, ChannelExcelFieldMapping $mapping)
    {
        if ($mapping->channel_id !== $channel->id) {
            return ApiResponse::forbidden('채널 범위를 벗어났습니다.');
        }

        $mapping->delete();

        return ApiResponse::success(message: '필드 매핑이 삭제되었습니다.');
    }
}
