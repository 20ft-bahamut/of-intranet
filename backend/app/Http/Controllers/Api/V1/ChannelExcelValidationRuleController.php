<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelExcelValidationRuleRequest;
use App\Http\Requests\UpdateChannelExcelValidationRuleRequest;
use App\Http\Resources\ChannelExcelValidationRuleResource;
use App\Models\Channel;
use App\Models\ChannelExcelValidationRule;
use Illuminate\Http\Request;

class ChannelExcelValidationRuleController extends Controller
{
    // 목록(현재값 보기) /api/v1/channels/{channel}/excel-validations
    public function index(Channel $channel)
    {
        $rules = ChannelExcelValidationRule::where('channel_id', $channel->id)
            ->orderBy('cell_ref')
            ->get();

        return ChannelExcelValidationRuleResource::collection($rules);
    }

    // 쓰기(생성) /api/v1/channels/{channel}/excel-validations
    public function store(StoreChannelExcelValidationRuleRequest $request, Channel $channel)
    {
        $data = $request->validated();
        $data['channel_id']  = $channel->id;
        $data['is_required'] = $data['is_required'] ?? true;

        // 동일 cell_ref 중복 방지(테이블에서도 UNIQUE라면 try-catch로 처리 가능)
        $exists = ChannelExcelValidationRule::where('channel_id',$channel->id)
            ->where('cell_ref',$data['cell_ref'])->exists();
        if ($exists) {
            return response()->json([
                'message' => '이미 존재하는 셀 위치입니다.'
            ], 422);
        }

        $rule = ChannelExcelValidationRule::create($data);
        return (new ChannelExcelValidationRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    // 수정 /api/v1/channels/{channel}/excel-validations/{rule}
    public function update(UpdateChannelExcelValidationRuleRequest $request, Channel $channel, ChannelExcelValidationRule $rule)
    {
        // 채널 소속 검증
        if ($rule->channel_id !== $channel->id) {
            return response()->json(['message' => '채널 불일치'], 404);
        }

        $payload = $request->validated();

        // cell_ref를 수정하려는 경우, 중복 방지
        if (isset($payload['cell_ref'])) {
            $dup = ChannelExcelValidationRule::where('channel_id',$channel->id)
                ->where('cell_ref',$payload['cell_ref'])
                ->where('id','<>',$rule->id)
                ->exists();
            if ($dup) {
                return response()->json(['message' => '이미 존재하는 셀 위치입니다.'], 422);
            }
        }

        $rule->fill($payload);
        $rule->save();

        return new ChannelExcelValidationRuleResource($rule);
    }

    // 삭제 /api/v1/channels/{channel}/excel-validations/{rule}
    public function destroy(Channel $channel, ChannelExcelValidationRule $rule)
    {
        if ($rule->channel_id !== $channel->id) {
            return response()->json(['message' => '채널 불일치'], 404);
        }
        $rule->delete();
        return response()->json(['message' => 'deleted'], 200);
    }
}
