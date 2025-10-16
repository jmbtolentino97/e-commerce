<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            DROP VIEW IF EXISTS product_current_stock;
            CREATE VIEW product_current_stock AS
            SELECT product_id, COALESCE(SUM(quantity), 0) AS stock_on_hand
            FROM inventory_movements
            GROUP BY product_id;
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS product_current_stock');
    }
};
