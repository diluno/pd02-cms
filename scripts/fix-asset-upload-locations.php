<?php
/**
 * Points the Assets fields at the "assets" volume for uploads.
 *
 *     ddev exec php scripts/fix-asset-upload-locations.php
 *
 * The original build left defaultUploadLocationSource null, which Craft reports
 * as "set to an invalid volume". Fixed in build-content-model.php too, so this
 * is only needed for installs built before that change.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\fields\Assets;

$fields = Craft::$app->getFields();

$volume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
if (!$volume) {
    throw new Exception('no "assets" volume found');
}
$source = "volume:{$volume->uid}";

$subpaths = [
    'boxIcon' => 'icons',
    'image' => 'images',
];

foreach ($subpaths as $handle => $subpath) {
    $field = $fields->getFieldByHandle($handle);

    if (!$field) {
        echo "! no field {$handle}, skipping\n";
        continue;
    }
    if (!$field instanceof Assets) {
        echo "! {$handle} is not an Assets field, skipping\n";
        continue;
    }

    $field->defaultUploadLocationSource = $source;
    $field->defaultUploadLocationSubpath = $subpath;
    $field->restrictedLocationSource = $source;

    if (!$fields->saveField($field)) {
        throw new Exception("{$handle}: " . json_encode($field->getErrors()));
    }
    echo "✓ {$handle} → {$source}/{$subpath}\n";
}

// A console script exits before Craft flushes project config to YAML.
Craft::$app->getProjectConfig()->saveModifiedConfigData();
echo "\nProject config written.\n";
