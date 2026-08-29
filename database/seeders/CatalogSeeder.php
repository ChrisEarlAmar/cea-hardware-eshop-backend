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
