<?php
/**
 * Adds the Partner Logos content block. Safe to re-run.
 *
 *     ddev exec php scripts/add-partner-logos-component.php
 *     ddev exec php craft project-config/rebuild --interactive=0
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();

function partnerLogosLayout(array $elements, bool $withTitle = false): FieldLayout
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

function savePartnerLogosEntryType(EntryType $entryType): void
{
    if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
        throw new Exception("{$entryType->handle} entry type: " . json_encode($entryType->getErrors()));
    }
}

$image = $fields->getFieldByHandle('image');
$itemLink = $fields->getFieldByHandle('itemLink');
if (!$image || !$itemLink) {
    throw new Exception('missing existing image or itemLink field');
}

$partnerType = $entries->getEntryTypeByHandle('partnerLogo');
if (!$partnerType) {
    $partnerType = new EntryType([
        'handle' => 'partnerLogo',
        'name' => 'Partner Logo',
        'hasTitleField' => true,
        'titleTranslationMethod' => 'site',
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $partnerType->setFieldLayout(partnerLogosLayout([
        [$image, true, 100],
        [$itemLink, false, 100],
    ], true));
    savePartnerLogosEntryType($partnerType);
}

$partnerLogos = $fields->getFieldByHandle('partnerLogos');
if (!$partnerLogos) {
    $partnerLogos = new Matrix([
        'handle' => 'partnerLogos',
        'name' => 'Partner Logos',
        'instructions' => 'Add partners in display order. The title is used as the accessible logo name. Wide logos automatically span two columns; images are never cropped.',
        'propagationMethod' => 'all',
        'translationMethod' => 'site',
        'viewMode' => 'blocks',
        'includeTableView' => false,
        'defaultIndexViewMode' => 'cards',
        'showCardsInGrid' => false,
        'enableVersioning' => false,
    ]);
    $partnerLogos->setEntryTypes([$partnerType]);
    if (!$fields->saveField($partnerLogos)) {
        throw new Exception('partnerLogos field: ' . json_encode($partnerLogos->getErrors()));
    }
}

$gridType = $entries->getEntryTypeByHandle('partnerLogoGrid');
if (!$gridType) {
    $gridType = new EntryType([
        'handle' => 'partnerLogoGrid',
        'name' => 'Partner Logos',
        'hasTitleField' => false,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $gridType->setFieldLayout(partnerLogosLayout([
        [$partnerLogos, true, 100],
    ]));
    savePartnerLogosEntryType($gridType);
}

$contentBlocks = $fields->getFieldByHandle('contentBlocks');
if (!$contentBlocks instanceof Matrix) {
    throw new Exception('missing contentBlocks Matrix field');
}
$allowedTypes = $contentBlocks->getEntryTypes();
if (!array_filter($allowedTypes, fn(EntryType $type) => $type->id === $gridType->id)) {
    $allowedTypes[] = $gridType;
    $contentBlocks->setEntryTypes($allowedTypes);
    if (!$fields->saveField($contentBlocks)) {
        throw new Exception('contentBlocks field: ' . json_encode($contentBlocks->getErrors()));
    }
}

$gql = Craft::$app->getGql();
$schema = $gql->getPublicSchema();
$scope = $schema->scope;
$scope[] = "nestedentryfields.{$partnerLogos->uid}:read";
$schema->scope = array_values(array_unique($scope));
if (!$gql->saveSchema($schema)) {
    throw new Exception('public GraphQL schema: ' . json_encode($schema->getErrors()));
}
Craft::$app->getProjectConfig()->saveModifiedConfigData();
echo "Partner Logos content block is ready. Review config/project/.\n";
