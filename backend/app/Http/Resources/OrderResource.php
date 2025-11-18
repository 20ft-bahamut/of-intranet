<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'channel_id'         => $this->channel_id,
            'channel_order_no'   => $this->channel_order_no,
            'product_id'         => $this->product_id,
            'product_title'      => $this->product_title,
            'option_title'       => $this->option_title,
            'quantity'           => (int) $this->quantity,
            'tracking_no'        => $this->tracking_no,

            'buyer_name'         => $this->buyer_name,
            'buyer_phone'        => $this->buyer_phone,
            'buyer_postcode'     => $this->buyer_postcode,
            'buyer_addr_full'    => $this->buyer_addr_full,
            'buyer_addr1'        => $this->buyer_addr1,
            'buyer_addr2'        => $this->buyer_addr2,

            'receiver_name'      => $this->receiver_name,
            'receiver_phone'     => $this->receiver_phone,
            'receiver_postcode'  => $this->receiver_postcode,
            'receiver_addr_full' => $this->receiver_addr_full,
            'receiver_addr1'     => $this->receiver_addr1,
            'receiver_addr2'     => $this->receiver_addr2,

            'shipping_request'   => $this->shipping_request,
            'customer_note'      => $this->customer_note,
            'admin_memo'         => $this->admin_memo,

            'ordered_at'         => optional($this->ordered_at)->format('Y-m-d H:i:s'),
            'status_src'         => $this->status_src,
            'status_std'         => $this->status_std,

            'created_at'         => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at'         => optional($this->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}
