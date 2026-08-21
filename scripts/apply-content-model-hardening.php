<?php
/**
 * Applies the audit-driven content model cleanup and SEO additions.
 *
 *     ddev exec php scripts/apply-content-model-hardening.php
 *
 * Safe to re-run. Project config written by this script is the deployment
 * source of truth for staging and production.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\ckeditor\Field as CkeditorField;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\models\FieldLayoutTab;

$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();
$globals = Craft::$app->getGlobals();

function removeFieldFromLayout($element, $field): bool
{
    $layout = $element->getFieldLayout();
    $changed = false;

    foreach ($layout->getTabs() as $tab) {
        $kept = [];
        foreach ($tab->getElements() as $layoutElement) {
            if ($layoutElement instanceof CustomField && $layoutElement->getFieldUid() === $field->uid) {
                $changed = true;
                continue;
            }
            $kept[] = $layoutElement;
        }
        $tab->setElements($kept);
    }

    if ($changed) {
        $element->setFieldLayout($layout);
    }

    return $changed;
}

function ensureSeoTab($entryType, array $seoFields): bool
{
    $layout = $entryType->getFieldLayout();
    $tabs = $layout->getTabs();
    $seoTab = null;

    foreach ($tabs as $tab) {
        if (strcasecmp($tab->name, 'SEO') === 0) {
            $seoTab = $tab;
            break;
        }
    }

    if (!$seoTab) {
        $seoTab = new FieldLayoutTab(['name' => 'SEO', 'sortOrder' => count($tabs) + 1]);
        $seoTab->setLayout($layout);
        $seoTab->setElements([]);
        $tabs[] = $seoTab;
        $layout->setTabs($tabs);
    }

    $elements = $seoTab->getElements();
    $existingUids = [];
    foreach ($elements as $layoutElement) {
        if ($layoutElement instanceof CustomField) {
            $existingUids[] = $layoutElement->getFieldUid();
        }
    }

    $changed = false;
    foreach ($seoFields as $field) {
        if (!in_array($field->uid, $existingUids, true)) {
            $elements[] = new CustomField($field);
            $changed = true;
        }
    }

    if ($changed) {
        $seoTab->setElements($elements);
        $entryType->setFieldLayout($layout);
    }

    return $changed;
}

echo "Content model cleanup\n";

$headerNavigation = $fields->getFieldByHandle('headerNavigation');
$general = $globals->getSetByHandle('general');
if ($headerNavigation && $general && removeFieldFromLayout($general, $headerNavigation)) {
    if (!$globals->saveSet($general)) {
        throw new Exception('general global set: ' . json_encode($general->getErrors()));
    }
    echo "✓ removed Header Navigation from General\n";
}
if ($headerNavigation) {
    if (!$fields->deleteField($headerNavigation)) {
        throw new Exception('could not delete headerNavigation field');
    }
    echo "✓ deleted headerNavigation field\n";
}

$navigationLink = $entries->getEntryTypeByHandle('navigationLink');
if ($navigationLink) {
    if (!$entries->deleteEntryType($navigationLink)) {
        throw new Exception('could not delete navigationLink entry type');
    }
    echo "✓ deleted navigationLink entry type\n";
}

$translations = $globals->getSetByHandle('translations');
if ($translations) {
    $globals->deleteSet($translations);
    echo "✓ deleted Translations global set\n";
}

$singleLineText = $fields->getFieldByHandle('singleLineText');
if ($singleLineText) {
    if (!$fields->deleteField($singleLineText)) {
        throw new Exception('could not delete singleLineText field');
    }
    echo "✓ deleted singleLineText field\n";
}

echo "SEO fields\n";

$seoDescription = $fields->getFieldByHandle('seoDescription');
if (!$seoDescription) {
    $seoDescription = new PlainText([
        'handle' => 'seoDescription',
        'name' => 'SEO Description',
        'instructions' => 'Optional search and social description. Keep it concise; lead text is used as a fallback.',
        'multiline' => true,
        'charLimit' => 160,
        'translationMethod' => 'site',
    ]);
    if (!$fields->saveField($seoDescription)) {
        throw new Exception('seoDescription field: ' . json_encode($seoDescription->getErrors()));
    }
    echo "✓ created seoDescription field\n";
}

$seoImage = $fields->getFieldByHandle('seoImage');
if (!$seoImage) {
    $assetsVolume = Craft::$app->getVolumes()->getVolumeByHandle('assets');
    if (!$assetsVolume) {
        throw new Exception('no "assets" volume');
    }
    $source = "volume:{$assetsVolume->uid}";
    $seoImage = new Assets([
        'handle' => 'seoImage',
        'name' => 'SEO Image',
        'instructions' => 'Optional image for link previews and social sharing.',
        'sources' => '*',
        'defaultUploadLocationSource' => $source,
        'defaultUploadLocationSubpath' => 'seo',
        'restrictedLocationSource' => $source,
        'allowedKinds' => ['image'],
        'restrictFiles' => true,
        'maxRelations' => 1,
        'viewMode' => 'list',
        'previewMode' => 'full',
        'allowUploads' => true,
        'translationMethod' => 'site',
    ]);
    if (!$fields->saveField($seoImage)) {
        throw new Exception('seoImage field: ' . json_encode($seoImage->getErrors()));
    }
    echo "✓ created seoImage field\n";
}

foreach (['home', 'topicPage'] as $handle) {
    $entryType = $entries->getEntryTypeByHandle($handle);
    if (!$entryType) {
        throw new Exception("missing entry type {$handle}");
    }
    if (ensureSeoTab($entryType, [$seoDescription, $seoImage])) {
        if (!$entries->saveEntryType($entryType)) {
            throw new Exception("{$handle} entry type: " . json_encode($entryType->getErrors()));
        }
        echo "✓ added SEO tab to {$handle}\n";
    } else {
        echo "· SEO tab already complete on {$handle}\n";
    }
}

echo "Public GraphQL schema\n";

$gql = Craft::$app->getGql();
$schema = $gql->getPublicSchema();
$scope = [
    'directive:parseRefs',
    'directive:transform',
];
foreach (Craft::$app->getSites()->getAllSites() as $site) {
    $scope[] = "sites.{$site->uid}:read";
}
foreach ($entries->getAllSections() as $section) {
    $scope[] = "sections.{$section->uid}:read";
}
foreach ($globals->getAllSets() as $set) {
    $scope[] = "globalsets.{$set->uid}:read";
}
foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
    $scope[] = "volumes.{$volume->uid}:read";
}
foreach ($fields->getAllFields() as $field) {
    if ($field instanceof Matrix || $field instanceof CkeditorField) {
        $scope[] = "nestedentryfields.{$field->uid}:read";
    }
}
$scope = array_values(array_unique($scope));
sort($scope);
$schema->scope = $scope;
if (!$gql->saveSchema($schema)) {
    throw new Exception('public schema: ' . json_encode($schema->getErrors()));
}
echo "✓ removed unpublished and user scopes\n";

Craft::$app->getProjectConfig()->saveModifiedConfigData();
echo "\nDone. Review config/project/.\n";
