<?php
/**
 * Demo content for local development — a home entry and one topic page that
 * exercises every content block, so the frontend has something real to build
 * against before editorial content exists.
 *
 *     ddev exec php scripts/seed-demo-content.php
 *
 * Local only. Re-running replaces the demo topic page rather than duplicating
 * it; other entries are left alone.
 */

require __DIR__ . '/../bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;

$elements = Craft::$app->getElements();
$entries = Craft::$app->getEntries();
$fields = Craft::$app->getFields();
$siteId = Craft::$app->getSites()->getPrimarySite()->id;

$contentBlocksField = $fields->getFieldByHandle('contentBlocks');
$boxesField = $fields->getFieldByHandle('boxes');

function typeId(string $handle): int
{
    $type = Craft::$app->getEntries()->getEntryTypeByHandle($handle);
    if (!$type) {
        throw new Exception("missing entry type {$handle}");
    }
    return $type->id;
}

/**
 * Creates a nested entry owned by $owner inside $field.
 */
function nested(Entry $owner, $field, string $typeHandle, array $values, ?string $title = null): Entry
{
    $entry = new Entry([
        'typeId' => typeId($typeHandle),
        'fieldId' => $field->id,
        'ownerId' => $owner->id,
        'siteId' => $owner->siteId,
        'title' => $title,
    ]);
    $entry->setFieldValues($values);

    if (!Craft::$app->getElements()->saveElement($entry)) {
        throw new Exception("nested {$typeHandle}: " . json_encode($entry->getErrors()));
    }

    return $entry;
}

// ---------------------------------------------------------------------------
// Home
// ---------------------------------------------------------------------------

$home = Entry::find()->section('home')->siteId($siteId)->one();
if ($home) {
    $home->setFieldValues([
        'leadText' => '<p>Was online passiert, hat reale Folgen. Digitale Gewalt '
            . 'verstärkt bestehende Gewaltdynamiken und trifft Betroffene rund um '
            . 'die Uhr: zu Hause, bei der Arbeit, im sozialen Umfeld.</p>',
    ]);
    if (!$elements->saveElement($home)) {
        throw new Exception('home: ' . json_encode($home->getErrors()));
    }
    echo "✓ home lead text\n";
}

// ---------------------------------------------------------------------------
// Demo topic page — replaced on re-run
// ---------------------------------------------------------------------------

$existing = Entry::find()->section('pages')->slug('demo-alle-bloecke')->siteId($siteId)->one();
if ($existing) {
    $elements->deleteElement($existing, true);
    echo "· removed previous demo page\n";
}

$page = new Entry([
    'sectionId' => $entries->getSectionByHandle('pages')->id,
    'typeId' => typeId('topicPage'),
    'siteId' => $siteId,
    'title' => 'Demo – alle Blöcke',
    'slug' => 'demo-alle-bloecke',
    'enabled' => true,
]);
$page->setFieldValues([
    'leadText' => '<p>Diese Seite zeigt jeden Content-Block einmal, damit das '
        . 'Frontend gegen echte Daten entwickelt werden kann.</p>',
]);

if (!$elements->saveElement($page)) {
    throw new Exception('demo page: ' . json_encode($page->getErrors()));
}
echo "✓ demo topic page\n";

nested($page, $contentBlocksField, 'sectionLabel', [], 'Was ist digitale Gewalt?');

nested($page, $contentBlocksField, 'contentRichtext', [
    'richtext' => '<h3>Definition Digitale Gewalt</h3><p>Digitale Gewalt bezeichnet '
        . 'alle Formen von Gewalt, Belästigung, Kontrolle und Einschüchterung, die '
        . 'über digitale Kanäle ausgeübt werden – Smartphones, Social Media, E-Mail, '
        . 'Messaging-Apps, Online-Plattformen oder vernetzte Geräte.</p>',
]);

$boxGroup = nested($page, $contentBlocksField, 'boxGroup', [
    'boxWidth' => 'half',
    'titleSize' => 'large',
]);

nested($boxGroup, $boxesField, 'box', [
    'boxColor' => 'white',
    'richtext' => '<p>Digitale Gewalt verlängert und verstärkt Gewaltdynamiken, die '
        . 'offline bereits bestehen – sei es in Beziehungen, am Arbeitsplatz oder in '
        . 'gesellschaftlichen Gruppen.</p>',
], 'Digitale Gewalt verstärkt bestehende Muster');

nested($boxGroup, $boxesField, 'box', [
    'boxColor' => 'lightblue',
    'richtext' => '<p>Digitale Gewalt kennt keine Öffnungszeiten. Betroffene sind '
        . 'rund um die Uhr erreichbar und finden keinen physischen Rückzugsraum.</p>',
], 'Digitale Gewalt verbaut Rückzugsorte');

nested($page, $contentBlocksField, 'alertBar', [
    'alertColor' => 'rose',
    'richtext' => '<p>Digitale Gewalt ist real und sie kann erdrückend sein. Sie '
        . 'kennt keine räumlichen Grenzen. Sie macht Angst. Doch es gibt '
        . 'Möglichkeiten, sich zu schützen und Hilfe zu holen.</p>',
]);

$accordion = nested($page, $contentBlocksField, 'accordion', []);
$accordionItemsField = $fields->getFieldByHandle('accordionItems');
nested($accordion, $accordionItemsField, 'accordionItem', [
    'richtext' => '<p>Hate Speech liegt vor, wenn eine Person oder Gruppe aufgrund '
        . 'gewisser Identitätsmerkmale beleidigt, abgewertet oder diskriminiert wird.</p>',
], 'Was gilt als Hate Speech?');

$linkList = nested($page, $contentBlocksField, 'linkList', []);
$linkItemsField = $fields->getFieldByHandle('linkItems');
foreach (['Hate Speech', 'Doxing', 'Stalking', 'Mobbing', 'Deepfakes'] as $label) {
    nested($linkList, $linkItemsField, 'linkItem', [
        'itemLink' => ['value' => 'https://example.org/', 'type' => 'url'],
    ], $label);
}

nested($page, $contentBlocksField, 'spacer', ['spacerSize' => 'large']);

echo "✓ content blocks\n";
echo "\nDone. Demo page: /demo-alle-bloecke\n";
