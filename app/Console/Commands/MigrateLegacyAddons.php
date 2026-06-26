<?php

namespace App\Console\Commands;

use App\Model\ModifierTemplate;
use App\Model\ModifierTemplateItem;
use App\Model\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyAddons extends Command
{
    protected $signature = 'products:migrate-legacy-addons
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Migrate legacy products.add_ons to modifier templates for products without templates';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;

        Product::query()
            ->withCount('modifierTemplates')
            ->orderBy('id')
            ->chunkById(50, function ($products) use ($dryRun, &$migrated, &$skipped) {
                foreach ($products as $product) {
                    $legacyIds = $product->legacyAddonIds();
                    if (count($legacyIds) === 0) {
                        continue;
                    }

                    if ($product->modifier_templates_count > 0) {
                        $this->line("Skipping product #{$product->id} ({$product->name}): already has modifier templates.");
                        $skipped++;
                        continue;
                    }

                    $templateName = $this->uniqueTemplateName("{$product->name} Extras");

                    if ($dryRun) {
                        $this->info("[dry-run] Would migrate product #{$product->id} ({$product->name}) with addons: " . implode(', ', $legacyIds));
                        $migrated++;
                        continue;
                    }

                    DB::transaction(function () use ($product, $legacyIds, $templateName, &$migrated) {
                        $template = ModifierTemplate::create([
                            'name' => $templateName,
                            'description' => 'Migrated from legacy product add-ons',
                            'selection_type' => 'multi',
                            'min_select' => 0,
                            'max_select' => count($legacyIds),
                            'is_required' => 0,
                            'is_active' => 1,
                        ]);

                        foreach (array_values($legacyIds) as $index => $addonId) {
                            ModifierTemplateItem::create([
                                'modifier_template_id' => $template->id,
                                'add_on_id' => $addonId,
                                'sort_order' => $index,
                                'is_default' => 0,
                                'is_active' => 1,
                            ]);
                        }

                        $product->modifierTemplates()->sync([
                            $template->id => [
                                'sort_order' => 0,
                                'is_active' => 1,
                            ],
                        ]);

                        $product->add_ons = json_encode([]);
                        $product->save();

                        $migrated++;
                        $this->info("Migrated product #{$product->id} ({$product->name}) → template \"{$templateName}\"");
                    });
                }
            });

        $mode = $dryRun ? ' (dry-run)' : '';
        $this->info("Done{$mode}: {$migrated} product(s) migrated, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function uniqueTemplateName(string $baseName): string
    {
        $name = $baseName;
        $suffix = 2;

        while (ModifierTemplate::where('name', $name)->exists()) {
            $name = "{$baseName} ({$suffix})";
            $suffix++;
        }

        return $name;
    }
}
