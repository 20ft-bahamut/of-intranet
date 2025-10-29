<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductNameMappingRequest;
use App\Http\Requests\UpdateProductNameMappingRequest;
use App\Http\Resources\ProductNameMappingResource;
use App\Models\Product;
use App\Models\ProductNameMapping;
use Illuminate\Http\Request;


class ProductNameMappingController extends Controller
{
    // 목록: GET /api/v1/products/{product}/product-name-mappings
    public function index(Request $req, Product $product)
    {
        $q = ProductNameMapping::where('product_id', $product->id)
            ->when($req->filled('channel_id'), fn($qq) =>
            $qq->where('channel_id', (int)$req->integer('channel_id')))
            ->when($req->filled('q'), function($qq) use ($req){
                $kw = $req->string('q');
                $qq->where(function($w) use ($kw){
                    $w->where('listing_title','like',"%{$kw}%")
                        ->orWhere('option_title','like',"%{$kw}%");
                });
            })
            ->orderByDesc('id');

        $per = (int)$req->integer('per_page', 20);
        return ProductNameMappingResource::collection($q->paginate($per));
    }

    // 생성: POST /api/v1/products/{product}/product-name-mappings
    public function store(StoreProductNameMappingRequest $req, Product $product)
    {
        $data = $req->validated();
        $data['product_id'] = $product->id;

        // 유니크 중복 체크: (channel_id, product_id, listing_title, option_title)
        $dup = ProductNameMapping::where('channel_id', $data['channel_id'])
            ->where('product_id', $product->id)
            ->where('listing_title', $data['listing_title'])
            ->where('option_title', $data['option_title'] ?? null)
            ->exists();
        if ($dup) return response()->json(['message'=>'duplicate_mapping'], 422);

        $row = ProductNameMapping::create($data);
        return (new ProductNameMappingResource($row))->response()->setStatusCode(201);
    }

    // 수정: PUT/PATCH /api/v1/products/{product}/product-name-mappings/{mapping}
    public function update(UpdateProductNameMappingRequest $req, Product $product, ProductNameMapping $mapping)
    {
        if ($mapping->product_id !== $product->id) {
            return response()->json(['message'=>'product_mismatch'], 404);
        }

        $payload = $req->validated();

        // listing/option/channel이 바뀌면 유니크 재검사
        if (isset($payload['listing_title']) || array_key_exists('option_title',$payload) || isset($payload['channel_id'])) {
            $lt = $payload['listing_title'] ?? $mapping->listing_title;
            $ot = array_key_exists('option_title',$payload) ? $payload['option_title'] : $mapping->option_title;
            $ch = $payload['channel_id'] ?? $mapping->channel_id;

            $dup = ProductNameMapping::where('channel_id',$ch)
                ->where('product_id', $product->id)
                ->where('listing_title',$lt)
                ->where('option_title',$ot)
                ->where('id','<>',$mapping->id)
                ->exists();
            if ($dup) return response()->json(['message'=>'duplicate_mapping'], 422);
        }

        $mapping->fill($payload)->save();
        return new ProductNameMappingResource($mapping);
    }

    // 삭제: DELETE /api/v1/products/{product}/product-name-mappings/{mapping}
    public function destroy(Product $product, ProductNameMapping $mapping)
    {
        if ($mapping->product_id !== $product->id) {
            return response()->json(['message'=>'product_mismatch'], 404);
        }
        $mapping->delete();
        return response()->json(['message'=>'deleted'], 200);
    }
}
