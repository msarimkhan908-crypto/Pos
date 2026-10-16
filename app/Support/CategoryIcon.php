<?php

namespace App\Support;

/**
 * Returns a food/product emoji that best matches a category or product name.
 * Used as a placeholder when no image is uploaded.
 */
class CategoryIcon
{
    private const MAP = [
        // Burgers & sandwiches
        'burger' => '🍔',
        'hamburger' => '🍔',
        'cheeseburger' => '🍔',
        'sandwich' => '🥪',
        'sub' => '🥪',
        'wrap' => '🌯',
        'roll' => '🌯',

        // Pizza
        'pizza' => '🍕',

        // Chicken
        'chicken' => '🍗',
        'wings' => '🍗',
        'nugget' => '🍗',
        'fried' => '🍗',

        // Fries & sides
        'fries' => '🍟',
        'chips' => '🍟',

        // Rice & grains
        'rice' => '🍚',
        'biryani' => '🍚',
        'pulao' => '🍚',
        'grain' => '🌾',

        // Noodles & pasta
        'pasta' => '🍝',
        'noodle' => '🍜',
        'spaghetti' => '🍝',
        'ramen' => '🍜',

        // Soup & stew
        'soup' => '🍲',
        'stew' => '🍲',
        'curry' => '🍛',
        'karahi' => '🍛',

        // Seafood & fish
        'fish' => '🐟',
        'seafood' => '🦐',
        'shrimp' => '🦐',
        'prawn' => '🦐',

        // Salads & vegetables
        'salad' => '🥗',
        'veg' => '🥦',
        'vegetable' => '🥦',

        // Bread & bakery
        'bread' => '🍞',
        'bakery' => '🥐',
        'bake' => '🥐',
        'croissant' => '🥐',
        'waffle' => '🧇',
        'pancake' => '🥞',
        'roti' => '🫓',
        'naan' => '🫓',
        'paratha' => '🫓',

        // Desserts & sweets
        'dessert' => '🍦',
        'ice cream' => '🍦',
        'icecream' => '🍦',
        'sweet' => '🍬',
        'candy' => '🍬',
        'cake' => '🎂',
        'cupcake' => '🧁',
        'cookie' => '🍪',
        'chocolate' => '🍫',
        'donut' => '🍩',
        'doughnut' => '🍩',
        'pastry' => '🥐',
        'kulfi' => '🍦',
        'halwa' => '🍮',

        // Drinks
        'drink' => '🥤',
        'beverage' => '🥤',
        'juice' => '🧃',
        'water' => '💧',
        'cola' => '🥤',
        'soda' => '🥤',
        'mocktail' => '🍹',
        'cocktail' => '🍹',
        'shake' => '🥛',
        'smoothie' => '🧃',
        'lassi' => '🥛',
        'milk' => '🥛',

        // Coffee & tea
        'coffee' => '☕',
        'tea' => '🍵',
        'chai' => '🍵',
        'espresso' => '☕',
        'latte' => '☕',
        'cappuccino' => '☕',

        // Meat & BBQ
        'meat' => '🥩',
        'beef' => '🥩',
        'steak' => '🥩',
        'bbq' => '🍖',
        'grill' => '🍖',
        'kebab' => '🍖',
        'seekh' => '🍖',
        'tikka' => '🍖',
        'mutton' => '🥩',
        'lamb' => '🥩',

        // Snacks
        'snack' => '🍿',
        'popcorn' => '🍿',
        'nachos' => '🧆',
        'samosa' => '🥟',
        'dumpling' => '🥟',
        'spring roll' => '🥟',

        // Eggs & breakfast
        'egg' => '🥚',
        'breakfast' => '🍳',
        'omelette' => '🍳',

        // Fruits
        'fruit' => '🍎',
        'mango' => '🥭',
        'banana' => '🍌',
        'strawberry' => '🍓',

        // Grocery / general
        'grocery' => '🛒',
        'combo' => '🍱',
        'deal' => '🍱',
        'set' => '🍱',
        'meal' => '🍱',
        'special' => '⭐',
        'extra' => '➕',
    ];

    private const COLORS = [
        '#f97316', '#10b981', '#3b82f6', '#8b5cf6',
        '#ec4899', '#f43f5e', '#14b8a6', '#f59e0b',
        '#6366f1', '#06b6d4',
    ];

    public static function forName(string $name): string
    {
        $lower = mb_strtolower($name);

        foreach (self::MAP as $keyword => $emoji) {
            if (str_contains($lower, $keyword)) {
                return $emoji;
            }
        }

        return '🍽️';
    }

    public static function colorForId(int $id): string
    {
        return self::COLORS[$id % count(self::COLORS)];
    }
}
