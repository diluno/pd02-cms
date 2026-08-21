<?php
/**
 * Adds an optional Alert Icon asset field to the Alert Bar content block.
 *
 *     ddev exec php scripts/add-alert-icon-field.php
 *
 * Mirrors the Box Icon field: single image, uploads default to the
 * "icons" folder of the assets volume. Safe to re-run.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();

$iconField = $fields->getFieldByHandle('alertIcon');

if (!$iconField) {
    $assetsVolume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
    if (!$assetsVolume) {
        throw new Exception('no "assets" volume');
    }
    $source = "volume:{$assetsVolume->uid}";

    $iconField = new Assets([
        'handle' => 'alertIcon',
        'name' => 'Alert Icon',
        'instructions' => 'Optional. Shown left of the text. Upload to the "icons" folder.',
        'sources' => '*',
        'defaultUploadLocationSource' => $source,
        'defaultUploadLocationSubpath' => 'icons',
        'restrictedLocationSource' => $source,
        'allowedKinds' => ['image'],
        'restrictFiles' => true,
        'maxRelations' => 1,
        'viewMode' => 'list',
        'previewMode' => 'full',
        'allowUploads' => true,
        'translationMethod' => 'site',
    ]);

    if (!$fields->saveField($iconField)) {
        throw new Exception('alertIcon field: ' . json_encode($iconField->getErrors()));
    }
    echo "✓ field alertIcon\n";
} else {
    echo "· field alertIcon exists\n";
}

$alertType = $entries->getEntryTypeByHandle('alertBar');
$layout = $alertType->getFieldLayout();
$tab = $layout->getTabs()[0];
$elements = $tab->getElements();

foreach ($elements as $el) {
    if ($el instanceof CustomField && $el->getFieldUid() === $iconField->uid) {
        echo "· field already present on Alert Bar\n";
        exit;
    }
}

// Insert after Alert Color, before Richtext.
$new = [];
foreach ($elements as $el) {
    $new[] = $el;
    if ($el instanceof CustomField && $el->getField()->handle === 'alertColor') {
        $new[] = new CustomField($iconField);
    }
}
$tab->setElements($new);
$alertType->setFieldLayout($layout);

if (!$entries->saveEntryType($alertType)) {
    throw new Exception('alertBar entry type: ' . json_encode($alertType->getErrors()));
}
echo "✓ field added to Alert Bar\n";

// Flush project config to YAML (normally done at end of a full request).
Craft::$app->getProjectConfig()->saveModifiedConfigData();
