<?php

/**
 * Sample finished goods using client-pvt ingredients.
 *
 * gm products: recipe qty_per_kg is grams of ingredient per 1 kg finished product
 *   (stored as quantity_required per 1 gm = qty_per_kg / 1000).
 * pcs products: recipe qty is per piece.
 * bought products: no recipe.
 */
return [
    [
        'number' => '9101',
        'name' => 'Besan Laddu',
        'category' => 'sweet',
        'price' => 400,
        'unit' => 'gm',
        'shelf_life' => 168,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Besan / Gram Flour', 'qty_per_kg' => 500],
            ['ingredient' => 'Ghee', 'qty_per_kg' => 150],
            ['ingredient' => 'White Crystal Sugar', 'qty_per_kg' => 300],
        ],
    ],
    [
        'number' => '9102',
        'name' => 'Garam Masala Mix',
        'category' => 'spices',
        'price' => 350,
        'unit' => 'gm',
        'shelf_life' => 2160,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Cumin', 'qty_per_kg' => 250],
            ['ingredient' => 'Coriander', 'qty_per_kg' => 300],
            ['ingredient' => 'Black Pepper', 'qty_per_kg' => 150],
            ['ingredient' => 'Fennel', 'qty_per_kg' => 150],
            ['ingredient' => 'Dry Red Chilli', 'qty_per_kg' => 150],
        ],
    ],
    [
        'number' => '9103',
        'name' => 'White Bread',
        'category' => 'bread',
        'price' => 40,
        'unit' => 'pcs',
        'shelf_life' => 48,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Maida Flour', 'qty' => 45],
            ['ingredient' => 'Angel Yeast', 'qty' => 2],
            ['ingredient' => 'White Salt', 'qty' => 1],
            ['ingredient' => 'White Crystal Sugar', 'qty' => 3],
            ['ingredient' => 'Gold Winner Oil', 'qty' => 5],
        ],
    ],
    [
        'number' => '9104',
        'name' => 'Vanilla Cake',
        'category' => 'bakery',
        'price' => 550,
        'unit' => 'gm',
        'shelf_life' => 48,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Maida Flour', 'qty_per_kg' => 450],
            ['ingredient' => 'White Crystal Sugar', 'qty_per_kg' => 250],
            ['ingredient' => 'Ghee', 'qty_per_kg' => 150],
            ['ingredient' => 'Milk Powder', 'qty_per_kg' => 80],
            ['ingredient' => 'Vanilla Essence', 'qty_per_kg' => 5],
        ],
    ],
    [
        'number' => '9105',
        'name' => 'Chocolate Cake',
        'category' => 'bakery',
        'price' => 650,
        'unit' => 'gm',
        'shelf_life' => 48,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Maida Flour', 'qty_per_kg' => 400],
            ['ingredient' => 'White Crystal Sugar', 'qty_per_kg' => 250],
            ['ingredient' => 'Ghee', 'qty_per_kg' => 140],
            ['ingredient' => 'Milk Powder', 'qty_per_kg' => 80],
            ['ingredient' => 'Cocoa Powder', 'qty_per_kg' => 40],
            ['ingredient' => 'Vanilla Essence', 'qty_per_kg' => 5],
        ],
    ],
    [
        'number' => '9106',
        'name' => 'Salt Biscuit',
        'category' => 'biscuit',
        'price' => 10,
        'unit' => 'pcs',
        'shelf_life' => 336,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Maida Flour', 'qty' => 30],
            ['ingredient' => 'White Salt', 'qty' => 1],
            ['ingredient' => 'Ghee', 'qty' => 8],
            ['ingredient' => 'White Crystal Sugar', 'qty' => 5],
        ],
    ],
    [
        'number' => '9107',
        'name' => 'Coconut Biscuit',
        'category' => 'biscuit',
        'price' => 12,
        'unit' => 'pcs',
        'shelf_life' => 336,
        'source' => 'own',
        'recipe' => [
            ['ingredient' => 'Maida Flour', 'qty' => 28],
            ['ingredient' => 'White Crystal Sugar', 'qty' => 12],
            ['ingredient' => 'Ghee', 'qty' => 10],
            ['ingredient' => 'Coconut Powder', 'qty' => 8],
            ['ingredient' => 'Vanilla Essence', 'qty' => 1],
        ],
    ],
    [
        'number' => '9108',
        'name' => 'Mixed Jam',
        'category' => 'other',
        'price' => 80,
        'unit' => 'gm',
        'shelf_life' => null,
        'source' => 'bought',
        'recipe' => [],
    ],
];
