<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductNameMappingRequest;
use App\Http\Requests\UpdateProductNameMappingRequest;
use App\Http\Resources\ProductNameMappingResource;
use App\Models\Product;
use App\Models\ProductNameMapping;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductNameMappingController extends Controller
{
    public function index(Request $request, Product $product)
    {
        $q = trim((string)$request->query('q', ''));
        $channelId = $request->query('channel_id');

        $items = $product->nameMappings()
            ->when($channelId, fn($qq)=>$qq->where('channel_id', $channelId))
            ->when($q !== '', fn($qq)=>$qq->where(function($w) use($q){
                $w->where('listing_title','like',"%{$q}%")
                    ->orWhere('option_title','like',"%{$q}%")
                    ->orWhere('description','like',"%{$q}%");
            }))
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(ProductNameMappingResource::collection($items));
    }

    public function store(StoreProductNameMappingRequest $request, Product $product)
    {
        $payload = $request->validated();
        // '' -> null 정규화(옵션명 비워도 저장)
        if (array_key_exists('option_title', $payload) && trim((string)$payload['option_title']) === '') {
            $payload['option_title'] = null;
        }
        $payload['product_id'] = $product->id;

        $item = $product->nameMappings()->create($payload);

        return ApiResponse::created(new ProductNameMappingResource($item), '매핑이 등록되었습니다.');
    }

    public function update(UpdateProductNameMappingRequest $request, Product $product, ProductNameMapping $mapping)
    {
        // 안전장치: URL의 product와 resource의 product 일치 확인
        if ($mapping->product_id !== $product->id) {
            return ApiResponse::forbidden('잘못된 접근입니다.');
        }

        $payload = $request->validated();
        if (array_key_exists('option_title', $payload) && trim((string)$payload['option_title']) === '') {
            $payload['option_title'] = null;
        }

        $mapping->fill($payload);
        $mapping->save();

        return ApiResponse::success(new ProductNameMappingResource($mapping), '매핑이 수정되었습니다.');
    }

    public function destroy(Product $product, ProductNameMapping $mapping)
    {
        if ($mapping->product_id !== $product->id) {
            return ApiResponse::forbidden('잘못된 접근입니다.');
        }

        $mapping->delete();
        return ApiResponse::success(null, '매핑이 삭제되었습니다.');
    }
}
