<?php

namespace modules;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\ckeditor\Field as CkeditorField;
use craft\events\ModelEvent;
use craft\htmlfield\HtmlFieldData;
use yii\base\Event;

/**
 * Site module
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        parent::init();

        // Word pastes spaces as &nbsp; — normalize them in CKEditor fields on save
        Event::on(Element::class, Element::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
            $this->normalizeNbsp($event->sender);
        });
    }

    private function normalizeNbsp(ElementInterface $element): void
    {
        foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if (!$field instanceof CkeditorField) {
                continue;
            }

            $value = $element->getFieldValue($field->handle);
            // Use the raw content; casting to string would render nested entries
            $html = $value instanceof HtmlFieldData ? $value->getRawContent() : (string)$value;
            if ($html === '') {
                continue;
            }

            $clean = preg_replace('/&nbsp;|&#160;|&#xa0;|\x{00A0}/iu', ' ', $html);
            $clean = preg_replace('/ {2,}/', ' ', $clean);

            if ($clean !== null && $clean !== $html) {
                $element->setFieldValue($field->handle, $clean);
            }
        }
    }
}
