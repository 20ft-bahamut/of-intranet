<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<SQL
CREATE OR REPLACE VIEW vw_order_shipping_status AS
SELECT
    oi.order_id,
    COUNT(*) AS items_total,
    SUM(oi.has_tracking) AS items_with_tracking,
    (COUNT(*) - SUM(oi.has_tracking)) AS items_missing_tracking,
    CASE
        WHEN SUM(oi.has_tracking) = 0 THEN 'UNASSIGNED'
        WHEN SUM(oi.has_tracking) = COUNT(*) THEN 'ASSIGNED'
        ELSE 'PARTIAL'
    END AS ship_status
FROM order_items oi
GROUP BY oi.order_id
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_order_shipping_status');
    }

};
