<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->prices(10, 100);
    }

    public function down(): void
    {
        $this->prices(9.99, 99.99);
    }

    private function prices(float $monthly, float $yearly): void
    {
        foreach (['pro-monthly' => $monthly, 'pro-yearly' => $yearly] as $slug => $price) {
            DB::table('plans')->where('slug', $slug)->where('type', 'pro')->where('currency', 'USD')->update(['price' => $price]);
        }
    }
};
