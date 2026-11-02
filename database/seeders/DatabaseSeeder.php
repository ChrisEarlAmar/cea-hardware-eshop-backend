<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountPrice;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoUserPassword = env('DEMO_USER_PASSWORD');
        $demoStaffPassword = env('DEMO_STAFF_PASSWORD');

        if (! is_string($demoUserPassword) || $demoUserPassword === '') {
            throw new \RuntimeException('DEMO_USER_PASSWORD must be set before seeding demo data.');
        }

        if (! is_string($demoStaffPassword) || $demoStaffPassword === '') {
            throw new \RuntimeException('DEMO_STAFF_PASSWORD must be set before seeding demo data.');
        }

        DB::transaction(function () use ($demoUserPassword, $demoStaffPassword): void {
            $testUser = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $demoUserPassword,
            ]);

            // A dedicated internal-staff account, separate from any customer
            // account, so the admin surface has someone real to log in as.
            User::create([
                'name' => 'Staff Admin',
                'email' => 'staff@example.com',
                'password' => $demoStaffPassword,
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

            Address::create([
                'user_id' => $testUser->id,
                'account_id' => $demoAccount->id,
                'label' => 'Warehouse',
                'recipient_name' => 'Test User',
                'line1' => '500 Industrial Pkwy',
                'city' => 'Springfield',
                'state' => 'IL',
                'postal_code' => '62701',
                'country' => 'US',
                'is_default' => true,
            ]);

            // A capped member on the demo account, so the B2B spending-limit
            // approval flow (OrderApprovalController) has someone real to check
            // out as: any order this user places over $50 lands in
            // pending_approval for Test User (the account owner) to decide.
            User::create([
                'name' => 'Capped Buyer',
                'email' => 'buyer@example.com',
                'password' => $demoUserPassword,
                'account_id' => $demoAccount->id,
                'role' => 'member',
                'spending_limit_cents' => 5000,
            ]);

            Coupon::create([
                'code' => 'WELCOME10',
                'type' => Coupon::TYPE_PERCENT,
                'value' => '10.00',
                'active' => true,
            ]);

            Coupon::create([
                'code' => 'SAVE5',
                'type' => Coupon::TYPE_FIXED,
                'value' => '5.00',
                'min_order_total' => '25.00',
                'active' => true,
            ]);
        });
    }
}
