<?php
/**
 * Adds a dedicated Home heroStatement field and removes the former Matrix block.
 *
 *     ddev exec php scripts/add-home-hero-field.php
 *
 * Existing hero blocks are intentionally discarded. The script is safe to
 * re-run.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();

$heroField = $fields->getFieldByHandle('heroStatement');

if (!$heroField) {
    $heroField = new PlainText([
        'handle' => 'heroStatement',
        'name' => 'Hero Statement',
        'multiline' => true,
        'translationMethod' => 'site',
    ]);

    if (!$fields->saveField($heroField)) {
        throw new Exception('heroStatement field: ' . json_encode($heroField->getErrors()));
    }
    echo "✓ field heroStatement\n";
} else {
    echo "· field heroStatement exists\n";
}

$contentBlocks = $fields->getFieldByHandle('contentBlocks');
$heroType = $entries->getEntryTypeByHandle('heroStatement');

$homeType = $entries->getEntryTypeByHandle('home');
$layout = $homeType->getFieldLayout();
$tabs = $layout->getTabs();
$tab = $tabs[0];
$layoutElements = $tab->getElements();
$hasHeroField = false;

foreach ($layoutElements as $layoutElement) {
    if ($layoutElement instanceof CustomField && $layoutElement->getFieldUid() === $heroField->uid) {
        $hasHeroField = true;
        break;
    }
}

if (!$hasHeroField) {
    array_unshift($layoutElements, new CustomField($heroField));
    $tab->setElements($layoutElements);
    $homeType->setFieldLayout($layout);

    if (!$entries->saveEntryType($homeType)) {
        throw new Exception('home entry type: ' . json_encode($homeType->getErrors()));
    }
    echo "✓ field added above Lead Text on Home\n";
} else {
    echo "· field already present on Home\n";
}

if ($heroType) {
    $allowedTypes = array_values(array_filter(
        $contentBlocks->getEntryTypes(),
        fn($type) => $type->id !== $heroType->id
    ));
    $contentBlocks->setEntryTypes($allowedTypes);

    if (!$fields->saveField($contentBlocks)) {
        throw new Exception('contentBlocks: ' . json_encode($contentBlocks->getErrors()));
    }

    if (!$entries->deleteEntryType($heroType)) {
        throw new Exception('Could not delete heroStatement entry type');
    }
    echo "✓ removed heroStatement content block\n";
} else {
    echo "· heroStatement content block already removed\n";
}
