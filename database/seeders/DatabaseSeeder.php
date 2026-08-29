<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // A dedicated internal-staff account, separate from any customer
        // account, so the admin surface has someone real to log in as.
        User::factory()->create([
            'name' => 'Staff Admin',
            'email' => 'staff@example.com',
            'is_staff' => true,
        ]);

        $this->call(CatalogSeeder::class);

        // A demo account with a negotiated price, so account-specific
        // pricing has something real to check manually: Test User should
        // see 0.25/unit on this SKU regardless of quantity, versus the
        // standard 0.48/0.38/0.30 tiers everyone else gets.
        $demoAccount = Account::create(['name' => 'Demo Hardware Co']);
        $testUser->update(['account_id' => $demoAccount->id, 'role' => 'owner']);

        $negotiatedVariant = ProductVariant::where('sku', 'BOLT-HEX-M8-30-ZN')->first();

        if ($negotiatedVariant !== null) {
            AccountPrice::create([
                'account_id' => $demoAccount->id,
                'product_variant_id' => $negotiatedVariant->id,
                'unit_price' => '0.25',
            ]);
        }
    }
}
