<?php
/**
 * Simplify event time fields:
 * - Replace event_start & event_end with single event_time text field
 * - Example: "14:00 - 17:00 Uhr"
 */

namespace ProcessWire;

header('Content-Type: application/json; charset=utf-8');

$log = [];
$errors = [];

try {
    $log[] = "=== Simplify Event Time Fields ===\n";

    // ====================================================================================
    // 1. CREATE event_time TEXT FIELD
    // ====================================================================================

    $log[] = "Step 1: Creating event_time field...";

    $eventTimeField = $fields->get('event_time');
    if (!$eventTimeField) {
        $eventTimeField = new Field();
        $eventTimeField->type = $modules->get('FieldtypeText');
        $eventTimeField->name = 'event_time';
    }

    $eventTimeField->label = 'Zeit';
    $eventTimeField->description = 'Zeitraum (z.B. 14:00 - 17:00 Uhr)';
    $eventTimeField->placeholder = '14:00 - 17:00 Uhr';
    $eventTimeField->size = 0; // full width
    $eventTimeField->maxlength = 100;

    $fields->save($eventTimeField);
    $log[] = "✓ Created/updated event_time field";

    // ====================================================================================
    // 2. UPDATE EVENT TEMPLATE
    // ====================================================================================

    $log[] = "\nStep 2: Updating event template...";

    $eventTemplate = $templates->get('event');
    if (!$eventTemplate) {
        $errors[] = "Event template not found";
    } else {
        $fg = $eventTemplate->fieldgroup;

        // Add event_time field
        if (!$fg->hasField($eventTimeField)) {
            // Add after event_location or at position 5
            $fg->add($eventTimeField);
            $fg->save();
            $log[] = "  ✓ Added event_time to template";
        } else {
            $log[] = "  event_time already in template";
        }

        // Remove event_start and event_end
        $removed = [];
        foreach (['event_start', 'event_end'] as $fname) {
            $field = $fields->get($fname);
            if ($field && $fg->hasField($field)) {
                $fg->remove($field);
                $removed[] = $fname;
            }
        }

        if (count($removed) > 0) {
            $fg->save();
            $log[] = "  ✓ Removed: " . implode(', ', $removed);
        } else {
            $log[] = "  event_start/event_end already removed";
        }
    }

    // ====================================================================================
    // 3. MIGRATE EXISTING DATA (if needed)
    // ====================================================================================

    $log[] = "\nStep 3: Checking existing event pages...";

    $eventPages = $pages->find("template=event");
    $log[] = "Found " . count($eventPages) . " event pages";

    foreach ($eventPages as $event) {
        $event->of(false);

        // If event has start/end times but no event_time, create one
        if (($event->event_start || $event->event_end) && !$event->event_time) {
            $timeStr = '';

            if ($event->event_start) {
                $timeStr = date('H:i', $event->event_start) . ' Uhr';
            }

            if ($event->event_end) {
                if ($timeStr) {
                    $timeStr = str_replace(' Uhr', '', $timeStr) . ' - ' . date('H:i', $event->event_end) . ' Uhr';
                } else {
                    $timeStr = date('H:i', $event->event_end) . ' Uhr';
                }
            }

            if ($timeStr) {
                $event->event_time = $timeStr;
                $pages->save($event, ['quiet' => true]);
                $log[] = "  ✓ Migrated: {$event->title} → {$timeStr}";
            }
        }
    }

    // ====================================================================================
    // 4. SUMMARY
    // ====================================================================================

    $log[] = "\n=== Simplification Complete ===";
    $log[] = "Event template now uses single 'event_time' text field";
    $log[] = "Format example: 14:00 - 17:00 Uhr";
    $log[] = "\nOld fields (event_start, event_end) removed from template";
    $log[] = "Data migrated to event_time field";

    if (count($errors) > 0) {
        $log[] = "\nErrors (" . count($errors) . "):";
        $log = array_merge($log, $errors);
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'log' => $log,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT);
}
