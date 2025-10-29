<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Http\Requests\UpdateChannelRequest;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    // 목록 (페이지네이션)
    public function index(Request $request)
    {
        $query = Channel::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $kw = $request->string('q');
                $q->where(function ($w) use ($kw) {
                    $w->where('name','like',"%{$kw}%")
                        ->orWhere('code','like',"%{$kw}%");
                });
            })
            ->when($request->filled('active'), function($q) use ($request) {
                $q->where('is_active', (bool) $request->boolean('active'));
            })
            ->orderBy('id','desc');

        $perPage = (int) $request->integer('per_page', 20);
        return ChannelResource::collection($query->paginate($perPage));
    }

    // 단건 조회
    public function show(Channel $channel)
    {
        return new ChannelResource($channel);
    }

    // 생성
    public function store(StoreChannelRequest $request)
    {
        $data = $request->validated();
        // 기본값 보정
        $data['is_excel_encrypted'] = $data['is_excel_encrypted'] ?? false;
        $data['excel_data_start_row'] = $data['excel_data_start_row'] ?? 2;
        $data['is_active'] = $data['is_active'] ?? true;

        $channel = Channel::create($data);
        return (new ChannelResource($channel))
            ->response()
            ->setStatusCode(201);
    }

    // 수정
    public function update(UpdateChannelRequest $request, Channel $channel)
    {
        $channel->fill($request->validated());
        $channel->save();

        return new ChannelResource($channel);
    }

    // 삭제
    public function destroy(Channel $channel)
    {
        $channel->delete();
        return response()->json(['message' => 'deleted'], 200);
    }
}
