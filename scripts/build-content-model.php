<?php
/**
 * One-off builder for the Digital Violence Info content model.
 *
 * Run once locally with CRAFT_ALLOW_ADMIN_CHANGES=true:
 *
 *     ddev exec php scripts/build-content-model.php
 *
 * Craft writes the result to config/project/, which is the source of truth from
 * then on — staging and production get it via `craft up`, never by running this.
 * Every step is keyed on a handle and skipped if that handle already exists, so
 * a re-run after a partial failure picks up where it stopped.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\ckeditor\Field as CkeditorField;
use craft\elements\Entry;
use craft\elements\GlobalSet as GlobalSetElement;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Assets;
use craft\fields\ButtonGroup;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;

$fieldsService = Craft::$app->getFields();
$entriesService = Craft::$app->getEntries();
$sitesService = Craft::$app->getSites();

function info(string $message): void
{
    echo $message . PHP_EOL;
}

/** Existing field by handle, or null. */
function existingField(string $handle)
{
    return Craft::$app->getFields()->getFieldByHandle($handle);
}

/** Existing entry type by handle, or null. */
function existingEntryType(string $handle)
{
    return Craft::$app->getEntries()->getEntryTypeByHandle($handle);
}

/**
 * Saves a field unless its handle is taken, and returns the field either way.
 */
function field(craft\base\FieldInterface $field): craft\base\FieldInterface
{
    $existing = existingField($field->handle);
    if ($existing) {
        info("  · field {$field->handle} exists, skipping");
        return $existing;
    }
    if (!Craft::$app->getFields()->saveField($field)) {
        throw new Exception("field {$field->handle}: " . json_encode($field->getErrors()));
    }
    info("  ✓ field {$field->handle}");
    return $field;
}

/**
 * Builds a single-tab field layout. Pass field instances; a leading `true`
 * adds the entry title field ahead of them.
 */
function layout(array $fields, bool $withTitle = true, string $elementType = Entry::class): FieldLayout
{
    $elements = [];
    if ($withTitle) {
        $elements[] = new EntryTitleField();
    }
    foreach ($fields as $f) {
        $elements[] = new CustomField($f);
    }

    $layout = new FieldLayout(['type' => $elementType]);

    // The tab has to know its layout before it will accept elements.
    $tab = new FieldLayoutTab(['name' => 'Content', 'sortOrder' => 1]);
    $tab->setLayout($layout);
    $tab->setElements($elements);

    $layout->setTabs([$tab]);

    return $layout;
}

/**
 * Saves an entry type unless its handle is taken, and returns it either way.
 */
