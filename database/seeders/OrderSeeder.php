<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed pending orders with Montreal delivery addresses.
     */
    public function run(): void
    {
        $orders = [
            ['phone' => '514-555-0101', 'address' => '1450 Rue Sainte-Catherine O, Montréal, QC H3G 1R1'],
            ['phone' => '514-555-0102', 'address' => '4200 Boul Saint-Laurent, Montréal, QC H2W 2R2'],
            ['phone' => '514-555-0103', 'address' => '3575 Av du Parc, Montréal, QC H2X 3P9'],
            ['phone' => '514-555-0104', 'address' => '5160 Boul Décarie, Montréal, QC H3X 2H9'],
            ['phone' => '514-555-0105', 'address' => '6700 Rue Saint-Denis, Montréal, QC H2S 2S2'],
            ['phone' => '514-555-0106', 'address' => '2100 Rue Ontario E, Montréal, QC H2K 1V4'],
            ['phone' => '514-555-0107', 'address' => '7070 Av Henri-Julien, Montréal, QC H2S 3S3'],
            ['phone' => '514-555-0108', 'address' => '5800 Rue Sherbrooke O, Montréal, QC H4A 1X3'],
            ['phone' => '514-555-0109', 'address' => '1001 Rue Wellington, Montréal, QC H3C 1V2'],
            ['phone' => '514-555-0110', 'address' => '8500 Rue Hochelaga, Montréal, QC H1L 2M1'],
        ];

        foreach ($orders as $order) {
            Customer::firstOrCreate(['phone' => $order['phone']])
                ->orders()
                ->create(['address' => $order['address']]);
        }
    }
}
