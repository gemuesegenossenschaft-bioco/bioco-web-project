<?php namespace ProcessWire;

use DateTimeImmutable;
use Exception;

/**
 * Ensures ProcessWire has the necessary fields/templates for event management
 * and provides automation hooks to flip statuses when events are over.
 */
class EventSetup {
    private const TEMPLATE_NAME = 'event';
    private const PARENT_NAME = 'events';
    private const PARENT_TITLE = 'Events';

    public function __construct(private ProcessWire $wire) {}

    public function bootstrap(): void
    {
        if(!$this->wire instanceof ProcessWire) {
            return;
        }

        try {
            $this->ensureFields();
            $parent = $this->ensureParentPage();
            $this->ensureTemplate($parent);
            $this->registerAutomationHooks();
        } catch(Exception $exception) {
            $this->wire->log()->save('events-setup', $exception->getMessage());
        }
    }

    private function ensureFields(): void
    {
        $modules = $this->wire->modules;

        $typeField = $this->ensureField('event_type', 'FieldtypeOptions', [
            'label' => 'Event Kategorie',
            'description' => 'Unterkategorie innerhalb von Events.',
            'inputType' => 'radios',
            'required' => true,
            'defaultValue' => 'general',
        ]);
        $this->ensureOptions($typeField, [
            'general' => 'Allgemeiner Event',
            'schnuppertag' => 'Schnuppertage',
        ]);

        $statusField = $this->ensureField('event_status', 'FieldtypeOptions', [
            'label' => 'Event Status',
            'inputType' => 'radios',
            'required' => true,
            'defaultValue' => 'upcoming',
        ]);
        $this->ensureOptions($statusField, [
            'upcoming' => 'Kommend',
            'past' => 'Vorbei',
        ]);

        $this->ensureField('event_start', 'FieldtypeDatetime', [
            'label' => 'Beginn',
            'required' => true,
            'dateOutputFormat' => 'Y-m-d H:i',
            'showDateInput' => 1,
            'showTimeInput' => 1,
        ]);

        $this->ensureField('event_end', 'FieldtypeDatetime', [
            'label' => 'Ende',
            'required' => true,
            'dateOutputFormat' => 'Y-m-d H:i',
            'showDateInput' => 1,
            'showTimeInput' => 1,
        ]);

        $this->ensureField('event_location', 'FieldtypeText', [
            'label' => 'Veranstaltungsort',
            'required' => true,
        ]);

        $this->ensureField('event_summary', 'FieldtypeTextarea', [
            'label' => 'Kurzbeschreibung',
            'rows' => 5,
            'required' => true,
        ]);

        $cardImageField = $this->ensureField('event_card_image', 'FieldtypeImage', [
            'label' => 'Event-Kartenbild',
            'description' => 'Bild für Event-Karten. Event-Medien erscheinen nur in der Detailansicht.',
            'maxFiles' => 1,
        ]);
        if(isset($cardImageField->extensions) && $cardImageField->extensions !== 'jpg jpeg png webp') {
            $cardImageField->extensions = 'jpg jpeg png webp';
            $cardImageField->maxFiles = 1;
            $this->wire->fields->save($cardImageField);
        }

        $mediaField = $this->ensureField('event_media', 'FieldtypeFile', [
            'label' => 'Fotos & Videos',
            'description' => 'Dateien erscheinen in der Event-Galerie (jpg, png, webp, mp4, mov).',
            'maxFiles' => 10,
        ]);
        if(isset($mediaField->extensions) && $mediaField->extensions !== 'jpg jpeg png webp mp4 mov webm') {
            $mediaField->extensions = 'jpg jpeg png webp mp4 mov webm';
            $mediaField->maxFiles = 10;
            $this->wire->fields->save($mediaField);
        }

        $this->ensureField('event_signup_enabled', 'FieldtypeCheckbox', [
            'label' => 'Anmeldeformular anzeigen',
            'labelType' => 'custom',
            'text' => 'Dieses Event erlaubt Anmeldungen',
            'checked' => 1,
        ]);

        $this->ensureField('event_signup_notes', 'FieldtypeTextarea', [
            'label' => 'Anmeldehinweise',
            'rows' => 4,
        ]);
    }