function entryType(
    string $handle,
    string $name,
    array $fields,
    bool $hasTitle = true,
    string $icon = 'block-brick'
): EntryType {
    $existing = existingEntryType($handle);
    if ($existing) {
        info("  · entry type {$handle} exists, skipping");
        return $existing;
    }

    $type = new EntryType([
        'handle' => $handle,
        'name' => $name,
        'icon' => $icon,
        'hasTitleField' => $hasTitle,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $type->setFieldLayout(layout($fields, $hasTitle));

    if (!Craft::$app->getEntries()->saveEntryType($type)) {
        throw new Exception("entry type {$handle}: " . json_encode($type->getErrors()));
    }
    info("  ✓ entry type {$handle}");

    return $type;
}

/** ButtonGroup option in the shape Craft stores. */
function option(string $label, string $value, bool $default = false): array
{
    return [
        'label' => $label,
        'value' => $value,
        'icon' => '',
        'default' => $default ? '1' : '',
    ];
}

/** A Matrix field holding the given entry types, all in one "General" group. */
function matrix(string $handle, string $name, array $entryTypes): Matrix
{
    $matrix = new Matrix([
        'handle' => $handle,
        'name' => $name,
        'propagationMethod' => 'all',
        'translationMethod' => 'site',
        'viewMode' => 'blocks',
        'includeTableView' => false,
        'defaultIndexViewMode' => 'cards',
        'showCardsInGrid' => false,
        'enableVersioning' => false,
    ]);
    $matrix->setEntryTypes($entryTypes);

    return $matrix;
}

// ---------------------------------------------------------------------------
// 1. French site
// ---------------------------------------------------------------------------

info('Sites');

$primarySite = $sitesService->getPrimarySite();
$frSite = $sitesService->getSiteByHandle('fr');

if (!$frSite) {
    $frSite = new Site([
        'groupId' => $primarySite->groupId,
        'name' => 'Digital Violence Info FR',
        'handle' => 'fr',
        'language' => 'fr',
        'hasUrls' => true,
        'baseUrl' => '$PRIMARY_SITE_URL/fr',
        'primary' => false,
        'enabled' => true,
    ]);
    if (!$sitesService->saveSite($frSite)) {
        throw new Exception('fr site: ' . json_encode($frSite->getErrors()));
    }
    info('  ✓ site fr');
} else {
    info('  · site fr exists, skipping');
}

$siteIds = [$primarySite->id, $frSite->id];

// ---------------------------------------------------------------------------
// 2. Fields
// ---------------------------------------------------------------------------

info('Fields');

$ckToolbar = [
    'heading', 'style', '|', 'bold', 'italic', 'link',
    'bulletedList', 'numberedList', 'insertImage',
];

$leadText = field(new CkeditorField([
    'handle' => 'leadText',
    'name' => 'Lead Text',
    'purifyHtml' => true,
    'headingLevels' => [2, 3, 4],
    'toolbar' => $ckToolbar,
    'availableVolumes' => '*',
    'availableTransforms' => '',
    'translationMethod' => 'site',
]));

$heroStatement = field(new PlainText([
    'handle' => 'heroStatement',
    'name' => 'Hero Statement',
    'multiline' => true,
    'translationMethod' => 'site',
]));

$richtext = field(new CkeditorField([
    'handle' => 'richtext',
    'name' => 'Richtext',
    'purifyHtml' => true,
    'headingLevels' => [2, 3, 4],
    'toolbar' => $ckToolbar,
    'availableVolumes' => '*',
    'availableTransforms' => '',
    'translationMethod' => 'site',
]));

$singleLineText = field(new PlainText([
    'handle' => 'singleLineText',
    'name' => 'Single Line Text',
    'multiline' => false,
    'translationMethod' => 'site',
]));

$imageAltText = field(new PlainText([
    'handle' => 'imageAltText',
    'name' => 'Image Alt Text',
    'multiline' => false,
    'translationMethod' => 'site',
]));

$caption = field(new PlainText([
    'handle' => 'caption',
    'name' => 'Caption',
    'multiline' => false,
    'translationMethod' => 'site',
]));

// The box system from the Figma "Boxen" spec. Width and title size live on the
// group, not the box, because the design requires them to be uniform across a
// group; colour is per box.
$boxWidth = field(new ButtonGroup([
    'handle' => 'boxWidth',
    'name' => 'Box Width',
    'translationMethod' => 'none',
    'options' => [
        option('1/1', 'full', true),
        option('1/2', 'half'),
        option('1/3', 'third'),
    ],
]));

$titleSize = field(new ButtonGroup([
    'handle' => 'titleSize',
    'name' => 'Title Size',
    'translationMethod' => 'none',
    'options' => [
        option('Gross', 'large', true),
        option('Klein', 'small'),
    ],
]));

$boxColor = field(new ButtonGroup([
    'handle' => 'boxColor',
    'name' => 'Box Color',
    'translationMethod' => 'none',
    'options' => [
        option('Weiss', 'white', true),
        option('Hellblau', 'lightblue'),
        option('Rot – Rechtliches/Schweiz', 'rose'),
        option('Grün – Handlungsaufforderung', 'green'),
        option('Blau – Info', 'blue'),
        option('Lila – Alarm/wichtig', 'lilac'),
    ],
]));

$alertColor = field(new ButtonGroup([
    'handle' => 'alertColor',
    'name' => 'Alert Color',
    'translationMethod' => 'none',
    'options' => [
        option('Rot – Rechtliches/Schweiz', 'rose', true),
        option('Grün – Handlungsaufforderung', 'green'),
        option('Blau – Info', 'blue'),
        option('Lila – Alarm/wichtig', 'lilac'),
    ],
]));

$spacerSize = field(new ButtonGroup([
    'handle' => 'spacerSize',
    'name' => 'Spacer Size',
    'translationMethod' => 'none',
    'options' => [
        option('Small', 'small', true),
        option('Large', 'large'),
    ],
]));

// Craft rejects an Assets field whose default upload location is unset, so
// point both asset fields at the R2 "assets" volume explicitly.
$assetsVolume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
if (!$assetsVolume) {
    throw new Exception('no "assets" volume — create it before running this');
}
$assetsVolumeSource = "volume:{$assetsVolume->uid}";

$assetSettings = [
    'sources' => '*',
    'defaultUploadLocationSource' => $assetsVolumeSource,
    'restrictedLocationSource' => $assetsVolumeSource,
    'allowedKinds' => ['image'],
    'restrictFiles' => true,
    'viewMode' => 'list',
    'previewMode' => 'full',
    'allowUploads' => true,
    'translationMethod' => 'site',
];

$boxIcon = field(new Assets(array_merge($assetSettings, [
    'handle' => 'boxIcon',
    'name' => 'Box Icon',
    'maxRelations' => 1,
    'defaultUploadLocationSubpath' => 'icons',
])));

$image = field(new Assets(array_merge($assetSettings, [
    'handle' => 'image',
    'name' => 'Image',
    'maxRelations' => 1,
    'defaultUploadLocationSubpath' => 'images',
])));

$linkSettings = [
    'types' => ['entry', 'url', 'asset'],
    'showLabelField' => true,
    'translationMethod' => 'site',
];

$ctaLink = field(new Link(array_merge($linkSettings, [
    'handle' => 'ctaLink',
    'name' => 'CTA Link',
])));

$itemLink = field(new Link(array_merge($linkSettings, [
    'handle' => 'itemLink',
    'name' => 'Link',
])));

// ---------------------------------------------------------------------------
// 3. Leaf block entry types (must exist before the Matrix fields that hold them)
// ---------------------------------------------------------------------------

info('Leaf block entry types');

$boxType = entryType('box', 'Box', [$boxColor, $richtext, $boxIcon, $image, $ctaLink], true, 'square');
$accordionItemType = entryType('accordionItem', 'Accordion Item', [$richtext], true, 'list');
$linkItemType = entryType('linkItem', 'Link Item', [$itemLink], true, 'link');
$navigationLinkType = entryType('navigationLink', 'Navigation Link', [$itemLink], true, 'link');
$footerLinkType = entryType('footerLink', 'Footer Link', [$itemLink], true, 'link');

// ---------------------------------------------------------------------------
// 4. Container Matrix fields
// ---------------------------------------------------------------------------

info('Container matrix fields');

$boxes = field(matrix('boxes', 'Boxes', [$boxType]));
$accordionItems = field(matrix('accordionItems', 'Accordion Items', [$accordionItemType]));
$linkItems = field(matrix('linkItems', 'Link Items', [$linkItemType]));
$headerNavigation = field(matrix('headerNavigation', 'Header Navigation', [$navigationLinkType]));
$footerLinks = field(matrix('footerLinks', 'Footer Links', [$footerLinkType]));

// ---------------------------------------------------------------------------
// 5. Content block entry types
// ---------------------------------------------------------------------------

info('Content block entry types');

$blockTypes = [
    entryType('sectionLabel', 'Section Label', [], true, 'tag'),
    entryType('contentRichtext', 'Richtext', [$richtext], false, 'align-left'),
    entryType('boxGroup', 'Box Group', [$boxWidth, $titleSize, $boxes], false, 'grid'),
    entryType('linkList', 'Link List', [$linkItems], false, 'link'),
    entryType('accordion', 'Accordion', [$accordionItems], false, 'list'),
    entryType('imageWithCaption', 'Image with Caption', [$image, $caption], false, 'image'),
    entryType('alertBar', 'Alert Bar', [$alertColor, $richtext], false, 'triangle-exclamation'),
    entryType('spacer', 'Spacer', [$spacerSize], false, 'arrows-up-down'),
];

info('Content blocks field');
$contentBlocks = field(matrix('contentBlocks', 'Content Blocks', $blockTypes));

// ---------------------------------------------------------------------------
// 6. Page entry types + sections
// ---------------------------------------------------------------------------

info('Page entry types');

$homeType = existingEntryType('home');
if (!$homeType) {
    $homeType = new EntryType([
        'handle' => 'home',
        'name' => 'Home',
        'icon' => 'house',
        'hasTitleField' => true,
        'showSlugField' => false,
        'showStatusField' => true,
    ]);
    $homeType->setFieldLayout(layout([$heroStatement, $leadText, $contentBlocks]));
    if (!$entriesService->saveEntryType($homeType)) {
        throw new Exception('home entry type: ' . json_encode($homeType->getErrors()));
    }
    info('  ✓ entry type home');
} else {
    info('  · entry type home exists, skipping');
}

$topicPageType = existingEntryType('topicPage');
if (!$topicPageType) {
    $topicPageType = new EntryType([
        'handle' => 'topicPage',
        'name' => 'Topic Page',
        'icon' => 'file-lines',
        'hasTitleField' => true,
        'showSlugField' => true,
        'showStatusField' => true,
    ]);
    $topicPageType->setFieldLayout(layout([$leadText, $contentBlocks], true));
    if (!$entriesService->saveEntryType($topicPageType)) {
        throw new Exception('topicPage entry type: ' . json_encode($topicPageType->getErrors()));
    }
    info('  ✓ entry type topicPage');
} else {
    info('  · entry type topicPage exists, skipping');
}

info('Sections');

/** Site settings for every site, with the given URI format. */
function siteSettingsFor(array $siteIds, ?string $uriFormat): array
{
    $settings = [];
    foreach ($siteIds as $siteId) {
        $settings[$siteId] = new Section_SiteSettings([
            'siteId' => $siteId,
            'enabledByDefault' => true,
            'hasUrls' => $uriFormat !== null,
            'uriFormat' => $uriFormat,
            'template' => null,
        ]);
    }
    return $settings;
}

if (!$entriesService->getSectionByHandle('home')) {
    $homeSection = new Section([
        'handle' => 'home',
        'name' => 'Home',
        'type' => Section::TYPE_SINGLE,
        'enableVersioning' => true,
        'propagationMethod' => 'all',
        'siteSettings' => siteSettingsFor($siteIds, '__home__'),
    ]);
    $homeSection->setEntryTypes([$homeType]);
    if (!$entriesService->saveSection($homeSection)) {
        throw new Exception('home section: ' . json_encode($homeSection->getErrors()));
    }
    info('  ✓ section home');
} else {
    info('  · section home exists, skipping');
}

if (!$entriesService->getSectionByHandle('pages')) {
    $pagesSection = new Section([
        'handle' => 'pages',
        'name' => 'Pages',
        'type' => Section::TYPE_STRUCTURE,
        'maxLevels' => 2,
        'enableVersioning' => true,
        'propagationMethod' => 'all',
        'defaultPlacement' => Section::DEFAULT_PLACEMENT_END,
        'previewTargets' => [
            ['label' => 'Primary entry page', 'urlFormat' => '{url}', 'refresh' => '1'],
        ],
        'siteSettings' => siteSettingsFor($siteIds, '{slug}'),
    ]);
    $pagesSection->setEntryTypes([$topicPageType]);
    if (!$entriesService->saveSection($pagesSection)) {
        throw new Exception('pages section: ' . json_encode($pagesSection->getErrors()));
    }
    info('  ✓ section pages');
} else {
    info('  · section pages exists, skipping');
}

// ---------------------------------------------------------------------------
// 7. Global sets
// ---------------------------------------------------------------------------

info('Global sets');

$globalsService = Craft::$app->getGlobals();

if (!$globalsService->getSetByHandle('general')) {
    $general = new GlobalSetElement(['handle' => 'general', 'name' => 'General']);
    $general->setFieldLayout(layout([$headerNavigation, $footerLinks], false, GlobalSetElement::class));
    if (!$globalsService->saveSet($general)) {
        throw new Exception('general global set: ' . json_encode($general->getErrors()));
    }
    info('  ✓ global set general');
} else {
    info('  · global set general exists, skipping');
}

if (!$globalsService->getSetByHandle('translations')) {
    $translations = new GlobalSetElement(['handle' => 'translations', 'name' => 'Translations']);
    $translations->setFieldLayout(layout([$singleLineText], false, GlobalSetElement::class));
    if (!$globalsService->saveSet($translations)) {
        throw new Exception('translations global set: ' . json_encode($translations->getErrors()));
    }
    info('  ✓ global set translations');
} else {
    info('  · global set translations exists, skipping');
}

// ---------------------------------------------------------------------------
// 8. Alt text on the asset volume
// ---------------------------------------------------------------------------

info('Asset volume');

$volume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
if ($volume) {
    $volumeLayout = $volume->getFieldLayout();
    $hasAlt = false;
    foreach ($volumeLayout->getCustomFields() as $f) {
        if ($f->handle === 'imageAltText') {
            $hasAlt = true;
            break;
        }
    }
    if (!$hasAlt) {
        $tabs = $volumeLayout->getTabs();
        if (!$tabs) {
            $tabs = [new FieldLayoutTab(['name' => 'Content', 'sortOrder' => 1])];
        }
        $elements = $tabs[0]->getElements();
        $elements[] = new CustomField($imageAltText);
        $tabs[0]->setElements($elements);
        $volumeLayout->setTabs($tabs);
        $volume->setFieldLayout($volumeLayout);
        if (!Craft::$app->getVolumes()->saveVolume($volume)) {
            throw new Exception('assets volume: ' . json_encode($volume->getErrors()));
        }
        info('  ✓ imageAltText added to assets volume');
    } else {
        info('  · assets volume already has imageAltText, skipping');
    }
} else {
    info('  ! no "assets" volume found, skipping');
}

// ---------------------------------------------------------------------------
// 9. Public GraphQL schema scope
//
// This is the step that silently breaks the frontend when missed: every
// section, global set and *nested entry* field (Matrix + CKEditor) needs its
// own read scope, or queries come back null with no useful error.
// ---------------------------------------------------------------------------

info('GraphQL public schema');

$gql = Craft::$app->getGql();
$publicSchema = $gql->getPublicSchema();

$scope = [
    'elements.drafts:read',
    'elements.revisions:read',
    'elements.inactive:read',
    'usergroups.everyone:read',
    'directive:transform',
    'directive:parseRefs',
];

foreach ($sitesService->getAllSites() as $site) {
    $scope[] = "sites.{$site->uid}:read";
}
foreach ($entriesService->getAllSections() as $section) {
    $scope[] = "sections.{$section->uid}:read";
}
foreach ($globalsService->getAllSets() as $set) {
    $scope[] = "globalsets.{$set->uid}:read";
}
foreach (Craft::$app->getVolumes()->getAllVolumes() as $vol) {
    $scope[] = "volumes.{$vol->uid}:read";
}
foreach ($fieldsService->getAllFields() as $f) {
    if ($f instanceof Matrix || $f instanceof CkeditorField) {
        $scope[] = "nestedentryfields.{$f->uid}:read";
    }
}

$scope = array_values(array_unique($scope));
sort($scope);

$publicSchema->scope = $scope;
if (!$gql->saveSchema($publicSchema)) {
    throw new Exception('public schema: ' . json_encode($publicSchema->getErrors()));
}
info('  ✓ public schema scoped (' . count($scope) . ' entries)');

info('');
info('Done. Review config/project/ and commit.');
