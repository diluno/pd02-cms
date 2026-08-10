<?php
/**
 * Adds the heroStatement content block.
 *
 *     ddev exec php scripts/add-hero-block.php
 *     ddev craft project-config/rebuild
 *
 * The landing page opens with a full-width cyan band carrying one 36px
 * statement (Figma node 683:63). Nothing in the model represented it, so the
 * landing could not be built from the CMS.
 *
 * No GraphQL scope change is needed: scopes are granted per nested-entry
 * field, and contentBlocks is already scoped.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();

$heroType = $entries->getEntryTypeByHandle('heroStatement');

if (!$heroType) {
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content', 'sortOrder' => 1]);
    $tab->setLayout($layout);
    $tab->setElements([new EntryTitleField(['label' => 'Statement'])]);
    $layout->setTabs([$tab]);

    $heroType = new EntryType([
        'handle' => 'heroStatement',
        'name' => 'Hero Statement',
        'icon' => 'bullhorn',
        'hasTitleField' => true,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $heroType->setFieldLayout($layout);

    if (!$entries->saveEntryType($heroType)) {
        throw new Exception('heroStatement: ' . json_encode($heroType->getErrors()));
    }
    echo "✓ entry type heroStatement\n";
} else {
    echo "· entry type heroStatement exists\n";
}

$contentBlocks = $fields->getFieldByHandle('contentBlocks');
$existing = $contentBlocks->getEntryTypes();

foreach ($existing as $type) {
    if ($type->handle === 'heroStatement') {
        echo "· contentBlocks already offers heroStatement\n";
        return;
    }
}

// Put the hero first — it opens a page.
array_unshift($existing, $heroType);
$contentBlocks->setEntryTypes($existing);

if (!$fields->saveField($contentBlocks)) {
    throw new Exception('contentBlocks: ' . json_encode($contentBlocks->getErrors()));
}
echo "✓ contentBlocks now offers heroStatement\n";
