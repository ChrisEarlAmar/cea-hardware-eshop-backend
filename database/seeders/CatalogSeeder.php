<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Synonym;
use App\Models\VariantAttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $bolts = Category::create(['name' => 'Bolts', 'slug' => 'bolts']);
        $bearings = Category::create(['name' => 'Bearings', 'slug' => 'bearings']);
        $washers = Category::create(['name' => 'Washers', 'slug' => 'washers']);
        $nuts = Category::create(['name' => 'Nuts', 'slug' => 'nuts']);
        $hammers = Category::create(['name' => 'Hammers', 'slug' => 'hammers']);
        $screws = Category::create(['name' => 'Screws', 'slug' => 'screws']);
        $wrenches = Category::create(['name' => 'Wrenches', 'slug' => 'wrenches']);
        $screwdrivers = Category::create(['name' => 'Screwdrivers', 'slug' => 'screwdrivers']);
        $drillBits = Category::create(['name' => 'Drill Bits', 'slug' => 'drill-bits']);
        $anchors = Category::create(['name' => 'Anchors', 'slug' => 'anchors']);
        $pliers = Category::create(['name' => 'Pliers', 'slug' => 'pliers']);
        $measuring = Category::create(['name' => 'Measuring Tools', 'slug' => 'measuring-tools']);
        $safety = Category::create(['name' => 'Safety Gear', 'slug' => 'safety-gear']);
        $adhesives = Category::create(['name' => 'Adhesives & Sealants', 'slug' => 'adhesives-sealants']);

        $material = Attribute::create(['name' => 'Material', 'key' => 'material', 'data_type' => 'string', 'unit' => null]);
        $threadSize = Attribute::create(['name' => 'Thread Size', 'key' => 'thread_size', 'data_type' => 'string', 'unit' => null]);
        $length = Attribute::create(['name' => 'Length', 'key' => 'length', 'data_type' => 'decimal', 'unit' => 'mm']);
        $weight = Attribute::create(['name' => 'Weight', 'key' => 'weight', 'data_type' => 'decimal', 'unit' => 'g']);
        $headStyle = Attribute::create(['name' => 'Head Style', 'key' => 'head_style', 'data_type' => 'string', 'unit' => null]);

        $boreDiameter = Attribute::create(['name' => 'Bore Diameter', 'key' => 'bore_diameter', 'data_type' => 'decimal', 'unit' => 'mm']);
        $outerDiameter = Attribute::create(['name' => 'Outer Diameter', 'key' => 'outer_diameter', 'data_type' => 'decimal', 'unit' => 'mm']);
        $sealType = Attribute::create(['name' => 'Seal Type', 'key' => 'seal_type', 'data_type' => 'string', 'unit' => null]);
        $loadRating = Attribute::create(['name' => 'Load Rating', 'key' => 'load_rating', 'data_type' => 'decimal', 'unit' => 'kg']);

        $diameter = Attribute::create(['name' => 'Diameter', 'key' => 'diameter', 'data_type' => 'decimal', 'unit' => 'mm']);
        $thickness = Attribute::create(['name' => 'Thickness', 'key' => 'thickness', 'data_type' => 'decimal', 'unit' => 'mm']);

        $nutHeight = Attribute::create(['name' => 'Height', 'key' => 'nut_height', 'data_type' => 'decimal', 'unit' => 'mm']);

        $handleMaterial = Attribute::create(['name' => 'Handle Material', 'key' => 'handle_material', 'data_type' => 'string', 'unit' => null]);
        $hammerWeight = Attribute::create(['name' => 'Weight', 'key' => 'hammer_weight', 'data_type' => 'decimal', 'unit' => 'oz']);
        $headFinish = Attribute::create(['name' => 'Head Finish', 'key' => 'head_finish', 'data_type' => 'string', 'unit' => null]);

        $driveType = Attribute::create(['name' => 'Drive Type', 'key' => 'drive_type', 'data_type' => 'string', 'unit' => null]);
        $gauge = Attribute::create(['name' => 'Gauge', 'key' => 'gauge', 'data_type' => 'string', 'unit' => null]);

        $wrenchSize = Attribute::create(['name' => 'Size', 'key' => 'wrench_size', 'data_type' => 'string', 'unit' => null]);
        $wrenchMaterial = Attribute::create(['name' => 'Material', 'key' => 'wrench_material', 'data_type' => 'string', 'unit' => null]);
        $wrenchFinish = Attribute::create(['name' => 'Finish', 'key' => 'wrench_finish', 'data_type' => 'string', 'unit' => null]);

        $tipType = Attribute::create(['name' => 'Tip Type', 'key' => 'tip_type', 'data_type' => 'string', 'unit' => null]);
        $tipSize = Attribute::create(['name' => 'Tip Size', 'key' => 'tip_size', 'data_type' => 'string', 'unit' => null]);

        $bitMaterial = Attribute::create(['name' => 'Material', 'key' => 'bit_material', 'data_type' => 'string', 'unit' => null]);
        $bitDiameter = Attribute::create(['name' => 'Diameter', 'key' => 'bit_diameter', 'data_type' => 'decimal', 'unit' => 'mm']);
        $bitLength = Attribute::create(['name' => 'Length', 'key' => 'bit_length', 'data_type' => 'decimal', 'unit' => 'mm']);

        $anchorMaterial = Attribute::create(['name' => 'Material', 'key' => 'anchor_material', 'data_type' => 'string', 'unit' => null]);
        $anchorDiameter = Attribute::create(['name' => 'Diameter', 'key' => 'anchor_diameter', 'data_type' => 'decimal', 'unit' => 'mm']);
        $maxLoad = Attribute::create(['name' => 'Max Load', 'key' => 'max_load', 'data_type' => 'decimal', 'unit' => 'kg']);

        $jawLength = Attribute::create(['name' => 'Jaw Length', 'key' => 'jaw_length', 'data_type' => 'decimal', 'unit' => 'mm']);
        $plierMaterial = Attribute::create(['name' => 'Material', 'key' => 'plier_material', 'data_type' => 'string', 'unit' => null]);

        $tapeLength = Attribute::create(['name' => 'Length', 'key' => 'tape_length', 'data_type' => 'string', 'unit' => null]);
        $bladeWidth = Attribute::create(['name' => 'Blade Width', 'key' => 'blade_width', 'data_type' => 'decimal', 'unit' => 'mm']);

        $gloveSize = Attribute::create(['name' => 'Size', 'key' => 'glove_size', 'data_type' => 'string', 'unit' => null]);
        $gloveMaterial = Attribute::create(['name' => 'Material', 'key' => 'glove_material', 'data_type' => 'string', 'unit' => null]);

        $volume = Attribute::create(['name' => 'Volume', 'key' => 'volume', 'data_type' => 'string', 'unit' => null]);
        $cureTime = Attribute::create(['name' => 'Cure Time', 'key' => 'cure_time', 'data_type' => 'string', 'unit' => null]);

        // Thread size/material/length select the exact purchasable bolt;
        // weight and head style just describe it.
        $this->attachAttributes($bolts, [
            [$material, true],
            [$threadSize, true],
            [$length, true],
            [$weight, false],
            [$headStyle, false],
        ]);

        // Bore/outer diameter select the exact purchasable bearing; seal
        // type and load rating just describe it.
        $this->attachAttributes($bearings, [
            [$boreDiameter, true],
            [$outerDiameter, true],
            [$sealType, false],
            [$loadRating, false],
        ]);

        $this->attachAttributes($washers, [
            [$material, true],
            [$diameter, true],
            [$thickness, false],
        ]);

        $this->attachAttributes($nuts, [
            [$material, true],
            [$threadSize, true],
            [$nutHeight, false],
        ]);

        $this->attachAttributes($hammers, [
            [$handleMaterial, true],
            [$hammerWeight, true],
            [$headFinish, false],
        ]);

        $this->attachAttributes($screws, [
            [$material, true],
            [$driveType, true],
            [$length, true],
            [$gauge, false],
            [$headStyle, false],
        ]);

        $this->attachAttributes($wrenches, [
            [$wrenchSize, true],
            [$wrenchMaterial, false],
            [$wrenchFinish, false],
        ]);

        $this->attachAttributes($screwdrivers, [
            [$tipType, true],
            [$tipSize, true],
            [$handleMaterial, false],
        ]);

        $this->attachAttributes($drillBits, [
            [$bitMaterial, true],
            [$bitDiameter, true],
            [$bitLength, false],
        ]);

        $this->attachAttributes($anchors, [
            [$anchorMaterial, true],
            [$anchorDiameter, true],
            [$maxLoad, false],
        ]);

        $this->attachAttributes($pliers, [
            [$jawLength, true],
            [$plierMaterial, false],
        ]);

        $this->attachAttributes($measuring, [
            [$tapeLength, true],
            [$bladeWidth, false],
        ]);

        $this->attachAttributes($safety, [
            [$gloveSize, true],
            [$gloveMaterial, false],
        ]);

        $this->attachAttributes($adhesives, [
            [$volume, true],
            [$cureTime, false],
        ]);

        $hexBolt = $this->createProduct(
            $bolts,
            'Hex Bolt',
            'hex-bolt',
            'A standard hexagonal-head bolt for general fastening use.',
            '1e3a5f'
        );

        $this->createVariants($hexBolt, [
            ['sku' => 'BOLT-HEX-M8-20-SS', 'stock' => 150, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M8', 'length' => '20', 'weight' => '9.5', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '0.55'], [50, '0.45'], [200, '0.38'],
            ]],
            ['sku' => 'BOLT-HEX-M8-30-SS', 'stock' => 120, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M8', 'length' => '30', 'weight' => '12.8', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '0.62'], [50, '0.50'], [200, '0.42'],
            ]],
            ['sku' => 'BOLT-HEX-M8-30-ZN', 'stock' => 200, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '30', 'weight' => '11.9', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '0.48'], [50, '0.38'], [200, '0.30'],
            ]],
            ['sku' => 'BOLT-HEX-M10-40-SS', 'stock' => 80, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M10', 'length' => '40', 'weight' => '22.1', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '0.95'], [50, '0.80'], [200, '0.65'],
            ]],
        ]);

        $carriageBolt = $this->createProduct(
            $bolts,
            'Carriage Bolt',
            'carriage-bolt',
            'A rounded-head bolt with a square shoulder to prevent turning.',
            '4a5568'
        );

        $this->createVariants($carriageBolt, [
            ['sku' => 'BOLT-CAR-M8-30-ZN', 'stock' => 90, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '30', 'weight' => '13.2', 'head_style' => 'Round',
            ], 'tiers' => [
                [1, '0.58'], [50, '0.48'], [200, '0.40'],
            ]],
            ['sku' => 'BOLT-CAR-M10-40-ZN', 'stock' => 60, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'length' => '40', 'weight' => '24.0', 'head_style' => 'Round',
            ], 'tiers' => [
                [1, '0.99'], [50, '0.85'], [200, '0.70'],
            ]],
        ]);

        $ballBearing = $this->createProduct(
            $bearings,
            'Ball Bearing',
            'ball-bearing',
            'A deep-groove ball bearing for radial and light axial loads.',
            '2d3748'
        );

        $this->createVariants($ballBearing, [
            ['sku' => 'BRG-BALL-608', 'stock' => 300, 'values' => [
                'bore_diameter' => '8', 'outer_diameter' => '22', 'seal_type' => 'Rubber Sealed', 'load_rating' => '3.35',
            ], 'tiers' => [
                [1, '1.20'], [50, '1.00'], [200, '0.85'],
            ]],
            ['sku' => 'BRG-BALL-6200', 'stock' => 150, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '30', 'seal_type' => 'Rubber Sealed', 'load_rating' => '5.10',
            ], 'tiers' => [
                [1, '2.10'], [50, '1.80'], [200, '1.55'],
            ]],
            ['sku' => 'BRG-BALL-6300', 'stock' => 75, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '35', 'seal_type' => 'Open', 'load_rating' => '7.65',
            ], 'tiers' => [
                [1, '3.40'], [50, '2.90'], [200, '2.50'],
            ]],
        ]);

        $flatWasher = $this->createProduct(
            $washers,
            'Flat Washer',
            'flat-washer',
            'A flat, load-distributing washer for use under a nut or bolt head.',
            '744210'
        );

        $this->createVariants($flatWasher, [
            ['sku' => 'WSH-FLAT-SS-8', 'stock' => 500, 'values' => [
                'material' => 'Stainless Steel', 'diameter' => '8', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.06'], [100, '0.04'], [500, '0.03'],
            ]],
            ['sku' => 'WSH-FLAT-SS-10', 'stock' => 400, 'values' => [
                'material' => 'Stainless Steel', 'diameter' => '10', 'thickness' => '2',
            ], 'tiers' => [
                [1, '0.08'], [100, '0.06'], [500, '0.04'],
            ]],
            ['sku' => 'WSH-FLAT-ZN-8', 'stock' => 600, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '8', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.04'], [100, '0.03'], [500, '0.02'],
            ]],
            ['sku' => 'WSH-FLAT-ZN-10', 'stock' => 550, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '10', 'thickness' => '2',
            ], 'tiers' => [
                [1, '0.05'], [100, '0.04'], [500, '0.03'],
            ]],
        ]);

        $lockWasher = $this->createProduct(
            $washers,
            'Lock Washer',
            'lock-washer',
            'A split-ring washer that resists loosening under vibration.',
            '975a16'
        );

        $this->createVariants($lockWasher, [
            ['sku' => 'WSH-LOCK-SS-8', 'stock' => 400, 'values' => [
                'material' => 'Stainless Steel', 'diameter' => '8', 'thickness' => '1.2',
            ], 'tiers' => [
                [1, '0.07'], [100, '0.05'], [500, '0.03'],
            ]],
            ['sku' => 'WSH-LOCK-ZN-8', 'stock' => 450, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '8', 'thickness' => '1.2',
            ], 'tiers' => [
                [1, '0.05'], [100, '0.03'], [500, '0.02'],
            ]],
            ['sku' => 'WSH-LOCK-ZN-10', 'stock' => 350, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '10', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.06'], [100, '0.04'], [500, '0.03'],
            ]],
        ]);

        $hexNut = $this->createProduct(
            $nuts,
            'Hex Nut',
            'hex-nut',
            'A standard hexagonal nut for use with matching thread-size bolts.',
            '22543d'
        );

        $this->createVariants($hexNut, [
            ['sku' => 'NUT-HEX-SS-M8', 'stock' => 500, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M8', 'nut_height' => '6.5',
            ], 'tiers' => [
                [1, '0.15'], [100, '0.10'], [500, '0.07'],
            ]],
            ['sku' => 'NUT-HEX-ZN-M8', 'stock' => 600, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '6.5',
            ], 'tiers' => [
                [1, '0.10'], [100, '0.07'], [500, '0.05'],
            ]],
            ['sku' => 'NUT-HEX-ZN-M10', 'stock' => 450, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'nut_height' => '8',
            ], 'tiers' => [
                [1, '0.15'], [100, '0.11'], [500, '0.08'],
            ]],
        ]);

        $wingNut = $this->createProduct(
            $nuts,
            'Wing Nut',
            'wing-nut',
            'A nut with two projecting wings for tightening by hand, no tools required.',
            '276749'
        );

        $this->createVariants($wingNut, [
            ['sku' => 'NUT-WING-ZN-M8', 'stock' => 200, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '12',
            ], 'tiers' => [
                [1, '0.35'], [100, '0.28'], [500, '0.22'],
            ]],
            ['sku' => 'NUT-WING-ZN-M10', 'stock' => 150, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'nut_height' => '14',
            ], 'tiers' => [
                [1, '0.45'], [100, '0.36'], [500, '0.28'],
            ]],
        ]);

        $clawHammer = $this->createProduct(
            $hammers,
            'Claw Hammer',
            'claw-hammer',
            'A general-purpose hammer with a curved claw for pulling nails.',
            '742a2a'
        );

        $this->createVariants($clawHammer, [
            ['sku' => 'HMR-CLAW-WD-16', 'stock' => 60, 'values' => [
                'handle_material' => 'Wood', 'hammer_weight' => '16', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '12.99'], [10, '11.50'], [50, '9.99'],
            ]],
            ['sku' => 'HMR-CLAW-FG-16', 'stock' => 45, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '16', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '15.99'], [10, '14.20'], [50, '12.50'],
            ]],
            ['sku' => 'HMR-CLAW-FG-20', 'stock' => 30, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '20', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '17.99'], [10, '15.99'], [50, '13.99'],
            ]],
        ]);

        $ballPeenHammer = $this->createProduct(
            $hammers,
            'Ball-Peen Hammer',
            'ball-peen-hammer',
            'A machinist\'s hammer with a rounded peen for shaping metal and setting rivets.',
            '9b2c2c'
        );

        $this->createVariants($ballPeenHammer, [
            ['sku' => 'HMR-PEEN-ST-8', 'stock' => 40, 'values' => [
                'handle_material' => 'Steel', 'hammer_weight' => '8', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '9.99'], [10, '8.75'], [50, '7.50'],
            ]],
            ['sku' => 'HMR-PEEN-ST-16', 'stock' => 25, 'values' => [
                'handle_material' => 'Steel', 'hammer_weight' => '16', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '13.99'], [10, '12.20'], [50, '10.50'],
            ]],
        ]);

        $woodScrew = $this->createProduct(
            $screws,
            'Wood Screw',
            'wood-screw',
            'A sharp-tipped screw for fastening into wood.',
            'b7791f'
        );

        $this->createVariants($woodScrew, [
            ['sku' => 'SCR-WOOD-PH-32-ZN', 'stock' => 800, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '32', 'gauge' => '#8', 'head_style' => 'Flat',
            ], 'tiers' => [
                [1, '0.05'], [200, '0.04'], [1000, '0.03'],
            ]],
            ['sku' => 'SCR-WOOD-PH-50-ZN', 'stock' => 650, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '50', 'gauge' => '#8', 'head_style' => 'Flat',
            ], 'tiers' => [
                [1, '0.07'], [200, '0.05'], [1000, '0.04'],
            ]],
            ['sku' => 'SCR-WOOD-TX-38-SS', 'stock' => 400, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Torx', 'length' => '38', 'gauge' => '#10', 'head_style' => 'Flat',
            ], 'tiers' => [
                [1, '0.11'], [200, '0.09'], [1000, '0.07'],
            ]],
        ]);

        $selfTappingScrew = $this->createProduct(
            $screws,
            'Self-Tapping Screw',
            'self-tapping-screw',
            'A screw that cuts its own thread into sheet metal or plastic as it drives.',
            'c05621'
        );

        $this->createVariants($selfTappingScrew, [
            ['sku' => 'SCR-TAP-PH-25-ZN', 'stock' => 700, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '25', 'gauge' => '#6', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.06'], [200, '0.05'], [1000, '0.04'],
            ]],
            ['sku' => 'SCR-TAP-PH-38-SS', 'stock' => 500, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Phillips', 'length' => '38', 'gauge' => '#8', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.10'], [200, '0.08'], [1000, '0.06'],
            ]],
        ]);

        $combinationWrench = $this->createProduct(
            $wrenches,
            'Combination Wrench',
            'combination-wrench',
            'An open end on one side and a box end on the other, both sized to fit the same bolt.',
            '2c5282'
        );

        $this->createVariants($combinationWrench, [
            ['sku' => 'WRN-COMB-10MM', 'stock' => 120, 'values' => [
                'wrench_size' => '10mm', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Polished Chrome',
            ], 'tiers' => [
                [1, '6.99'], [10, '5.99'], [30, '4.99'],
            ]],
            ['sku' => 'WRN-COMB-12MM', 'stock' => 110, 'values' => [
                'wrench_size' => '12mm', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Polished Chrome',
            ], 'tiers' => [
                [1, '7.49'], [10, '6.40'], [30, '5.40'],
            ]],
            ['sku' => 'WRN-COMB-14MM', 'stock' => 90, 'values' => [
                'wrench_size' => '14mm', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Polished Chrome',
            ], 'tiers' => [
                [1, '8.29'], [10, '7.10'], [30, '5.99'],
            ]],
        ]);

        $adjustableWrench = $this->createProduct(
            $wrenches,
            'Adjustable Wrench',
            'adjustable-wrench',
            'A wrench with a movable jaw that adapts to a range of bolt sizes.',
            '2b6cb0'
        );

        $this->createVariants($adjustableWrench, [
            ['sku' => 'WRN-ADJ-6IN', 'stock' => 70, 'values' => [
                'wrench_size' => '6 inch', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Satin Chrome',
            ], 'tiers' => [
                [1, '9.99'], [10, '8.60'], [30, '7.30'],
            ]],
            ['sku' => 'WRN-ADJ-10IN', 'stock' => 55, 'values' => [
                'wrench_size' => '10 inch', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Satin Chrome',
            ], 'tiers' => [
                [1, '14.99'], [10, '12.90'], [30, '10.99'],
            ]],
        ]);

        $phillipsScrewdriver = $this->createProduct(
            $screwdrivers,
            'Phillips Screwdriver',
            'phillips-screwdriver',
            'A cross-tip screwdriver for Phillips-head fasteners.',
            '702459'
        );

        $this->createVariants($phillipsScrewdriver, [
            ['sku' => 'SDR-PH-1', 'stock' => 200, 'values' => [
                'tip_type' => 'Phillips', 'tip_size' => '#1', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '4.49'], [20, '3.80'], [100, '3.20'],
            ]],
            ['sku' => 'SDR-PH-2', 'stock' => 250, 'values' => [
                'tip_type' => 'Phillips', 'tip_size' => '#2', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '4.99'], [20, '4.20'], [100, '3.50'],
            ]],
        ]);

        $flatheadScrewdriver = $this->createProduct(
            $screwdrivers,
            'Flathead Screwdriver',
            'flathead-screwdriver',
            'A flat-tip screwdriver for slotted fasteners.',
            '6b46c1'
        );

        $this->createVariants($flatheadScrewdriver, [
            ['sku' => 'SDR-FH-3-16', 'stock' => 180, 'values' => [
                'tip_type' => 'Flathead', 'tip_size' => '3/16 inch', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '4.29'], [20, '3.60'], [100, '3.00'],
            ]],
            ['sku' => 'SDR-FH-1-4', 'stock' => 150, 'values' => [
                'tip_type' => 'Flathead', 'tip_size' => '1/4 inch', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '4.79'], [20, '4.00'], [100, '3.30'],
            ]],
        ]);

        $twistDrillBit = $this->createProduct(
            $drillBits,
            'Twist Drill Bit',
            'twist-drill-bit',
            'A general-purpose spiral-fluted bit for drilling wood, plastic, and light metal.',
            '1a365d'
        );

        $this->createVariants($twistDrillBit, [
            ['sku' => 'BIT-TWIST-HSS-3', 'stock' => 300, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '3', 'bit_length' => '60',
            ], 'tiers' => [
                [1, '1.20'], [50, '1.00'], [200, '0.80'],
            ]],
            ['sku' => 'BIT-TWIST-HSS-5', 'stock' => 260, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '5', 'bit_length' => '86',
            ], 'tiers' => [
                [1, '1.60'], [50, '1.35'], [200, '1.10'],
            ]],
            ['sku' => 'BIT-TWIST-HSS-8', 'stock' => 180, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '8', 'bit_length' => '117',
            ], 'tiers' => [
                [1, '2.40'], [50, '2.05'], [200, '1.70'],
            ]],
        ]);

        $masonryDrillBit = $this->createProduct(
            $drillBits,
            'Masonry Drill Bit',
            'masonry-drill-bit',
            'A carbide-tipped bit for drilling into brick, block, and concrete.',
            '2a4365'
        );

        $this->createVariants($masonryDrillBit, [
            ['sku' => 'BIT-MASON-CT-6', 'stock' => 150, 'values' => [
                'bit_material' => 'Carbide-Tipped', 'bit_diameter' => '6', 'bit_length' => '100',
            ], 'tiers' => [
                [1, '3.20'], [50, '2.70'], [200, '2.20'],
            ]],
            ['sku' => 'BIT-MASON-CT-10', 'stock' => 100, 'values' => [
                'bit_material' => 'Carbide-Tipped', 'bit_diameter' => '10', 'bit_length' => '150',
            ], 'tiers' => [
                [1, '4.50'], [50, '3.80'], [200, '3.10'],
            ]],
        ]);

        $wallAnchor = $this->createProduct(
            $anchors,
            'Wall Anchor',
            'wall-anchor',
            'A plastic expansion anchor for fastening into drywall.',
            '553c9a'
        );

        $this->createVariants($wallAnchor, [
            ['sku' => 'ANC-WALL-PL-6', 'stock' => 900, 'values' => [
                'anchor_material' => 'Plastic', 'anchor_diameter' => '6', 'max_load' => '15',
            ], 'tiers' => [
                [1, '0.08'], [200, '0.06'], [1000, '0.05'],
            ]],
            ['sku' => 'ANC-WALL-PL-8', 'stock' => 700, 'values' => [
                'anchor_material' => 'Plastic', 'anchor_diameter' => '8', 'max_load' => '25',
            ], 'tiers' => [
                [1, '0.10'], [200, '0.08'], [1000, '0.06'],
            ]],
        ]);

        $concreteAnchor = $this->createProduct(
            $anchors,
            'Concrete Anchor',
            'concrete-anchor',
            'A zinc-plated steel expansion anchor rated for masonry and concrete.',
            '44337a'
        );

        $this->createVariants($concreteAnchor, [
            ['sku' => 'ANC-CONC-ZN-8', 'stock' => 400, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '8', 'max_load' => '60',
            ], 'tiers' => [
                [1, '0.45'], [100, '0.38'], [500, '0.30'],
            ]],
            ['sku' => 'ANC-CONC-ZN-10', 'stock' => 300, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '10', 'max_load' => '90',
            ], 'tiers' => [
                [1, '0.65'], [100, '0.55'], [500, '0.44'],
            ]],
        ]);

        $slipJointPliers = $this->createProduct(
            $pliers,
            'Slip-Joint Pliers',
            'slip-joint-pliers',
            'General-purpose pliers with an adjustable pivot for two jaw-opening ranges.',
            '285e61'
        );

        $this->createVariants($slipJointPliers, [
            ['sku' => 'PLR-SLIP-25', 'stock' => 90, 'values' => [
                'jaw_length' => '25', 'plier_material' => 'Drop-Forged Steel',
            ], 'tiers' => [
                [1, '8.99'], [10, '7.80'], [50, '6.60'],
            ]],
            ['sku' => 'PLR-SLIP-32', 'stock' => 70, 'values' => [
                'jaw_length' => '32', 'plier_material' => 'Drop-Forged Steel',
            ], 'tiers' => [
                [1, '10.99'], [10, '9.50'], [50, '8.00'],
            ]],
        ]);

        $needleNosePliers = $this->createProduct(
            $pliers,
            'Needle-Nose Pliers',
            'needle-nose-pliers',
            'Slim, tapered-jaw pliers for precision work in tight spaces.',
            '234e52'
        );

        $this->createVariants($needleNosePliers, [
            ['sku' => 'PLR-NEEDLE-15', 'stock' => 100, 'values' => [
                'jaw_length' => '15', 'plier_material' => 'Chrome Vanadium Steel',
            ], 'tiers' => [
                [1, '7.49'], [10, '6.40'], [50, '5.40'],
            ]],
            ['sku' => 'PLR-NEEDLE-20', 'stock' => 80, 'values' => [
                'jaw_length' => '20', 'plier_material' => 'Chrome Vanadium Steel',
            ], 'tiers' => [
                [1, '8.99'], [10, '7.70'], [50, '6.50'],
            ]],
        ]);

        $lockingPliers = $this->createProduct(
            $pliers,
            'Locking Pliers',
            'locking-pliers',
            'Adjustable, self-clamping pliers that lock onto a workpiece hands-free.',
            '1a4731'
        );

        $this->createVariants($lockingPliers, [
            ['sku' => 'PLR-LOCK-18', 'stock' => 60, 'values' => [
                'jaw_length' => '18', 'plier_material' => 'Heat-Treated Steel',
            ], 'tiers' => [
                [1, '11.99'], [10, '10.30'], [50, '8.70'],
            ]],
        ]);

        $tapeMeasure = $this->createProduct(
            $measuring,
            'Tape Measure',
            'tape-measure',
            'A retractable steel tape for measuring length on the job.',
            'b83280'
        );

        $this->createVariants($tapeMeasure, [
            ['sku' => 'MSR-TAPE-5M', 'stock' => 130, 'values' => [
                'tape_length' => '5m', 'blade_width' => '19',
            ], 'tiers' => [
                [1, '6.99'], [20, '5.90'], [100, '4.80'],
            ]],
            ['sku' => 'MSR-TAPE-8M', 'stock' => 90, 'values' => [
                'tape_length' => '8m', 'blade_width' => '25',
            ], 'tiers' => [
                [1, '9.99'], [20, '8.50'], [100, '7.00'],
            ]],
        ]);

        $workGloves = $this->createProduct(
            $safety,
            'Work Gloves',
            'work-gloves',
            'Durable, breathable gloves for general handling and light-duty protection.',
            '9c4221'
        );

        $this->createVariants($workGloves, [
            ['sku' => 'SFT-GLV-M', 'stock' => 300, 'values' => [
                'glove_size' => 'M', 'glove_material' => 'Synthetic Leather',
            ], 'tiers' => [
                [1, '4.99'], [50, '4.20'], [200, '3.50'],
            ]],
            ['sku' => 'SFT-GLV-L', 'stock' => 350, 'values' => [
                'glove_size' => 'L', 'glove_material' => 'Synthetic Leather',
            ], 'tiers' => [
                [1, '4.99'], [50, '4.20'], [200, '3.50'],
            ]],
            ['sku' => 'SFT-GLV-XL', 'stock' => 250, 'values' => [
                'glove_size' => 'XL', 'glove_material' => 'Synthetic Leather',
            ], 'tiers' => [
                [1, '5.29'], [50, '4.45'], [200, '3.70'],
            ]],
        ]);

        $siliconeSealant = $this->createProduct(
            $adhesives,
            'Silicone Sealant',
            'silicone-sealant',
            'A flexible, waterproof sealant for gaps and joints around the home or workshop.',
            '4c51bf'
        );

        $this->createVariants($siliconeSealant, [
            ['sku' => 'ADH-SIL-280ML', 'stock' => 220, 'values' => [
                'volume' => '280ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '5.49'], [24, '4.60'], [96, '3.90'],
            ]],
        ]);

        $epoxyAdhesive = $this->createProduct(
            $adhesives,
            'Epoxy Adhesive',
            'epoxy-adhesive',
            'A two-part, high-strength epoxy for bonding metal, wood, and ceramic.',
            '3730a3'
        );

        $this->createVariants($epoxyAdhesive, [
            ['sku' => 'ADH-EPX-25ML', 'stock' => 180, 'values' => [
                'volume' => '25ml', 'cure_time' => '5 minutes',
            ], 'tiers' => [
                [1, '6.99'], [24, '5.90'], [96, '4.90'],
            ]],
            ['sku' => 'ADH-EPX-50ML', 'stock' => 120, 'values' => [
                'volume' => '50ml', 'cure_time' => '5 minutes',
            ], 'tiers' => [
                [1, '11.99'], [24, '10.20'], [96, '8.50'],
            ]],
        ]);

        // --- Catalog expansion: additional products per category so every
        // category listing page has enough items to look like a real
        // storefront (at least 6-8 products each). Existing products above
        // are untouched; everything below is purely additive.

        // --- Additional Bolts ---

        $socketCapBolt = $this->createProduct(
            $bolts,
            'Socket Cap Bolt',
            'socket-cap-bolt',
            'A cylindrical bolt with an internal hex drive for a low-profile, high-torque fastening.',
            '2f6f3e'
        );

        $this->createVariants($socketCapBolt, [
            ['sku' => 'BOLT-SOC-M6-20-SS', 'stock' => 220, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M6', 'length' => '20', 'weight' => '5.2', 'head_style' => 'Socket',
            ], 'tiers' => [
                [1, '0.45'], [50, '0.38'], [200, '0.30'],
            ]],
            ['sku' => 'BOLT-SOC-M6-30-SS', 'stock' => 180, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M6', 'length' => '30', 'weight' => '7.8', 'head_style' => 'Socket',
            ], 'tiers' => [
                [1, '0.55'], [50, '0.46'], [200, '0.36'],
            ]],
            ['sku' => 'BOLT-SOC-M8-25-ZN', 'stock' => 260, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '25', 'weight' => '9.0', 'head_style' => 'Socket',
            ], 'tiers' => [
                [1, '0.40'], [50, '0.32'], [200, '0.25'],
            ]],
        ]);

        $flangeBolt = $this->createProduct(
            $bolts,
            'Flange Bolt',
            'flange-bolt',
            'A bolt with an integrated washer-like flange under the head to spread clamping load.',
            '6b4423'
        );

        $this->createVariants($flangeBolt, [
            ['sku' => 'BOLT-FLG-M8-25-ZN', 'stock' => 140, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '25', 'weight' => '10.5', 'head_style' => 'Flange',
            ], 'tiers' => [
                [1, '0.50'], [50, '0.41'], [200, '0.33'],
            ]],
            ['sku' => 'BOLT-FLG-M10-35-ZN', 'stock' => 95, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'length' => '35', 'weight' => '18.0', 'head_style' => 'Flange',
            ], 'tiers' => [
                [1, '0.75'], [50, '0.63'], [200, '0.51'],
            ]],
        ]);

        $eyeBolt = $this->createProduct(
            $bolts,
            'Eye Bolt',
            'eye-bolt',
            'A bolt with a looped head for attaching cables, hooks, or rope.',
            '3d2b1f'
        );

        $this->createVariants($eyeBolt, [
            ['sku' => 'BOLT-EYE-M8-50-ZN', 'stock' => 85, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '50', 'weight' => '22.0', 'head_style' => 'Eye',
            ], 'tiers' => [
                [1, '0.85'], [50, '0.72'], [200, '0.60'],
            ]],
            ['sku' => 'BOLT-EYE-M10-60-SS', 'stock' => 40, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M10', 'length' => '60', 'weight' => '35.0', 'head_style' => 'Eye',
            ], 'tiers' => [
                [1, '1.60'], [50, '1.35'], [200, '1.10'],
            ]],
        ]);

        $uBolt = $this->createProduct(
            $bolts,
            'U-Bolt',
            'u-bolt',
            'A U-shaped bolt with threaded ends, used to mount pipes and round stock to a surface.',
            '5c3a21'
        );

        $this->createVariants($uBolt, [
            ['sku' => 'BOLT-U-M8-50-ZN', 'stock' => 70, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'length' => '50', 'weight' => '28.0', 'head_style' => 'U-Shape',
            ], 'tiers' => [
                [1, '0.95'], [50, '0.80'], [200, '0.65'],
            ]],
            ['sku' => 'BOLT-U-M10-70-ZN', 'stock' => 50, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'length' => '70', 'weight' => '45.0', 'head_style' => 'U-Shape',
            ], 'tiers' => [
                [1, '1.40'], [50, '1.18'], [200, '0.95'],
            ]],
        ]);

        $lagBolt = $this->createProduct(
            $bolts,
            'Lag Bolt',
            'lag-bolt',
            'A heavy-duty hex-head bolt with a sharp wood-threaded tip for fastening into timber.',
            '7c4a02'
        );

        $this->createVariants($lagBolt, [
            ['sku' => 'BOLT-LAG-8X50-ZN', 'stock' => 130, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => '8mm', 'length' => '50', 'weight' => '25.0', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '0.60'], [50, '0.50'], [200, '0.40'],
            ]],
            ['sku' => 'BOLT-LAG-10X75-ZN', 'stock' => 6, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => '10mm', 'length' => '75', 'weight' => '48.0', 'head_style' => 'Hex',
            ], 'tiers' => [
                [1, '1.05'], [50, '0.88'], [200, '0.70'],
            ]],
        ]);

        // --- Additional Bearings ---

        $rollerBearing = $this->createProduct(
            $bearings,
            'Roller Bearing',
            'roller-bearing',
            'A cylindrical roller bearing for heavy radial loads in industrial equipment.',
            '1a202c'
        );

        $this->createVariants($rollerBearing, [
            ['sku' => 'BRG-ROL-6204', 'stock' => 140, 'values' => [
                'bore_diameter' => '20', 'outer_diameter' => '47', 'seal_type' => 'Open', 'load_rating' => '12.80',
            ], 'tiers' => [
                [1, '4.20'], [50, '3.60'], [200, '3.00'],
            ]],
            ['sku' => 'BRG-ROL-6205', 'stock' => 100, 'values' => [
                'bore_diameter' => '25', 'outer_diameter' => '52', 'seal_type' => 'Open', 'load_rating' => '14.00',
            ], 'tiers' => [
                [1, '4.80'], [50, '4.10'], [200, '3.40'],
            ]],
        ]);

        $thrustBearing = $this->createProduct(
            $bearings,
            'Thrust Bearing',
            'thrust-bearing',
            'A flat bearing designed to support axial loads along the shaft.',
            '2f3a4f'
        );

        $this->createVariants($thrustBearing, [
            ['sku' => 'BRG-THR-51100', 'stock' => 90, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '24', 'seal_type' => 'Open', 'load_rating' => '4.50',
            ], 'tiers' => [
                [1, '2.90'], [50, '2.45'], [200, '2.00'],
            ]],
            ['sku' => 'BRG-THR-51101', 'stock' => 65, 'values' => [
                'bore_diameter' => '12', 'outer_diameter' => '26', 'seal_type' => 'Open', 'load_rating' => '5.20',
            ], 'tiers' => [
                [1, '3.20'], [50, '2.70'], [200, '2.20'],
            ]],
        ]);

        $needleRollerBearing = $this->createProduct(
            $bearings,
            'Needle Roller Bearing',
            'needle-roller-bearing',
            'A slim, high-density roller bearing for compact housings under moderate radial load.',
            '1e293b'
        );

        $this->createVariants($needleRollerBearing, [
            ['sku' => 'BRG-NDL-HK0810', 'stock' => 200, 'values' => [
                'bore_diameter' => '8', 'outer_diameter' => '12', 'seal_type' => 'Open', 'load_rating' => '2.10',
            ], 'tiers' => [
                [1, '3.50'], [50, '2.95'], [200, '2.40'],
            ]],
            ['sku' => 'BRG-NDL-HK1012', 'stock' => 160, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '14', 'seal_type' => 'Open', 'load_rating' => '2.60',
            ], 'tiers' => [
                [1, '3.90'], [50, '3.30'], [200, '2.70'],
            ]],
        ]);

        $pillowBlockBearing = $this->createProduct(
            $bearings,
            'Pillow Block Bearing',
            'pillow-block-bearing',
            'A pre-mounted bearing unit in a cast housing for easy shaft support installation.',
            '312e81'
        );

        $this->createVariants($pillowBlockBearing, [
            ['sku' => 'BRG-PIL-UCP204', 'stock' => 55, 'values' => [
                'bore_diameter' => '20', 'outer_diameter' => '47', 'seal_type' => 'Rubber Sealed', 'load_rating' => '11.50',
            ], 'tiers' => [
                [1, '7.90'], [50, '6.70'], [200, '5.50'],
            ]],
            ['sku' => 'BRG-PIL-UCP205', 'stock' => 35, 'values' => [
                'bore_diameter' => '25', 'outer_diameter' => '52', 'seal_type' => 'Rubber Sealed', 'load_rating' => '13.20',
            ], 'tiers' => [
                [1, '8.60'], [50, '7.30'], [200, '6.00'],
            ]],
        ]);

        $flangedBearing = $this->createProduct(
            $bearings,
            'Flanged Bearing',
            'flanged-bearing',
            'A ball bearing with an integrated mounting flange for direct panel installation.',
            '1e1b4b'
        );

        $this->createVariants($flangedBearing, [
            ['sku' => 'BRG-FLG-F608', 'stock' => 170, 'values' => [
                'bore_diameter' => '8', 'outer_diameter' => '22', 'seal_type' => 'Rubber Sealed', 'load_rating' => '3.35',
            ], 'tiers' => [
                [1, '1.80'], [50, '1.55'], [200, '1.30'],
            ]],
            ['sku' => 'BRG-FLG-F6200', 'stock' => 120, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '30', 'seal_type' => 'Rubber Sealed', 'load_rating' => '5.10',
            ], 'tiers' => [
                [1, '2.60'], [50, '2.20'], [200, '1.85'],
            ]],
        ]);

        $angularContactBearing = $this->createProduct(
            $bearings,
            'Angular Contact Bearing',
            'angular-contact-bearing',
            'A bearing designed with angled races to handle combined radial and axial loads.',
            '4c1d95'
        );

        $this->createVariants($angularContactBearing, [
            ['sku' => 'BRG-ANG-7200', 'stock' => 80, 'values' => [
                'bore_diameter' => '10', 'outer_diameter' => '30', 'seal_type' => 'Open', 'load_rating' => '6.20',
            ], 'tiers' => [
                [1, '3.80'], [50, '3.20'], [200, '2.65'],
            ]],
            ['sku' => 'BRG-ANG-7201', 'stock' => 9, 'values' => [
                'bore_diameter' => '12', 'outer_diameter' => '32', 'seal_type' => 'Open', 'load_rating' => '6.80',
            ], 'tiers' => [
                [1, '4.10'], [50, '3.50'], [200, '2.90'],
            ]],
        ]);

        // --- Additional Washers ---

        $fenderWasher = $this->createProduct(
            $washers,
            'Fender Washer',
            'fender-washer',
            'An oversized flat washer that spreads load across a wider area, ideal for soft materials.',
            '6d4c1f'
        );

        $this->createVariants($fenderWasher, [
            ['sku' => 'WSH-FEND-ZN-8', 'stock' => 480, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '8', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.09'], [100, '0.07'], [500, '0.05'],
            ]],
            ['sku' => 'WSH-FEND-ZN-10', 'stock' => 380, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '10', 'thickness' => '2',
            ], 'tiers' => [
                [1, '0.12'], [100, '0.09'], [500, '0.07'],
            ]],
        ]);

        $squareWasher = $this->createProduct(
            $washers,
            'Square Washer',
            'square-washer',
            'A square-profile washer used with lag screws and heavy timber fasteners.',
            '4a3f6b'
        );

        $this->createVariants($squareWasher, [
            ['sku' => 'WSH-SQ-ZN-8', 'stock' => 260, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '8', 'thickness' => '3',
            ], 'tiers' => [
                [1, '0.15'], [100, '0.12'], [500, '0.09'],
            ]],
            ['sku' => 'WSH-SQ-ZN-10', 'stock' => 190, 'values' => [
                'material' => 'Zinc-Plated Steel', 'diameter' => '10', 'thickness' => '4',
            ], 'tiers' => [
                [1, '0.20'], [100, '0.16'], [500, '0.12'],
            ]],
        ]);

        $rubberWasher = $this->createProduct(
            $washers,
            'Rubber Washer',
            'rubber-washer',
            'A soft, compressible washer that seals against moisture and dampens vibration.',
            '3f3f46'
        );

        $this->createVariants($rubberWasher, [
            ['sku' => 'WSH-RUB-8', 'stock' => 340, 'values' => [
                'material' => 'Rubber', 'diameter' => '8', 'thickness' => '3',
            ], 'tiers' => [
                [1, '0.10'], [100, '0.08'], [500, '0.06'],
            ]],
            ['sku' => 'WSH-RUB-10', 'stock' => 270, 'values' => [
                'material' => 'Rubber', 'diameter' => '10', 'thickness' => '3',
            ], 'tiers' => [
                [1, '0.12'], [100, '0.09'], [500, '0.07'],
            ]],
        ]);

        $copperSealingWasher = $this->createProduct(
            $washers,
            'Copper Sealing Washer',
            'copper-sealing-washer',
            'A soft copper washer used to create a leak-tight seal under banjo bolts and fittings.',
            '8a4a1f'
        );

        $this->createVariants($copperSealingWasher, [
            ['sku' => 'WSH-CU-8', 'stock' => 210, 'values' => [
                'material' => 'Copper', 'diameter' => '8', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.18'], [100, '0.14'], [500, '0.11'],
            ]],
            ['sku' => 'WSH-CU-10', 'stock' => 150, 'values' => [
                'material' => 'Copper', 'diameter' => '10', 'thickness' => '2',
            ], 'tiers' => [
                [1, '0.22'], [100, '0.18'], [500, '0.14'],
            ]],
        ]);

        $nylonWasher = $this->createProduct(
            $washers,
            'Nylon Washer',
            'nylon-washer',
            'A non-conductive, corrosion-proof washer for electrical and outdoor applications.',
            '44403c'
        );

        $this->createVariants($nylonWasher, [
            ['sku' => 'WSH-NY-8', 'stock' => 400, 'values' => [
                'material' => 'Nylon', 'diameter' => '8', 'thickness' => '1.5',
            ], 'tiers' => [
                [1, '0.07'], [100, '0.05'], [500, '0.03'],
            ]],
            ['sku' => 'WSH-NY-10', 'stock' => 300, 'values' => [
                'material' => 'Nylon', 'diameter' => '10', 'thickness' => '2',
            ], 'tiers' => [
                [1, '0.09'], [100, '0.07'], [500, '0.05'],
            ]],
        ]);

        // --- Additional Nuts ---

        $nylonLockNut = $this->createProduct(
            $nuts,
            'Nylon Lock Nut',
            'nylon-lock-nut',
            'A hex nut with a nylon insert that resists loosening under vibration.',
            '14532d'
        );

        $this->createVariants($nylonLockNut, [
            ['sku' => 'NUT-NYL-ZN-M8', 'stock' => 380, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '8',
            ], 'tiers' => [
                [1, '0.20'], [100, '0.16'], [500, '0.12'],
            ]],
            ['sku' => 'NUT-NYL-SS-M8', 'stock' => 220, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M8', 'nut_height' => '8',
            ], 'tiers' => [
                [1, '0.35'], [100, '0.28'], [500, '0.22'],
            ]],
        ]);

        $capNut = $this->createProduct(
            $nuts,
            'Cap Nut',
            'cap-nut',
            'A domed nut that covers the bolt end for a finished look and injury protection.',
            '1b4332'
        );

        $this->createVariants($capNut, [
            ['sku' => 'NUT-CAP-ZN-M8', 'stock' => 260, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '14',
            ], 'tiers' => [
                [1, '0.25'], [100, '0.20'], [500, '0.15'],
            ]],
            ['sku' => 'NUT-CAP-SS-M10', 'stock' => 130, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M10', 'nut_height' => '16',
            ], 'tiers' => [
                [1, '0.55'], [100, '0.45'], [500, '0.36'],
            ]],
        ]);

        $couplingNut = $this->createProduct(
            $nuts,
            'Coupling Nut',
            'coupling-nut',
            'An elongated nut used to join two threaded rods end to end.',
            '065f46'
        );

        $this->createVariants($couplingNut, [
            ['sku' => 'NUT-CPL-ZN-M8', 'stock' => 150, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '30',
            ], 'tiers' => [
                [1, '0.40'], [100, '0.33'], [500, '0.26'],
            ]],
            ['sku' => 'NUT-CPL-ZN-M10', 'stock' => 110, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'nut_height' => '35',
            ], 'tiers' => [
                [1, '0.55'], [100, '0.46'], [500, '0.37'],
            ]],
        ]);

        $flangeNut = $this->createProduct(
            $nuts,
            'Flange Nut',
            'flange-nut',
            'A hex nut with a built-in washer face that distributes load without a separate washer.',
            '166534'
        );

        $this->createVariants($flangeNut, [
            ['sku' => 'NUT-FLG-ZN-M8', 'stock' => 320, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '8',
            ], 'tiers' => [
                [1, '0.18'], [100, '0.14'], [500, '0.11'],
            ]],
            ['sku' => 'NUT-FLG-SS-M10', 'stock' => 140, 'values' => [
                'material' => 'Stainless Steel', 'thread_size' => 'M10', 'nut_height' => '10',
            ], 'tiers' => [
                [1, '0.40'], [100, '0.33'], [500, '0.26'],
            ]],
        ]);

        $squareNut = $this->createProduct(
            $nuts,
            'Square Nut',
            'square-nut',
            'A traditional four-sided nut for slotted channel and heavy timber framing.',
            '3f6212'
        );

        $this->createVariants($squareNut, [
            ['sku' => 'NUT-SQ-ZN-M8', 'stock' => 200, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M8', 'nut_height' => '8',
            ], 'tiers' => [
                [1, '0.16'], [100, '0.13'], [500, '0.10'],
            ]],
            ['sku' => 'NUT-SQ-ZN-M10', 'stock' => 8, 'values' => [
                'material' => 'Zinc-Plated Steel', 'thread_size' => 'M10', 'nut_height' => '10',
            ], 'tiers' => [
                [1, '0.22'], [100, '0.18'], [500, '0.14'],
            ]],
        ]);

        // --- Additional Hammers ---

        $sledgehammer = $this->createProduct(
            $hammers,
            'Sledgehammer',
            'sledgehammer',
            'A heavy, long-handled hammer for demolition and driving stakes.',
            '7f1d1d'
        );

        $this->createVariants($sledgehammer, [
            ['sku' => 'HMR-SLDG-FG-48', 'stock' => 35, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '48', 'head_finish' => 'Matte',
            ], 'tiers' => [
                [1, '24.99'], [10, '21.90'], [50, '18.50'],
            ]],
            ['sku' => 'HMR-SLDG-WD-64', 'stock' => 20, 'values' => [
                'handle_material' => 'Wood', 'hammer_weight' => '64', 'head_finish' => 'Matte',
            ], 'tiers' => [
                [1, '29.99'], [10, '26.50'], [50, '22.90'],
            ]],
        ]);

        $rubberMallet = $this->createProduct(
            $hammers,
            'Rubber Mallet',
            'rubber-mallet',
            'A soft-faced mallet for assembling parts without marring the surface.',
            '431407'
        );

        $this->createVariants($rubberMallet, [
            ['sku' => 'HMR-MALL-WD-16', 'stock' => 90, 'values' => [
                'handle_material' => 'Wood', 'hammer_weight' => '16', 'head_finish' => 'Black Rubber',
            ], 'tiers' => [
                [1, '7.99'], [10, '6.90'], [50, '5.80'],
            ]],
            ['sku' => 'HMR-MALL-FG-24', 'stock' => 65, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '24', 'head_finish' => 'Black Rubber',
            ], 'tiers' => [
                [1, '10.99'], [10, '9.50'], [50, '7.90'],
            ]],
        ]);

        $framingHammer = $this->createProduct(
            $hammers,
            'Framing Hammer',
            'framing-hammer',
            'A long, straight-claw hammer built for fast, high-impact framing work.',
            '78350f'
        );

        $this->createVariants($framingHammer, [
            ['sku' => 'HMR-FRM-FG-20', 'stock' => 50, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '20', 'head_finish' => 'Milled Face',
            ], 'tiers' => [
                [1, '19.99'], [10, '17.50'], [50, '14.90'],
            ]],
            ['sku' => 'HMR-FRM-FG-22', 'stock' => 40, 'values' => [
                'handle_material' => 'Fiberglass', 'hammer_weight' => '22', 'head_finish' => 'Milled Face',
            ], 'tiers' => [
                [1, '21.99'], [10, '19.20'], [50, '16.50'],
            ]],
        ]);

        $tackHammer = $this->createProduct(
            $hammers,
            'Tack Hammer',
            'tack-hammer',
            'A lightweight hammer with a magnetic head for driving upholstery tacks and brads.',
            '92400e'
        );

        $this->createVariants($tackHammer, [
            ['sku' => 'HMR-TACK-WD-7', 'stock' => 75, 'values' => [
                'handle_material' => 'Wood', 'hammer_weight' => '7', 'head_finish' => 'Polished',
            ], 'tiers' => [
                [1, '6.99'], [10, '5.90'], [50, '4.80'],
            ]],
        ]);

        $deadBlowHammer = $this->createProduct(
            $hammers,
            'Dead Blow Hammer',
            'dead-blow-hammer',
            'A shot-filled hammer that minimizes bounce-back and rebound on impact.',
            '451a03'
        );

        $this->createVariants($deadBlowHammer, [
            ['sku' => 'HMR-DEAD-PL-16', 'stock' => 55, 'values' => [
                'handle_material' => 'Polymer', 'hammer_weight' => '16', 'head_finish' => 'Matte',
            ], 'tiers' => [
                [1, '13.99'], [10, '12.20'], [50, '10.50'],
            ]],
            ['sku' => 'HMR-DEAD-PL-32', 'stock' => 30, 'values' => [
                'handle_material' => 'Polymer', 'hammer_weight' => '32', 'head_finish' => 'Matte',
            ], 'tiers' => [
                [1, '18.99'], [10, '16.50'], [50, '13.99'],
            ]],
        ]);

        // --- Additional Screws ---

        $drywallScrew = $this->createProduct(
            $screws,
            'Drywall Screw',
            'drywall-screw',
            'A bugle-head screw engineered for fastening drywall panels to studs.',
            'b45309'
        );

        $this->createVariants($drywallScrew, [
            ['sku' => 'SCR-DRY-PH-25-ZN', 'stock' => 900, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '25', 'gauge' => '#6', 'head_style' => 'Bugle',
            ], 'tiers' => [
                [1, '0.04'], [200, '0.03'], [1000, '0.02'],
            ]],
            ['sku' => 'SCR-DRY-PH-40-ZN', 'stock' => 700, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '40', 'gauge' => '#8', 'head_style' => 'Bugle',
            ], 'tiers' => [
                [1, '0.05'], [200, '0.04'], [1000, '0.03'],
            ]],
        ]);

        $machineScrew = $this->createProduct(
            $screws,
            'Machine Screw',
            'machine-screw',
            'A uniform-diameter screw used with a nut or tapped hole in metal assemblies.',
            '7c2d12'
        );

        $this->createVariants($machineScrew, [
            ['sku' => 'SCR-MACH-PH-12-SS', 'stock' => 500, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Phillips', 'length' => '12', 'gauge' => 'M4', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.09'], [200, '0.07'], [1000, '0.05'],
            ]],
            ['sku' => 'SCR-MACH-PH-20-SS', 'stock' => 350, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Phillips', 'length' => '20', 'gauge' => 'M5', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.14'], [200, '0.11'], [1000, '0.08'],
            ]],
        ]);

        $deckScrew = $this->createProduct(
            $screws,
            'Deck Screw',
            'deck-screw',
            'A weather-resistant, coated screw designed for outdoor decking and timber.',
            '9a3412'
        );

        $this->createVariants($deckScrew, [
            ['sku' => 'SCR-DECK-TX-63-ZN', 'stock' => 600, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Torx', 'length' => '63', 'gauge' => '#9', 'head_style' => 'Flat',
            ], 'tiers' => [
                [1, '0.10'], [200, '0.08'], [1000, '0.06'],
            ]],
            ['sku' => 'SCR-DECK-TX-75-SS', 'stock' => 380, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Torx', 'length' => '75', 'gauge' => '#10', 'head_style' => 'Flat',
            ], 'tiers' => [
                [1, '0.18'], [200, '0.15'], [1000, '0.11'],
            ]],
        ]);

        $concreteScrew = $this->createProduct(
            $screws,
            'Concrete Screw',
            'concrete-screw',
            'A hardened, thread-forming screw that anchors directly into pre-drilled masonry.',
            'c2410c'
        );

        $this->createVariants($concreteScrew, [
            ['sku' => 'SCR-CONC-HX-40-ZN', 'stock' => 260, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Hex', 'length' => '40', 'gauge' => '3/16 inch', 'head_style' => 'Hex Washer',
            ], 'tiers' => [
                [1, '0.28'], [200, '0.23'], [1000, '0.18'],
            ]],
            ['sku' => 'SCR-CONC-HX-63-ZN', 'stock' => 190, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Hex', 'length' => '63', 'gauge' => '3/16 inch', 'head_style' => 'Hex Washer',
            ], 'tiers' => [
                [1, '0.40'], [200, '0.33'], [1000, '0.26'],
            ]],
        ]);

        $sheetMetalScrew = $this->createProduct(
            $screws,
            'Sheet Metal Screw',
            'sheet-metal-screw',
            'A hardened, sharp-threaded screw for fastening into thin sheet metal without a nut.',
            '6c2e0f'
        );

        $this->createVariants($sheetMetalScrew, [
            ['sku' => 'SCR-SHT-PH-13-ZN', 'stock' => 750, 'values' => [
                'material' => 'Zinc-Plated Steel', 'drive_type' => 'Phillips', 'length' => '13', 'gauge' => '#8', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.06'], [200, '0.05'], [1000, '0.04'],
            ]],
            ['sku' => 'SCR-SHT-PH-19-SS', 'stock' => 420, 'values' => [
                'material' => 'Stainless Steel', 'drive_type' => 'Phillips', 'length' => '19', 'gauge' => '#10', 'head_style' => 'Pan',
            ], 'tiers' => [
                [1, '0.11'], [200, '0.09'], [1000, '0.07'],
            ]],
        ]);

        // --- Additional Wrenches ---

        $ratchetingWrench = $this->createProduct(
            $wrenches,
            'Ratcheting Wrench',
            'ratcheting-wrench',
            'A combination wrench with a ratcheting box end for fast work in tight spaces.',
            '1e3a8a'
        );

        $this->createVariants($ratchetingWrench, [
            ['sku' => 'WRN-RTC-10MM', 'stock' => 100, 'values' => [
                'wrench_size' => '10mm', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Polished Chrome',
            ], 'tiers' => [
                [1, '9.99'], [10, '8.60'], [30, '7.30'],
            ]],
            ['sku' => 'WRN-RTC-12MM', 'stock' => 85, 'values' => [
                'wrench_size' => '12mm', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Polished Chrome',
            ], 'tiers' => [
                [1, '10.99'], [10, '9.40'], [30, '7.90'],
            ]],
        ]);

        $pipeWrench = $this->createProduct(
            $wrenches,
            'Pipe Wrench',
            'pipe-wrench',
            'A heavy-duty wrench with serrated jaws that bite into round pipe and fittings.',
            '1e40af'
        );

        $this->createVariants($pipeWrench, [
            ['sku' => 'WRN-PIPE-14IN', 'stock' => 45, 'values' => [
                'wrench_size' => '14 inch', 'wrench_material' => 'Cast Iron', 'wrench_finish' => 'Matte Black',
            ], 'tiers' => [
                [1, '18.99'], [10, '16.50'], [30, '13.90'],
            ]],
            ['sku' => 'WRN-PIPE-18IN', 'stock' => 30, 'values' => [
                'wrench_size' => '18 inch', 'wrench_material' => 'Cast Iron', 'wrench_finish' => 'Matte Black',
            ], 'tiers' => [
                [1, '24.99'], [10, '21.90'], [30, '18.50'],
            ]],
        ]);

        $torqueWrench = $this->createProduct(
            $wrenches,
            'Torque Wrench',
            'torque-wrench',
            'A precision wrench that clicks at a preset torque to prevent over-tightening.',
            '3b5998'
        );

        $this->createVariants($torqueWrench, [
            ['sku' => 'WRN-TRQ-3-8IN', 'stock' => 25, 'values' => [
                'wrench_size' => '3/8 inch drive', 'wrench_material' => 'Chrome Vanadium Steel', 'wrench_finish' => 'Satin Chrome',
            ], 'tiers' => [
                [1, '34.99'], [10, '30.90'], [30, '26.50'],
            ]],
        ]);

        $hexKeyWrench = $this->createProduct(
            $wrenches,
            'Hex Key Wrench',
            'hex-key-wrench',
            'An L-shaped hex key for driving socket-head fasteners.',
            '1c2e4a'
        );

        $this->createVariants($hexKeyWrench, [
            ['sku' => 'WRN-HEX-4MM', 'stock' => 220, 'values' => [
                'wrench_size' => '4mm', 'wrench_material' => 'Alloy Steel', 'wrench_finish' => 'Black Oxide',
            ], 'tiers' => [
                [1, '1.49'], [10, '1.20'], [30, '0.95'],
            ]],
            ['sku' => 'WRN-HEX-6MM', 'stock' => 190, 'values' => [
                'wrench_size' => '6mm', 'wrench_material' => 'Alloy Steel', 'wrench_finish' => 'Black Oxide',
            ], 'tiers' => [
                [1, '1.79'], [10, '1.45'], [30, '1.15'],
            ]],
        ]);

        $strapWrench = $this->createProduct(
            $wrenches,
            'Strap Wrench',
            'strap-wrench',
            'A rubber-strap wrench that grips smooth or delicate round surfaces without marring.',
            '27374d'
        );

        $this->createVariants($strapWrench, [
            ['sku' => 'WRN-STRAP-4IN', 'stock' => 60, 'values' => [
                'wrench_size' => '4 inch', 'wrench_material' => 'Rubber & Steel', 'wrench_finish' => 'Matte Black',
            ], 'tiers' => [
                [1, '12.99'], [10, '11.20'], [30, '9.50'],
            ]],
        ]);

        // --- Additional Screwdrivers ---

        $torxScrewdriver = $this->createProduct(
            $screwdrivers,
            'Torx Screwdriver',
            'torx-screwdriver',
            'A star-tip screwdriver for Torx security and machine fasteners.',
            '581c87'
        );

        $this->createVariants($torxScrewdriver, [
            ['sku' => 'SDR-TX-T15', 'stock' => 190, 'values' => [
                'tip_type' => 'Torx', 'tip_size' => 'T15', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '4.99'], [20, '4.20'], [100, '3.50'],
            ]],
            ['sku' => 'SDR-TX-T20', 'stock' => 160, 'values' => [
                'tip_type' => 'Torx', 'tip_size' => 'T20', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '5.29'], [20, '4.45'], [100, '3.70'],
            ]],
        ]);

        $precisionScrewdriver = $this->createProduct(
            $screwdrivers,
            'Precision Screwdriver',
            'precision-screwdriver',
            'A fine-tip screwdriver for electronics and delicate assembly work.',
            '6d28d9'
        );

        $this->createVariants($precisionScrewdriver, [
            ['sku' => 'SDR-PRC-PH00', 'stock' => 230, 'values' => [
                'tip_type' => 'Phillips', 'tip_size' => '#00', 'handle_material' => 'Aluminum',
            ], 'tiers' => [
                [1, '3.49'], [20, '2.90'], [100, '2.40'],
            ]],
            ['sku' => 'SDR-PRC-SL2', 'stock' => 200, 'values' => [
                'tip_type' => 'Flathead', 'tip_size' => '2mm', 'handle_material' => 'Aluminum',
            ], 'tiers' => [
                [1, '3.29'], [20, '2.75'], [100, '2.25'],
            ]],
        ]);

        $offsetScrewdriver = $this->createProduct(
            $screwdrivers,
            'Offset Screwdriver',
            'offset-screwdriver',
            'A Z-shaped screwdriver that reaches fasteners in tight, obstructed spaces.',
            '7e22ce'
        );

        $this->createVariants($offsetScrewdriver, [
            ['sku' => 'SDR-OFS-PH2', 'stock' => 90, 'values' => [
                'tip_type' => 'Phillips', 'tip_size' => '#2', 'handle_material' => 'Steel',
            ], 'tiers' => [
                [1, '6.49'], [20, '5.50'], [100, '4.60'],
            ]],
        ]);

        $ratchetingScrewdriver = $this->createProduct(
            $screwdrivers,
            'Ratcheting Screwdriver',
            'ratcheting-screwdriver',
            'A multi-bit screwdriver with a ratcheting handle for faster driving.',
            '86198f'
        );

        $this->createVariants($ratchetingScrewdriver, [
            ['sku' => 'SDR-RTC-SET', 'stock' => 70, 'values' => [
                'tip_type' => 'Multi-Bit', 'tip_size' => 'Assorted', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '11.99'], [20, '10.20'], [100, '8.50'],
            ]],
        ]);

        $stubbyScrewdriver = $this->createProduct(
            $screwdrivers,
            'Stubby Screwdriver',
            'stubby-screwdriver',
            'A short-shaft screwdriver for driving fasteners in cramped, low-clearance spots.',
            '701a75'
        );

        $this->createVariants($stubbyScrewdriver, [
            ['sku' => 'SDR-STB-PH2', 'stock' => 140, 'values' => [
                'tip_type' => 'Phillips', 'tip_size' => '#2', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '3.99'], [20, '3.30'], [100, '2.75'],
            ]],
            ['sku' => 'SDR-STB-FH-1-4', 'stock' => 120, 'values' => [
                'tip_type' => 'Flathead', 'tip_size' => '1/4 inch', 'handle_material' => 'Cushion Grip',
            ], 'tiers' => [
                [1, '3.79'], [20, '3.15'], [100, '2.60'],
            ]],
        ]);

        // --- Additional Drill Bits ---

        $spadeBit = $this->createProduct(
            $drillBits,
            'Spade Bit',
            'spade-bit',
            'A flat, paddle-shaped bit for fast, rough boring in wood.',
            '0f172a'
        );

        $this->createVariants($spadeBit, [
            ['sku' => 'BIT-SPD-HSS-19', 'stock' => 160, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '19', 'bit_length' => '152',
            ], 'tiers' => [
                [1, '2.20'], [50, '1.85'], [200, '1.50'],
            ]],
            ['sku' => 'BIT-SPD-HSS-25', 'stock' => 120, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '25', 'bit_length' => '152',
            ], 'tiers' => [
                [1, '2.60'], [50, '2.20'], [200, '1.80'],
            ]],
        ]);

        $stepDrillBit = $this->createProduct(
            $drillBits,
            'Step Drill Bit',
            'step-drill-bit',
            'A cone-shaped bit that drills multiple hole sizes in thin sheet metal.',
            '172554'
        );

        $this->createVariants($stepDrillBit, [
            ['sku' => 'BIT-STEP-HSS-4-12', 'stock' => 95, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '12', 'bit_length' => '65',
            ], 'tiers' => [
                [1, '8.90'], [50, '7.60'], [200, '6.20'],
            ]],
            ['sku' => 'BIT-STEP-HSS-4-20', 'stock' => 60, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '20', 'bit_length' => '75',
            ], 'tiers' => [
                [1, '11.90'], [50, '10.10'], [200, '8.30'],
            ]],
        ]);

        $forstnerBit = $this->createProduct(
            $drillBits,
            'Forstner Bit',
            'forstner-bit',
            'A flat-bottomed bit for clean, precise flat-bottomed holes in wood.',
            '0c4a6e'
        );

        $this->createVariants($forstnerBit, [
            ['sku' => 'BIT-FRST-HSS-25', 'stock' => 70, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '25', 'bit_length' => '90',
            ], 'tiers' => [
                [1, '6.90'], [50, '5.90'], [200, '4.80'],
            ]],
            ['sku' => 'BIT-FRST-HSS-35', 'stock' => 45, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '35', 'bit_length' => '90',
            ], 'tiers' => [
                [1, '9.90'], [50, '8.40'], [200, '6.90'],
            ]],
        ]);

        $cobaltDrillBit = $this->createProduct(
            $drillBits,
            'Cobalt Drill Bit',
            'cobalt-drill-bit',
            'A heat-resistant cobalt-alloy bit for drilling hardened steel and stainless.',
            '164e63'
        );

        $this->createVariants($cobaltDrillBit, [
            ['sku' => 'BIT-COB-5', 'stock' => 180, 'values' => [
                'bit_material' => 'Cobalt', 'bit_diameter' => '5', 'bit_length' => '86',
            ], 'tiers' => [
                [1, '2.90'], [50, '2.45'], [200, '2.00'],
            ]],
            ['sku' => 'BIT-COB-8', 'stock' => 130, 'values' => [
                'bit_material' => 'Cobalt', 'bit_diameter' => '8', 'bit_length' => '117',
            ], 'tiers' => [
                [1, '4.20'], [50, '3.55'], [200, '2.90'],
            ]],
        ]);

        $augerBit = $this->createProduct(
            $drillBits,
            'Auger Bit',
            'auger-bit',
            'A long, deep-fluted bit for boring clean holes through thick timber.',
            '155e75'
        );

        $this->createVariants($augerBit, [
            ['sku' => 'BIT-AUG-HSS-10', 'stock' => 60, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '10', 'bit_length' => '230',
            ], 'tiers' => [
                [1, '5.90'], [50, '5.00'], [200, '4.10'],
            ]],
            ['sku' => 'BIT-AUG-HSS-16', 'stock' => 40, 'values' => [
                'bit_material' => 'HSS', 'bit_diameter' => '16', 'bit_length' => '300',
            ], 'tiers' => [
                [1, '8.90'], [50, '7.60'], [200, '6.20'],
            ]],
        ]);

        // --- Additional Anchors ---

        $toggleBoltAnchor = $this->createProduct(
            $anchors,
            'Toggle Bolt Anchor',
            'toggle-bolt-anchor',
            'A spring-wing anchor that opens behind hollow walls for heavy hanging loads.',
            '4338ca'
        );

        $this->createVariants($toggleBoltAnchor, [
            ['sku' => 'ANC-TOG-ZN-6', 'stock' => 500, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '6', 'max_load' => '30',
            ], 'tiers' => [
                [1, '0.30'], [200, '0.25'], [1000, '0.20'],
            ]],
            ['sku' => 'ANC-TOG-ZN-8', 'stock' => 380, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '8', 'max_load' => '45',
            ], 'tiers' => [
                [1, '0.40'], [200, '0.33'], [1000, '0.26'],
            ]],
        ]);

        $sleeveAnchor = $this->createProduct(
            $anchors,
            'Sleeve Anchor',
            'sleeve-anchor',
            'An expanding-sleeve anchor for medium to heavy loads in solid concrete or block.',
            '4f46e5'
        );

        $this->createVariants($sleeveAnchor, [
            ['sku' => 'ANC-SLV-ZN-10', 'stock' => 260, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '10', 'max_load' => '100',
            ], 'tiers' => [
                [1, '0.70'], [200, '0.58'], [1000, '0.46'],
            ]],
            ['sku' => 'ANC-SLV-ZN-12', 'stock' => 180, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '12', 'max_load' => '140',
            ], 'tiers' => [
                [1, '0.95'], [200, '0.80'], [1000, '0.64'],
            ]],
        ]);

        $chemicalAnchor = $this->createProduct(
            $anchors,
            'Chemical Anchor',
            'chemical-anchor',
            'A resin-capsule anchoring system for high-load fastening into masonry and stone.',
            '5b21b6'
        );

        $this->createVariants($chemicalAnchor, [
            ['sku' => 'ANC-CHM-10', 'stock' => 5, 'values' => [
                'anchor_material' => 'Resin/Steel', 'anchor_diameter' => '10', 'max_load' => '250',
            ], 'tiers' => [
                [1, '3.90'], [200, '3.30'], [1000, '2.70'],
            ]],
        ]);

        $mollyBolt = $this->createProduct(
            $anchors,
            'Molly Bolt',
            'molly-bolt',
            'A metal expansion anchor for hanging moderate loads on drywall or plaster.',
            '3949ab'
        );

        $this->createVariants($mollyBolt, [
            ['sku' => 'ANC-MOL-ZN-6', 'stock' => 420, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '6', 'max_load' => '20',
            ], 'tiers' => [
                [1, '0.15'], [200, '0.12'], [1000, '0.09'],
            ]],
            ['sku' => 'ANC-MOL-ZN-8', 'stock' => 310, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '8', 'max_load' => '35',
            ], 'tiers' => [
                [1, '0.20'], [200, '0.16'], [1000, '0.13'],
            ]],
        ]);

        $selfDrillingAnchor = $this->createProduct(
            $anchors,
            'Self-Drilling Anchor',
            'self-drilling-anchor',
            'A hardened anchor that drills and taps its own hole into masonry in one step.',
            '312f6e'
        );

        $this->createVariants($selfDrillingAnchor, [
            ['sku' => 'ANC-SDR-ZN-8', 'stock' => 150, 'values' => [
                'anchor_material' => 'Zinc-Plated Steel', 'anchor_diameter' => '8', 'max_load' => '55',
            ], 'tiers' => [
                [1, '0.55'], [200, '0.46'], [1000, '0.37'],
            ]],
        ]);

        // --- Additional Pliers ---

        $diagonalCuttingPliers = $this->createProduct(
            $pliers,
            'Diagonal Cutting Pliers',
            'diagonal-cutting-pliers',
            'Angled-jaw pliers with hardened cutting edges for wire and pin removal.',
            '134e4a'
        );

        $this->createVariants($diagonalCuttingPliers, [
            ['sku' => 'PLR-DIAG-15', 'stock' => 110, 'values' => [
                'jaw_length' => '15', 'plier_material' => 'Chrome Vanadium Steel',
            ], 'tiers' => [
                [1, '7.99'], [10, '6.90'], [50, '5.80'],
            ]],
            ['sku' => 'PLR-DIAG-18', 'stock' => 80, 'values' => [
                'jaw_length' => '18', 'plier_material' => 'Chrome Vanadium Steel',
            ], 'tiers' => [
                [1, '9.49'], [10, '8.10'], [50, '6.80'],
            ]],
        ]);

        $combinationPliers = $this->createProduct(
            $pliers,
            'Combination Pliers',
            'combination-pliers',
            'A versatile pliers combining gripping, bending, and cutting jaws in one tool.',
            '115e59'
        );

        $this->createVariants($combinationPliers, [
            ['sku' => 'PLR-COMB-18', 'stock' => 95, 'values' => [
                'jaw_length' => '18', 'plier_material' => 'Drop-Forged Steel',
            ], 'tiers' => [
                [1, '8.49'], [10, '7.30'], [50, '6.10'],
            ]],
        ]);

        $waterPumpPliers = $this->createProduct(
            $pliers,
            'Water Pump Pliers',
            'water-pump-pliers',
            'Multi-position pliers with elongated jaws for gripping pipes and large fittings.',
            '0f766e'
        );

        $this->createVariants($waterPumpPliers, [
            ['sku' => 'PLR-PUMP-25', 'stock' => 70, 'values' => [
                'jaw_length' => '25', 'plier_material' => 'Drop-Forged Steel',
            ], 'tiers' => [
                [1, '10.99'], [10, '9.50'], [50, '8.00'],
            ]],
            ['sku' => 'PLR-PUMP-32', 'stock' => 50, 'values' => [
                'jaw_length' => '32', 'plier_material' => 'Drop-Forged Steel',
            ], 'tiers' => [
                [1, '13.99'], [10, '12.10'], [50, '10.20'],
            ]],
        ]);

        $snapRingPliers = $this->createProduct(
            $pliers,
            'Snap Ring Pliers',
            'snap-ring-pliers',
            'Specialized pliers with fine tips for installing and removing retaining rings.',
            '0e5959'
        );

        $this->createVariants($snapRingPliers, [
            ['sku' => 'PLR-SNAP-15', 'stock' => 60, 'values' => [
                'jaw_length' => '15', 'plier_material' => 'Chrome Vanadium Steel',
            ], 'tiers' => [
                [1, '9.99'], [10, '8.60'], [50, '7.30'],
            ]],
        ]);

        // --- Additional Measuring Tools ---

        $digitalCaliper = $this->createProduct(
            $measuring,
            'Digital Caliper',
            'digital-caliper',
            'An electronic caliper for precise inside, outside, and depth measurements.',
            '9f1239'
        );

        $this->createVariants($digitalCaliper, [
            ['sku' => 'MSR-CAL-150MM', 'stock' => 100, 'values' => [
                'tape_length' => '150mm', 'blade_width' => '15',
            ], 'tiers' => [
                [1, '15.99'], [20, '13.60'], [100, '11.20'],
            ]],
            ['sku' => 'MSR-CAL-200MM', 'stock' => 70, 'values' => [
                'tape_length' => '200mm', 'blade_width' => '18',
            ], 'tiers' => [
                [1, '21.99'], [20, '18.70'], [100, '15.40'],
            ]],
        ]);

        $spiritLevel = $this->createProduct(
            $measuring,
            'Spirit Level',
            'spirit-level',
            'A bubble-vial level for checking horizontal and vertical alignment.',
            '9d174d'
        );

        $this->createVariants($spiritLevel, [
            ['sku' => 'MSR-LVL-60CM', 'stock' => 85, 'values' => [
                'tape_length' => '60cm', 'blade_width' => '40',
            ], 'tiers' => [
                [1, '12.99'], [20, '10.90'], [100, '8.90'],
            ]],
            ['sku' => 'MSR-LVL-100CM', 'stock' => 55, 'values' => [
                'tape_length' => '100cm', 'blade_width' => '40',
            ], 'tiers' => [
                [1, '17.99'], [20, '15.20'], [100, '12.50'],
            ]],
        ]);

        $combinationSquare = $this->createProduct(
            $measuring,
            'Combination Square',
            'combination-square',
            'An adjustable square with a sliding rule for marking right angles and 45-degree miters.',
            '831843'
        );

        $this->createVariants($combinationSquare, [
            ['sku' => 'MSR-SQR-30CM', 'stock' => 90, 'values' => [
                'tape_length' => '30cm', 'blade_width' => '20',
            ], 'tiers' => [
                [1, '9.99'], [20, '8.50'], [100, '7.00'],
            ]],
        ]);

        $digitalAngleFinder = $this->createProduct(
            $measuring,
            'Digital Angle Finder',
            'digital-angle-finder',
            'An electronic protractor that reads precise angles for framing and layout.',
            '6b21a8'
        );

        $this->createVariants($digitalAngleFinder, [
            ['sku' => 'MSR-ANG-7IN', 'stock' => 45, 'values' => [
                'tape_length' => '7 inch', 'blade_width' => '30',
            ], 'tiers' => [
                [1, '13.99'], [20, '11.90'], [100, '9.80'],
            ]],
        ]);

        $chalkLineReel = $this->createProduct(
            $measuring,
            'Chalk Line Reel',
            'chalk-line-reel',
            'A retractable chalk-coated line for snapping long, straight layout marks.',
            '4c0519'
        );

        $this->createVariants($chalkLineReel, [
            ['sku' => 'MSR-CHK-30M', 'stock' => 120, 'values' => [
                'tape_length' => '30m', 'blade_width' => '2',
            ], 'tiers' => [
                [1, '6.99'], [20, '5.90'], [100, '4.80'],
            ]],
        ]);

        $laserDistanceMeasurer = $this->createProduct(
            $measuring,
            'Laser Distance Measurer',
            'laser-distance-measurer',
            'A handheld laser tool for fast, single-person distance and area measurement.',
            '500724'
        );

        $this->createVariants($laserDistanceMeasurer, [
            ['sku' => 'MSR-LSR-40M', 'stock' => 40, 'values' => [
                'tape_length' => '40m', 'blade_width' => '30',
            ], 'tiers' => [
                [1, '29.99'], [20, '25.90'], [100, '21.50'],
            ]],
            ['sku' => 'MSR-LSR-60M', 'stock' => 7, 'values' => [
                'tape_length' => '60m', 'blade_width' => '30',
            ], 'tiers' => [
                [1, '39.99'], [20, '34.50'], [100, '28.90'],
            ]],
        ]);

        // --- Additional Safety Gear ---

        $safetyGlasses = $this->createProduct(
            $safety,
            'Safety Glasses',
            'safety-glasses',
            'Impact-resistant eyewear that protects against dust, debris, and splashes.',
            '164e3b'
        );

        $this->createVariants($safetyGlasses, [
            ['sku' => 'SFT-GLS-CLR', 'stock' => 400, 'values' => [
                'glove_size' => 'One Size', 'glove_material' => 'Polycarbonate',
            ], 'tiers' => [
                [1, '3.99'], [50, '3.30'], [200, '2.70'],
            ]],
            ['sku' => 'SFT-GLS-TNT', 'stock' => 320, 'values' => [
                'glove_size' => 'One Size', 'glove_material' => 'Polycarbonate (Tinted)',
            ], 'tiers' => [
                [1, '4.49'], [50, '3.70'], [200, '3.00'],
            ]],
        ]);

        $hardHat = $this->createProduct(
            $safety,
            'Hard Hat',
            'hard-hat',
            'A rigid protective helmet for job sites with overhead hazards.',
            '854d0e'
        );

        $this->createVariants($hardHat, [
            ['sku' => 'SFT-HAT-M', 'stock' => 140, 'values' => [
                'glove_size' => 'M/L', 'glove_material' => 'ABS Plastic',
            ], 'tiers' => [
                [1, '9.99'], [50, '8.50'], [200, '6.90'],
            ]],
            ['sku' => 'SFT-HAT-XL', 'stock' => 90, 'values' => [
                'glove_size' => 'XL', 'glove_material' => 'ABS Plastic',
            ], 'tiers' => [
                [1, '10.99'], [50, '9.30'], [200, '7.60'],
            ]],
        ]);

        $earProtectionMuffs = $this->createProduct(
            $safety,
            'Ear Protection Muffs',
            'ear-protection-muffs',
            'Padded over-ear muffs that reduce noise exposure from loud power tools.',
            '713f12'
        );

        $this->createVariants($earProtectionMuffs, [
            ['sku' => 'SFT-EAR-STD', 'stock' => 180, 'values' => [
                'glove_size' => 'One Size', 'glove_material' => 'Foam & ABS',
            ], 'tiers' => [
                [1, '7.99'], [50, '6.80'], [200, '5.60'],
            ]],
        ]);

        $dustMaskRespirator = $this->createProduct(
            $safety,
            'Dust Mask Respirator',
            'dust-mask-respirator',
            'A disposable respirator mask that filters airborne dust and particulates.',
            '422006'
        );

        $this->createVariants($dustMaskRespirator, [
            ['sku' => 'SFT-MSK-N95', 'stock' => 600, 'values' => [
                'glove_size' => 'One Size', 'glove_material' => 'N95 Filter Media',
            ], 'tiers' => [
                [1, '1.49'], [50, '1.20'], [200, '0.95'],
            ]],
        ]);

        $kneePads = $this->createProduct(
            $safety,
            'Knee Pads',
            'knee-pads',
            'Cushioned, strap-on pads that protect knees during flooring and low-clearance work.',
            '365314'
        );

        $this->createVariants($kneePads, [
            ['sku' => 'SFT-KNEE-STD', 'stock' => 130, 'values' => [
                'glove_size' => 'One Size', 'glove_material' => 'Foam & Neoprene',
            ], 'tiers' => [
                [1, '11.99'], [50, '10.20'], [200, '8.40'],
            ]],
        ]);

        $safetyVest = $this->createProduct(
            $safety,
            'Safety Vest',
            'safety-vest',
            'A high-visibility reflective vest for on-site visibility and job-site compliance.',
            '4d7c0f'
        );

        $this->createVariants($safetyVest, [
            ['sku' => 'SFT-VST-M', 'stock' => 200, 'values' => [
                'glove_size' => 'M', 'glove_material' => 'Polyester Mesh',
            ], 'tiers' => [
                [1, '5.99'], [50, '5.10'], [200, '4.20'],
            ]],
            ['sku' => 'SFT-VST-XL', 'stock' => 150, 'values' => [
                'glove_size' => 'XL', 'glove_material' => 'Polyester Mesh',
            ], 'tiers' => [
                [1, '6.49'], [50, '5.50'], [200, '4.50'],
            ]],
        ]);

        // --- Additional Adhesives & Sealants ---

        $woodGlue = $this->createProduct(
            $adhesives,
            'Wood Glue',
            'wood-glue',
            'A strong PVA adhesive for interior wood-to-wood bonding.',
            '3e2f92'
        );

        $this->createVariants($woodGlue, [
            ['sku' => 'ADH-WD-250ML', 'stock' => 260, 'values' => [
                'volume' => '250ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '4.49'], [24, '3.80'], [96, '3.20'],
            ]],
            ['sku' => 'ADH-WD-500ML', 'stock' => 170, 'values' => [
                'volume' => '500ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '7.49'], [24, '6.30'], [96, '5.30'],
            ]],
        ]);

        $superGlue = $this->createProduct(
            $adhesives,
            'Super Glue',
            'super-glue',
            'A fast-setting cyanoacrylate adhesive for instant bonds on most materials.',
            '4c1d5f'
        );

        $this->createVariants($superGlue, [
            ['sku' => 'ADH-SG-3G', 'stock' => 500, 'values' => [
                'volume' => '3g', 'cure_time' => '10 seconds',
            ], 'tiers' => [
                [1, '2.99'], [24, '2.50'], [96, '2.10'],
            ]],
            ['sku' => 'ADH-SG-20G', 'stock' => 280, 'values' => [
                'volume' => '20g', 'cure_time' => '10 seconds',
            ], 'tiers' => [
                [1, '6.99'], [24, '5.90'], [96, '4.90'],
            ]],
        ]);

        $constructionAdhesive = $this->createProduct(
            $adhesives,
            'Construction Adhesive',
            'construction-adhesive',
            'A heavy-duty, gap-filling adhesive for panels, trim, and structural bonding.',
            '3a2e6e'
        );

        $this->createVariants($constructionAdhesive, [
            ['sku' => 'ADH-CON-290ML', 'stock' => 150, 'values' => [
                'volume' => '290ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '6.49'], [24, '5.50'], [96, '4.60'],
            ]],
        ]);

        $polyurethaneSealant = $this->createProduct(
            $adhesives,
            'Polyurethane Sealant',
            'polyurethane-sealant',
            'A flexible, paintable sealant for expansion joints exposed to movement and weather.',
            '1e2a4a'
        );

        $this->createVariants($polyurethaneSealant, [
            ['sku' => 'ADH-PU-300ML', 'stock' => 130, 'values' => [
                'volume' => '300ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '7.99'], [24, '6.80'], [96, '5.60'],
            ]],
        ]);

        $threadlocker = $this->createProduct(
            $adhesives,
            'Threadlocker',
            'threadlocker',
            'A liquid adhesive applied to bolt threads to resist loosening from vibration.',
            '22304a'
        );

        $this->createVariants($threadlocker, [
            ['sku' => 'ADH-TL-10ML', 'stock' => 220, 'values' => [
                'volume' => '10ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '4.99'], [24, '4.20'], [96, '3.50'],
            ]],
            ['sku' => 'ADH-TL-50ML', 'stock' => 11, 'values' => [
                'volume' => '50ml', 'cure_time' => '24 hours',
            ], 'tiers' => [
                [1, '12.99'], [24, '11.00'], [96, '9.10'],
            ]],
        ]);

        // Hand-written synonyms: common abbreviations/unit variants that
        // should find the exact same variants as their canonical value.
        $this->createSynonyms($material, [
            'ss' => 'Stainless Steel',
            'stainless' => 'Stainless Steel',
            'zinc' => 'Zinc-Plated Steel',
            'zn' => 'Zinc-Plated Steel',
        ]);

        $this->createSynonyms($length, [
            '20mm' => '20',
            '20 mm' => '20',
            '30mm' => '30',
            '30 mm' => '30',
            '40mm' => '40',
            '40 mm' => '40',
        ]);

        $this->createSynonyms($sealType, [
            'sealed' => 'Rubber Sealed',
        ]);

        $this->createSynonyms($handleMaterial, [
            'wooden' => 'Wood',
            'fiberglass handle' => 'Fiberglass',
        ]);

        $this->createSynonyms($driveType, [
            'phillips head' => 'Phillips',
            'torx head' => 'Torx',
        ]);

        $this->createSynonyms($bitMaterial, [
            'hss bit' => 'HSS',
            'carbide' => 'Carbide-Tipped',
        ]);

        $this->createSynonyms($anchorMaterial, [
            'plastic anchor' => 'Plastic',
        ]);

        $this->createSynonyms($gloveSize, [
            'medium' => 'M',
            'large' => 'L',
            'extra large' => 'XL',
        ]);
    }

    private function createProduct(
        Category $category,
        string $name,
        string $slug,
        string $description,
        string $backgroundColor
    ): Product {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image_url' => $this->fetchAndStorePlaceholderImage($slug, $name, $backgroundColor),
        ]);

        $this->createGallery($product, $name, $backgroundColor);

        return $product;
    }

    /**
     * A small gallery beyond the primary card image - a detail shot and a
     * packaging shot, both fetched from the same placehold.co pipeline with
     * distinct labels/dimensions so they read as different angles rather
     * than duplicates of the card image.
     */
    private function createGallery(Product $product, string $name, string $backgroundColor): void
    {
        if ($product->image_url !== null) {
            ProductImage::create([
                'product_id' => $product->id,
                'url' => $product->image_url,
                'alt' => $name,
                'sort_order' => 0,
            ]);
        }

        $shots = [
            ['label' => 'Detail View', 'width' => 600, 'height' => 600],
            ['label' => 'Packaging', 'width' => 600, 'height' => 400],
        ];

        foreach ($shots as $index => $shot) {
            $url = $this->fetchAndStorePlaceholderImage(
                "{$product->slug}-{$index}",
                "{$name} - {$shot['label']}",
                $backgroundColor,
                width: $shot['width'],
                height: $shot['height'],
            );

            if ($url !== null) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $url,
                    'alt' => "{$name} - {$shot['label']}",
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    /**
     * Generates a placehold.co image for this product (a unique background
     * color and the product's own name as the label), downloads it once at
     * seed time, and stores it on the local public disk - the running app
     * never calls out to placehold.co itself, only this seeder does.
     */
    private function fetchAndStorePlaceholderImage(
        string $slug,
        string $text,
        string $backgroundColor,
        string $textColor = 'FFFFFF',
        int $width = 600,
        int $height = 400
    ): ?string {
        // placehold.co returns SVG by default unless a format extension is
        // given - without ".png" here, the response body is actually SVG
        // markup even though we ask it a PNG question, which silently
        // breaks rendering once served back with an image/png content type.
        $url = sprintf(
            'https://placehold.co/%dx%d/%s/%s.png?text=%s',
            $width,
            $height,
            $backgroundColor,
            $textColor,
            urlencode($text)
        );

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $path = "products/{$slug}.png";
            Storage::disk('public')->put($path, $response->body());

            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            // A seed running without network access shouldn't hard-fail the
            // whole catalog - products just end up without an image.
            return null;
        }
    }

    /**
     * @param  array<int, array{0: Attribute, 1: bool}>  $attributes
     */
    private function attachAttributes(Category $category, array $attributes): void
    {
        foreach ($attributes as [$attribute, $isVariantDefining]) {
            CategoryAttribute::create([
                'category_id' => $category->id,
                'attribute_id' => $attribute->id,
                'is_variant_defining' => $isVariantDefining,
                'is_required' => true,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $termsToValues
     */
    private function createSynonyms(Attribute $attribute, array $termsToValues): void
    {
        foreach ($termsToValues as $term => $value) {
            Synonym::create([
                'attribute_id' => $attribute->id,
                'term' => $term,
                'value' => $value,
            ]);
        }
    }

    /**
     * @param  array<int, array{sku: string, stock: int, values: array<string, string>, tiers: array<int, array{0: int, 1: string}>}>  $variants
     */
    private function createVariants(Product $product, array $variants): void
    {
        $attributesByKey = Attribute::whereIn(
            'key',
            collect($variants)->flatMap(fn ($v) => array_keys($v['values']))->unique()
        )->get()->keyBy('key');

        foreach ($variants as $variantData) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'stock' => $variantData['stock'],
            ]);

            foreach ($variantData['values'] as $key => $value) {
                VariantAttributeValue::create([
                    'product_variant_id' => $variant->id,
                    'attribute_id' => $attributesByKey[$key]->id,
                    'value' => $value,
                ]);
            }

            foreach ($variantData['tiers'] as [$minQuantity, $unitPrice]) {
                PriceTier::create([
                    'product_variant_id' => $variant->id,
                    'min_quantity' => $minQuantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }
    }
}