    private function ensureTemplate(Page $parentPage): void
    {
        $templates = $this->wire->templates;
        $fields = $this->wire->fields;

        $template = $templates->get(self::TEMPLATE_NAME);
        if(!$template) {
            $template = new Template();
            $template->name = self::TEMPLATE_NAME;
            $template->label = 'Event';
            $template->noChildren = 1;
            $template->slashUrls = 0;
            $template->useRoles = 1;
            $templates->add($template);
        }

        $fieldGroup = $template->fieldgroup ?: new Fieldgroup();
        $fieldGroup->name = self::TEMPLATE_NAME;
        $this->addFieldToGroup($fieldGroup, $fields->get('title'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_type'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_status'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_start'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_end'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_location'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_summary'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_card_image'));
        $this->addFieldToGroup($fieldGroup, $fields->get('body'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_media'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_signup_enabled'));
        $this->addFieldToGroup($fieldGroup, $fields->get('event_signup_notes'));

        if(!$fieldGroup->id) {
            $this->wire->fieldgroups->add($fieldGroup);
        }
        $this->wire->fieldgroups->save($fieldGroup);

        $template->fieldgroup = $fieldGroup;
        $template->set('parentTemplates', [$parentPage->template->id]);
        $template->set('parent', $parentPage->id);
        $templates->save($template);
    }

    private function addFieldToGroup(Fieldgroup $group, Field $field): void
    {
        if($group->has($field)) {
            return;
        }
        $group->add($field);
    }

    private function ensureParentPage(): Page
    {
        $pages = $this->wire->pages;
        $templates = $this->wire->templates;

        $parent = $pages->get("name=" . self::PARENT_NAME);
        if($parent->id) {
            return $parent;
        }

        $basicTemplate = $templates->get('basic-page');
        if(!$basicTemplate) {
            throw new Exception('basic-page template missing – cannot create events parent.');
        }

        $parent = new Page();
        $parent->template = $basicTemplate;
        $parent->parent = $pages->get('/');
        $parent->name = self::PARENT_NAME;
        $parent->title = self::PARENT_TITLE;
        $parent->of(false);
        $parent->save();

        return $parent;
    }

    private function ensureField(string $name, string $type, array $settings = []): Field
    {
        $fields = $this->wire->fields;
        $modules = $this->wire->modules;
        $field = $fields->get($name);
        if(!$field) {
            $field = new Field();
            $field->name = $name;
            $field->type = $modules->get($type);
        }

        foreach($settings as $property => $value) {
            $field->$property = $value;
        }

        $fields->save($field);
        return $field;
    }

    private function ensureOptions(Field $field, array $options): void
    {
        if(!isset($field->options)) {
            return;
        }

        $changed = false;
        $seen = [];

        foreach($field->options as $option) {
            $value = (string) $option->value;
            if(!array_key_exists($value, $options)) {
                continue;
            }
            $seen[$value] = true;
            if($option->title !== $options[$value]) {
                $option->title = $options[$value];
                $changed = true;
            }
        }

        foreach($options as $value => $title) {
            if(isset($seen[$value])) {
                continue;
            }
            $field->addOption($value, $title);
            $changed = true;
        }

        if((int) $field->optionColumns !== 1) {
            $field->optionColumns = 1;
            $changed = true;
        }

        if($changed) {
            $this->wire->fields->save($field);
        }
    }

    private function registerAutomationHooks(): void
    {
        $wire = $this->wire;

        $wire->addHook('LazyCron::everyDay', function() use ($wire) {
            $this->closePastEvents();
        });

        // Run once on bootstrap to keep data fresh even without cron trigger
        $this->closePastEvents();
    }

    private function closePastEvents(): void
    {
        $pages = $this->wire->pages;
        $now = new DateTimeImmutable('now');

        try {
            $expired = $pages->find("template=event, event_status=upcoming, event_end<={$now->format('Y-m-d H:i:s')}");
            if(!$expired->count()) {
                return;
            }

            foreach($expired as $eventPage) {
                try {
                    $eventPage->of(false);
                    $eventPage->event_status = 'past';
                    $eventPage->event_signup_enabled = 0;
                    $eventPage->save(['event_status', 'event_signup_enabled']);
                    $this->wire->log()->save('events-automation', "Event '{$eventPage->title}' marked as past");
                } catch(Exception $e) {
                    $this->wire->log()->save('events-automation', "Failed to update event {$eventPage->id}: {$e->getMessage()}");
                }
            }
        } catch(Exception $e) {
            $this->wire->log()->save('events-automation', "Failed to query expired events: {$e->getMessage()}");
        }
    }
}
