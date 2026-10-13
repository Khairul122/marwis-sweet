<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ProductAvailabilityService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    /** @param list<array<string, mixed>> $variants */
    public function annotateVariants(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        $variantId = static fn (array $variant): int => (int) ($variant['id'] ?? $variant['variant_id'] ?? 0);
        $variantIds = array_values(array_unique(array_map($variantId, $variants)));
        $recipeIdsByVariant = [];
        foreach ($this->database->table('recipes')
            ->whereIn('product_variant_id', $variantIds)
            ->where('is_active', true)
            ->orderBy('version', 'DESC')
            ->get()->getResultArray() as $recipe) {
            $recipeIdsByVariant[(int) $recipe['product_variant_id']] ??= (int) $recipe['id'];
        }

        $itemsByRecipe = [];
        if ($recipeIdsByVariant !== []) {
            foreach ($this->database->table('recipe_items')->whereIn('recipe_id', array_values($recipeIdsByVariant))->get()->getResultArray() as $item) {
                $itemsByRecipe[(int) $item['recipe_id']][] = $item;
            }
        }

        $requirements = [];
        $configurationErrors = [];
        foreach ($variants as $variant) {
            $id = $variantId($variant);
            $requirements[$id] = [];
            if ($variant['stock_mode'] === 'none') {
                continue;
            }
            if ($variant['stock_mode'] === 'direct') {
                $itemId = (int) ($variant['direct_inventory_item_id'] ?? 0);
                if ($itemId < 1) {
                    $configurationErrors[$id] = 'Pemetaan stok produk belum tersedia.';
                    continue;
                }
                $requirements[$id][$itemId] = 1000;
                continue;
            }
            if ($variant['stock_mode'] !== 'recipe') {
                $configurationErrors[$id] = 'Pengaturan stok produk belum valid.';
                continue;
            }

            $recipeId = $recipeIdsByVariant[$id] ?? null;
            $recipeItems = $recipeId === null ? [] : ($itemsByRecipe[$recipeId] ?? []);
            if ($recipeItems === []) {
                $configurationErrors[$id] = 'Resep produk belum siap digunakan.';
                continue;
            }
            foreach ($recipeItems as $item) {
                $itemId = (int) $item['inventory_item_id'];
                $quantityMilli = Money::quantityMilli($item['quantity']);
                if ($itemId < 1 || $quantityMilli < 1) {
                    $configurationErrors[$id] = 'Resep produk belum siap digunakan.';
                    break;
                }
                $requirements[$id][$itemId] = ($requirements[$id][$itemId] ?? 0) + $quantityMilli;
            }
        }

        $states = $this->evaluate($requirements, $configurationErrors);
        return array_map(static function (array $variant) use ($states, $variantId): array {
            return $variant + $states[$variantId($variant)];
        }, $variants);
    }

    /** @param list<array<string, mixed>> $toppings */
    public function annotateToppings(array $toppings): array
    {
        if ($toppings === []) {
            return [];
        }

        $toppingIds = array_values(array_unique(array_map(static fn (array $topping): int => (int) $topping['id'], $toppings)));
        $requirements = array_fill_keys($toppingIds, []);
        foreach ($this->database->table('topping_recipe_items')->whereIn('topping_id', $toppingIds)->get()->getResultArray() as $item) {
            $toppingId = (int) $item['topping_id'];
            $itemId = (int) $item['inventory_item_id'];
            $quantityMilli = Money::quantityMilli($item['quantity']);
            if ($itemId > 0 && $quantityMilli > 0) {
                $requirements[$toppingId][$itemId] = ($requirements[$toppingId][$itemId] ?? 0) + $quantityMilli;
            }
        }

        $states = $this->evaluate($requirements);
        return array_map(static function (array $topping) use ($states): array {
            return $topping + $states[(int) $topping['id']];
        }, $toppings);
    }

    /** @param array<int, array<int, int>> $requirements @param array<int, string> $configurationErrors */
    private function evaluate(array $requirements, array $configurationErrors = []): array
    {
        $inventoryItemIds = [];
        foreach ($requirements as $items) {
            array_push($inventoryItemIds, ...array_keys($items));
        }
        $inventoryItemIds = array_values(array_unique(array_map('intval', $inventoryItemIds)));

        $inventory = [];
        if ($inventoryItemIds !== []) {
            foreach ($this->database->table('inventory_items')->select('id, quantity_on_hand')->whereIn('id', $inventoryItemIds)->get()->getResultArray() as $item) {
                $inventory[(int) $item['id']] = Money::quantityMilli($item['quantity_on_hand']);
            }
        }
        $locks = (new InventoryLockService($this->database))->findLocks($inventoryItemIds);

        $states = [];
        foreach ($requirements as $recordId => $items) {
            if (isset($configurationErrors[$recordId])) {
                $states[$recordId] = $this->state('unconfigured', $configurationErrors[$recordId]);
                continue;
            }

            $lockedItemIds = array_intersect(array_keys($items), array_keys($locks));
            if ($lockedItemIds !== []) {
                $states[$recordId] = $this->state('locked', 'Stok yang dibutuhkan sedang dikunci untuk stok opname.');
                continue;
            }

            $missingItem = false;
            $insufficientStock = false;
            foreach ($items as $itemId => $requiredMilli) {
                if (!array_key_exists($itemId, $inventory)) {
                    $missingItem = true;
                    break;
                }
                if ($inventory[$itemId] < $requiredMilli) {
                    $insufficientStock = true;
                    break;
                }
            }
            if ($missingItem) {
                $states[$recordId] = $this->state('unconfigured', 'Data stok yang dibutuhkan belum lengkap.');
            } elseif ($insufficientStock) {
                $states[$recordId] = $this->state('sold_out', 'Stok produk atau bahannya tidak mencukupi.');
            } else {
                $states[$recordId] = $this->state('available', '');
            }
        }

        return $states;
    }

    /** @return array{is_available:bool, availability_status:string, availability_reason:string} */
    private function state(string $status, string $reason): array
    {
        return [
            'is_available' => $status === 'available',
            'availability_status' => $status,
            'availability_reason' => $reason,
        ];
    }
}
