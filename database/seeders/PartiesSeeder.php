<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Supplier register and credit customers for the demo station.
 * Accounts are back-dated so opening balances show realistic aging.
 */
class PartiesSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Bahawalpur Oil Depot', 'contact_person' => 'Rana Tariq', 'phone' => '062-2881234', 'address' => 'Depot Road, Bahawalpur', 'payment_terms_days' => 7, 'opening_balance' => 0],
            ['name' => 'City Lubricants Traders', 'contact_person' => 'Sheikh Naveed', 'phone' => '0300-6123987', 'address' => 'Circular Road, Bahawalpur', 'payment_terms_days' => 15, 'opening_balance' => 18500],
            ['name' => 'Indus Fuel Logistics', 'contact_person' => 'Asif Mehmood', 'phone' => '0321-7004455', 'address' => 'Multan Road, Lodhran', 'payment_terms_days' => 3, 'opening_balance' => 42000, 'notes' => 'Tanker freight charges'],
        ];

        foreach ($suppliers as $supplier) {
            $model = Supplier::updateOrCreate(['name' => $supplier['name']], $supplier + ['is_active' => true]);
            $model->forceFill(['created_at' => today()->subDays(45)])->saveQuietly();
        }

        $customers = [
            ['name' => 'Khan Goods Transport', 'phone' => '0300-7712345', 'vehicle_number' => 'TLA-4521', 'address' => 'Truck Adda, Bahawalpur', 'credit_limit' => 600000, 'opening_balance' => 85000],
            ['name' => 'Al-Rehman Flour Mills', 'phone' => '062-2734455', 'vehicle_number' => 'BWP-2210', 'address' => 'Industrial Estate, Bahawalpur', 'credit_limit' => 350000, 'opening_balance' => 40000],
            ['name' => 'City School Vans', 'phone' => '0333-6123456', 'vehicle_number' => 'LES-778', 'address' => 'Model Town A, Bahawalpur', 'credit_limit' => 150000, 'opening_balance' => 0],
            ['name' => 'Haji Akram Farms', 'phone' => '0345-7890123', 'vehicle_number' => 'Tractor MF-385', 'address' => 'Chak 12/BC', 'credit_limit' => 120000, 'opening_balance' => 22000],
            ['name' => 'District Ambulance Service', 'phone' => '1122', 'vehicle_number' => null, 'address' => 'Civil Hospital Road', 'credit_limit' => null, 'opening_balance' => 0, 'notes' => 'Government account, paid monthly by cheque'],
        ];

        foreach ($customers as $i => $customer) {
            $model = Customer::updateOrCreate(['name' => $customer['name']], $customer + ['is_active' => true]);
            $model->forceFill(['created_at' => today()->subDays(70 - $i * 8)])->saveQuietly();
        }
    }
}
