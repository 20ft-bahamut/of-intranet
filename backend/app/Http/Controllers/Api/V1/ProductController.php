<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     * ?q=검색어(name/code)
     * ?is_active=1|0 (선택)
     */
    public function index()
    {
        $q = (string) request('q', '');
        $isActive = request()->has('is_active') ? request('is_active') : null;

        $query = Product::query();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name','like',"%{$q}%")
                    ->orWhere('code','like',"%{$q}%");
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $list = $query->orderByDesc('id')->get();

        return ApiResponse::success(ProductResource::collection($list));
    }

    /**
     * GET /api/v1/products/{product}
     */
    public function show(Product $product)
    {
        return ApiResponse::success(ProductResource::make($product));
    }

    /**
     * POST /api/v1/products
     * body: name, code, max_merge_qty, spec?, description?, is_active?
     */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return ApiResponse::success(
            ProductResource::make($product),
            '저장되었습니다.',
            201
        );
    }

    /**
     * PUT /api/v1/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return ApiResponse::success(
            ProductResource::make($product->refresh()),
            '수정되었습니다.'
        );
    }

    /**
     * DELETE /api/v1/products/{product}
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return ApiResponse::success(null, '삭제되었습니다.');
    }
}
