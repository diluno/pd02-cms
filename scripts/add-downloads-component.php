<?php
/**
 * Adds the Downloads content block and its nested Download cards.
 *
 *     ddev exec php scripts/add-downloads-component.php
 *
 * Each card has a localized title, optional preview image and description,
 * required PDF, and optional localized button label. Safe to re-run.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();

function downloadsLayout(array $elements, bool $withTitle = false): FieldLayout
{
    $layoutElements = [];

    if ($withTitle) {
        $layoutElements[] = new EntryTitleField([
            'required' => true,
            'width' => 100,
        ]);
    }

    foreach ($elements as [$field, $required, $width]) {
        $layoutElements[] = new CustomField($field, [
            'required' => $required,
            'width' => $width,
        ]);
    }

    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content', 'sortOrder' => 1]);
    $tab->setLayout($layout);
    $tab->setElements($layoutElements);
    $layout->setTabs([$tab]);

    return $layout;
}

function saveDownloadsEntryType(EntryType $entryType): void
{
    if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
        throw new Exception("{$entryType->handle} entry type: " . json_encode($entryType->getErrors()));
    }
}

$assetsVolume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
if (!$assetsVolume) {
    throw new Exception('no "assets" volume');
}
$assetsSource = "volume:{$assetsVolume->uid}";

$downloadFile = $fields->getFieldByHandle('downloadFile');
if (!$downloadFile) {
    $downloadFile = new Assets([
        'handle' => 'downloadFile',
        'name' => 'Download File',
        'instructions' => 'PDF offered by the download button.',
        'sources' => '*',
        'defaultUploadLocationSource' => $assetsSource,
        'defaultUploadLocationSubpath' => 'downloads',
        'restrictedLocationSource' => $assetsSource,
        'allowedKinds' => ['pdf'],
        'restrictFiles' => true,
        'maxRelations' => 1,
        'viewMode' => 'list',
        'previewMode' => 'full',
        'allowUploads' => true,
        'translationMethod' => 'site',
    ]);

    if (!$fields->saveField($downloadFile)) {
        throw new Exception('downloadFile field: ' . json_encode($downloadFile->getErrors()));
    }
    echo "✓ field downloadFile\n";
} else {
    echo "· field downloadFile exists\n";
}

$downloadLabel = $fields->getFieldByHandle('downloadLabel');
if (!$downloadLabel) {
    $downloadLabel = new PlainText([
        'handle' => 'downloadLabel',
        'name' => 'Download Button Label',
        'instructions' => 'Optional. The localized default is used when empty.',
        'multiline' => false,
        'translationMethod' => 'site',
    ]);

    if (!$fields->saveField($downloadLabel)) {
        throw new Exception('downloadLabel field: ' . json_encode($downloadLabel->getErrors()));
    }
    echo "✓ field downloadLabel\n";
} else {
    echo "· field downloadLabel exists\n";
}

$richtext = $fields->getFieldByHandle('richtext');
$image = $fields->getFieldByHandle('image');
if (!$richtext || !$image) {
    throw new Exception('missing existing richtext or image field');
}

$downloadType = $entries->getEntryTypeByHandle('download');
if (!$downloadType) {
    $downloadType = new EntryType([
        'handle' => 'download',
        'name' => 'Download',
        'icon' => 'download',
        'hasTitleField' => true,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $downloadType->setFieldLayout(downloadsLayout([
        [$image, false, 50],
        [$downloadFile, true, 50],
        [$richtext, false, 100],
        [$downloadLabel, false, 100],
    ], true));
    saveDownloadsEntryType($downloadType);
    echo "✓ entry type download\n";
} else {
    echo "· entry type download exists\n";
}

$downloadItems = $fields->getFieldByHandle('downloadItems');
if (!$downloadItems) {
    $downloadItems = new Matrix([
        'handle' => 'downloadItems',
        'name' => 'Downloads',
        'propagationMethod' => 'all',
        'translationMethod' => 'site',
        'viewMode' => 'blocks',
        'includeTableView' => false,
        'defaultIndexViewMode' => 'cards',
        'showCardsInGrid' => false,
        'enableVersioning' => false,
    ]);
    $downloadItems->setEntryTypes([$downloadType]);

    if (!$fields->saveField($downloadItems)) {
        throw new Exception('downloadItems field: ' . json_encode($downloadItems->getErrors()));
    }
    echo "✓ field downloadItems\n";
} else {
    echo "· field downloadItems exists\n";
}

$downloadsType = $entries->getEntryTypeByHandle('downloads');
if (!$downloadsType) {
    $downloadsType = new EntryType([
        'handle' => 'downloads',
        'name' => 'Downloads',
        'icon' => 'download',
        'hasTitleField' => false,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $downloadsType->setFieldLayout(downloadsLayout([
        [$downloadItems, true, 100],
    ]));
    saveDownloadsEntryType($downloadsType);
    echo "✓ entry type downloads\n";
} else {
    echo "· entry type downloads exists\n";
}

$contentBlocks = $fields->getFieldByHandle('contentBlocks');
if (!$contentBlocks instanceof Matrix) {
    throw new Exception('missing contentBlocks Matrix field');
}

$allowedTypes = $contentBlocks->getEntryTypes();
$hasDownloads = array_filter(
    $allowedTypes,
    fn(EntryType $type) => $type->id === $downloadsType->id
);

if (!$hasDownloads) {
    $allowedTypes[] = $downloadsType;
    $contentBlocks->setEntryTypes($allowedTypes);
    if (!$fields->saveField($contentBlocks)) {
        throw new Exception('contentBlocks field: ' . json_encode($contentBlocks->getErrors()));
    }
    echo "✓ Downloads added to Content Blocks\n";
} else {
    echo "· Downloads already allowed in Content Blocks\n";
}

$gql = Craft::$app->getGql();
$schema = $gql->getPublicSchema();
$scope = $schema->scope;
$scope[] = "nestedentryfields.{$downloadItems->uid}:read";
$scope = array_values(array_unique($scope));
sort($scope);
$schema->scope = $scope;

if (!$gql->saveSchema($schema)) {
    throw new Exception('public GraphQL schema: ' . json_encode($schema->getErrors()));
}
echo "✓ public GraphQL schema\n";

Craft::$app->getProjectConfig()->saveModifiedConfigData();
echo "\nDone. Review config/project/.\n";
