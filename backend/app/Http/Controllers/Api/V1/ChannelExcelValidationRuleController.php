<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelExcelValidationRuleRequest;
use App\Http\Requests\UpdateChannelExcelValidationRuleRequest;
use App\Http\Resources\ChannelExcelValidationRuleResource;
use App\Models\Channel;
use App\Models\ChannelExcelValidationRule;
use App\Support\ApiResponse;

class ChannelExcelValidationRuleController extends Controller
{
    public function index(Channel $channel)
    {
        $list = $channel->excelValidationRules()
            ->orderBy('id', 'asc')
            ->get(['id','channel_id','cell_ref','expected_label','is_required','created_at','updated_at']);

        return ApiResponse::success(ChannelExcelValidationRuleResource::collection($list));
    }

    public function store(Channel $channel, StoreChannelExcelValidationRuleRequest $request)
    {
        $data = $request->validated() + ['channel_id' => $channel->id];

        $row = $channel->excelValidationRules()->create($data);

        return ApiResponse::success(
            ChannelExcelValidationRuleResource::make($row),
            '저장되었습니다.',
            201
        );
    }

    public function update(
        Channel $channel,
        ChannelExcelValidationRule $rule,
        UpdateChannelExcelValidationRuleRequest $request
    ) {
        if ((int)$rule->channel_id !== (int)$channel->id) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }

        $rule->update($request->validated());

        return ApiResponse::success(
            ChannelExcelValidationRuleResource::make($rule->refresh()),
            '수정되었습니다.'
        );
    }

    public function destroy(Channel $channel, ChannelExcelValidationRule $rule)
    {
        if ((int)$rule->channel_id !== (int)$channel->id) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }

        $rule->delete();

        return ApiResponse::success(null, '삭제되었습니다.');
    }
}
