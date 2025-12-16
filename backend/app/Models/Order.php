<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    protected $casts = [
        'raw_meta' => 'json',
        'ordered_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    // 관계
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // 스코프: 검색/필터 통합
    public function scopeApplyFilters(Builder $qb, array $filters): Builder
    {
        if (!empty($filters['channel_id'])) {
            $qb->where('channel_id', (int)$filters['channel_id']);
        }

        if (isset($filters['has_tracking'])) {
            if ($filters['has_tracking'] === '1') {
                $qb->whereNotNull('tracking_no')->where('tracking_no', '<>', '');
            } elseif ($filters['has_tracking'] === '0') {
                $qb->where(function($q){
                    $q->whereNull('tracking_no')->orWhere('tracking_no', '');
                });
            }
        }

        if (!empty($filters['date_from'])) {
            $qb->where('ordered_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $qb->where('ordered_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (!empty($filters['q'])) {
            $like = '%'.(string)$filters['q'].'%';
            $qb->where(function(Builder $w) use ($like) {
                $w->where('channel_order_no', 'like', $like)
                    ->orWhere('product_title', 'like', $like)
                    ->orWhere('option_title', 'like', $like)
                    ->orWhere('buyer_name', 'like', $like)
                    ->orWhere('buyer_phone', 'like', $like)
                    ->orWhere('receiver_name', 'like', $like)
                    ->orWhere('receiver_phone', 'like', $like)
                    ->orWhere('receiver_addr_full', 'like', $like);

                // 조인된 테이블 컬럼(아래는 안전하게 exists/joins로 처리 가능)
            });
        }

        return $qb;
    }

    public function changeLogs()
    {
        return $this->hasMany(\App\Models\OrderChangeLog::class, 'order_id');
    }
}
