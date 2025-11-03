<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductNameMappingRequest;
use App\Http\Requests\UpdateProductNameMappingRequest;
use App\Http\Resources\ProductNameMappingResource;
use App\Models\Product;
use App\Models\ProductNameMapping;
use App\Support\ApiResponse;

class ProductNameMappingController extends Controller
{
    /**
     * GET /api/v1/products/{product}/product-name-mappings
     */
    public function index(Product $product)
    {
        $list = $product->nameMappings()
            ->latest('id')
            ->get(['id','channel_id','product_id','listing_title','option_title','description','created_at','updated_at']);

        return ApiResponse::success(ProductNameMappingResource::collection($list));
    }

    /**
     * POST /api/v1/products/{product}/product-name-mappings
     * body: channel_id, listing_title, option_title, description?
     */
    public function store(Product $product, StoreProductNameMappingRequest $request)
    {
        $data = $request->validated() + ['product_id' => $product->id];
        $row  = $product->nameMappings()->create($data);

        return ApiResponse::success(ProductNameMappingResource::make($row), '저장되었습니다.', 201);
    }

    /**
     * PUT /api/v1/products/{product}/product-name-mappings/{mapping}
     */
    public function update(Product $product, ProductNameMapping $mapping, UpdateProductNameMappingRequest $request)
    {
        // URL의 product와 레코드의 product_id 일치 검증
        if ((int)$mapping->product_id !== (int)$product->id) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }

        $mapping->update($request->validated());

        return ApiResponse::success(ProductNameMappingResource::make($mapping->refresh()), '수정되었습니다.');
    }

    /**
     * DELETE /api/v1/products/{product}/product-name-mappings/{mapping}
     */
    public function destroy(Product $product, ProductNameMapping $mapping)
    {
        if ((int)$mapping->product_id !== (int)$product->id) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }

        $mapping->delete();

        return ApiResponse::success(null, '삭제되었습니다.');
    }
}
