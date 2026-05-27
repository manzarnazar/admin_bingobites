<?php

namespace App\Console\Commands;

use App\Model\ModifierGroup;
use App\Model\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProductVariationsToModifierGroups extends Command
{
    protected $signature = 'modifiers:migrate-variations {--category_id=} {--dry-run}';

    protected $description = 'Convert legacy product variation JSON into reusable modifier groups and attach them to products.';

    public function handle(): int
    {
        $categoryId = $this->option('category_id');
        $dryRun = (bool) $this->option('dry-run');

        $products = Product::withoutGlobalScopes()
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->whereJsonContains('category_ids', [['id' => (string) $categoryId]]);
            })
            ->get();

        $attachedCount = 0;
        $createdGroups = 0;

        foreach ($products as $product) {
            $variations = json_decode($product->variations, true) ?? [];
            if (empty($variations)) {
                continue;
            }

            $syncData = [];
            foreach ($variations as $index => $variation) {
                if (!isset($variation['name']) || !isset($variation['values']) || !is_array($variation['values'])) {
                    continue;
                }

                $groupName = trim($variation['name']);
                if ($groupName === '') {
                    continue;
                }

                $existingGroup = ModifierGroup::where('name', $groupName)->first();
                if (!$existingGroup && !$dryRun) {
                    $existingGroup = ModifierGroup::create([
                        'name' => $groupName,
                        'selection_type' => ($variation['type'] ?? 'multi') === 'single' ? 'single' : 'multi',
                        'min' => (int) ($variation['min'] ?? 0),
                        'max' => (int) ($variation['max'] ?? 0),
                        'is_required' => ($variation['required'] ?? 'off') === 'on',
                        'is_active' => true,
                    ]);
                    $createdGroups++;
                }

                if (!$existingGroup) {
                    continue;
                }

                if (!$dryRun && $existingGroup->options()->count() === 0) {
                    $options = [];
                    foreach (array_values($variation['values']) as $optionIndex => $value) {
                        if (!isset($value['label'])) {
                            continue;
                        }
                        $options[] = [
                            'name' => $value['label'],
                            'additional_price' => (float) ($value['optionPrice'] ?? 0),
                            'position' => $optionIndex,
                            'is_active' => true,
                        ];
                    }

                    if (!empty($options)) {
                        $existingGroup->options()->createMany($options);
                    }
                }

                $syncData[$existingGroup->id] = [
                    'selection_type' => ($variation['type'] ?? 'multi') === 'single' ? 'single' : 'multi',
                    'min' => (int) ($variation['min'] ?? 0),
                    'max' => (int) ($variation['max'] ?? 0),
                    'is_required' => ($variation['required'] ?? 'off') === 'on',
                    'position' => $index,
                    'is_default_enabled' => 1,
                ];
            }

            if (!empty($syncData)) {
                if (!$dryRun) {
                    DB::transaction(function () use ($product, $syncData) {
                        $product->modifierGroups()->syncWithoutDetaching($syncData);
                    });
                }
                $attachedCount++;
            }
        }

        $this->info("Products processed: {$products->count()}");
        $this->info("Products attached: {$attachedCount}");
        $this->info("New groups created: {$createdGroups}");
        if ($dryRun) {
            $this->warn('Dry run mode: no database changes were written.');
        }

        return self::SUCCESS;
    }
}
