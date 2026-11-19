<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        $tables = [
            'users', 'stores', 'categories', 'suppliers', 'products',
            'stock_ins', 'stock_in_details', 'stock_outs', 'stock_out_details',
            'sales', 'sale_details', 'stock_movements',
            'ai_conversations', 'ai_messages',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ditemukan");
        }
    }
}
