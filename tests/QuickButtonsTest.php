<?php
/**
 * Quick Buttons Plugin - Self-Contained Test Suite
 *
 * Run: php tests/QuickButtonsTest.php
 * No osTicket bootstrap required — tests use mocks.
 *
 * @author  ChesnoTech
 * @version 2.4.0
 */

class QuickButtonsTest {

    private $passed = 0;
    private $failed = 0;
    private $errors = array();

    function assert($condition, $message) {
        if ($condition) {
            $this->passed++;
            echo "  PASS: {$message}\n";
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  FAIL: {$message}\n";
        }
    }

    function assertEquals($expected, $actual, $message) {
        $this->assert($expected === $actual,
            "{$message} (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
    }

    // ================================================================
    //  Test: choiceKey logic
    // ================================================================

    function testChoiceKey() {
        echo "\n--- Test: choiceKey ---\n";

        // Replicate the choiceKey logic locally
        $choiceKey = function($value) {
            if (!$value) return $value;
            if (is_scalar($value)) {
                $decoded = @json_decode($value, true);
                if (is_array($decoded) && count($decoded) === 1)
                    return (string) key($decoded);
                return (string) $value;
            }
            if (is_array($value) && count($value) === 1)
                return (string) key($value);
            return (string) $value;
        };

        $this->assertEquals('9', $choiceKey('9'), 'Plain scalar string');
        $this->assertEquals('12', $choiceKey(12), 'Int cast to string');
        $this->assertEquals('9', $choiceKey('{"9":"Platform Build (Open)"}'), 'JSON object key');
        $this->assertEquals('12', $choiceKey('{"12":"Assembly"}'), 'JSON dept key');
        $this->assertEquals('9', $choiceKey(array('9' => 'Platform Build')), 'PHP array key');
        $this->assert($choiceKey(null) === null, 'Null returns null');
        $this->assert($choiceKey('') === '', 'Empty returns empty');
        $this->assertEquals('0', $choiceKey('0'), 'Zero string');
    }

    // ================================================================
    //  Test: Widget config JSON parsing
    // ================================================================

    function testWidgetConfigParsing() {
        echo "\n--- Test: Widget Config Parsing ---\n";

        $parse = function($raw) {
            $raw = strip_tags($raw ?: '');
            $data = @json_decode($raw, true);
            return is_array($data) ? $data : array();
        };

        // Valid JSON
        $r = $parse('{"departments":{"12":{"enabled":true}}}');
        $this->assert(!empty($r['departments']['12']['enabled']), 'Parses valid JSON');

        // HTML wrapped (Redactor)
        $r = $parse('<p>{"departments":{"5":{"enabled":false}}}</p>');
        $this->assert(isset($r['departments']['5']), 'Strips HTML wrapping');
        $this->assertEquals(false, $r['departments']['5']['enabled'], 'Parses through HTML');

        // Invalid JSON
        $r = $parse('not json');
        $this->assert(empty($r), 'Invalid JSON returns empty');

        // Empty
        $r = $parse('');
        $this->assert(empty($r), 'Empty returns empty');

        // Null
        $r = $parse(null);
        $this->assert(empty($r), 'Null returns empty');

        // Complex multi-department config
        $json = '{"departments":{"12":{"enabled":true,"start_trigger_status":"7","start_target_status":"9","stop_target_status":"22","stop_transfer_dept":""},"14":{"enabled":true,"start_trigger_status":"22","start_target_status":"36","stop_target_status":"25","stop_transfer_dept":"14","clear_team":true}}}';
        $r = $parse($json);
        $this->assert(count($r['departments']) === 2, 'Multi-dept config');
        $this->assertEquals('', $r['departments']['12']['stop_transfer_dept'], 'Empty transfer for mid-step');
        $this->assertEquals(true, $r['departments']['14']['clear_team'], 'Clear team flag');
    }

    // ================================================================
    //  Test: Config validation rules
    // ================================================================

    function testConfigValidation() {
        echo "\n--- Test: Config Validation ---\n";

        // Color validation regex
        $isValidColor = function($c) { return preg_match('/^#[0-9A-Fa-f]{3,6}$/', $c); };

        $this->assert($isValidColor('#128DBE'), 'Valid 6-digit hex');
        $this->assert($isValidColor('#fff'), 'Valid 3-digit hex');
        $this->assert($isValidColor('#27ae60'), 'Valid green hex');
        $this->assert(!$isValidColor('red'), 'Reject named color');
        $this->assert(!$isValidColor('#gggggg'), 'Reject invalid hex');
        $this->assert(!$isValidColor('128DBE'), 'Reject without hash');
        $this->assert(!$isValidColor('#12'), 'Reject 2-digit hex');

        // Required field validation
        $validate = function($deptCfg) {
            $required = array('start_trigger_status', 'start_target_status', 'stop_target_status');
            foreach ($required as $f)
                if (empty($deptCfg[$f])) return "Missing: {$f}";
            return true;
        };

        $this->assertEquals(true, $validate(array(
            'start_trigger_status' => '7', 'start_target_status' => '9', 'stop_target_status' => '25'
        )), 'All required fields present');

        $this->assertEquals('Missing: start_trigger_status', $validate(array(
            'start_trigger_status' => '', 'start_target_status' => '9', 'stop_target_status' => '25'
        )), 'Missing trigger detected');

        $this->assertEquals('Missing: stop_target_status', $validate(array(
            'start_trigger_status' => '7', 'start_target_status' => '9', 'stop_target_status' => ''
        )), 'Missing stop target detected');
    }

    // ================================================================
    //  Test: formatDuration
    // ================================================================

    function testFormatDuration() {
        echo "\n--- Test: formatDuration ---\n";

        $fmt = function($totalSec) {
            if ($totalSec < 60) return $totalSec . 's';
            if ($totalSec < 3600) return round($totalSec / 60) . 'm';
            if ($totalSec < 86400) return round($totalSec / 3600, 1) . 'h';
            return round($totalSec / 86400, 1) . 'd';
        };

        $this->assertEquals('0s', $fmt(0), '0 seconds');
        $this->assertEquals('30s', $fmt(30), '30 seconds');
        $this->assertEquals('59s', $fmt(59), '59 seconds');
        $this->assertEquals('1m', $fmt(60), '1 minute');
        $this->assertEquals('5m', $fmt(300), '5 minutes');
        $this->assertEquals('1h', $fmt(3600), '1 hour');
        $this->assertEquals('1.5h', $fmt(5400), '1.5 hours');
        $this->assertEquals('1d', $fmt(86400), '1 day');
        $this->assertEquals('2.5d', $fmt(216000), '2.5 days');
    }

    // ================================================================
    //  Test: Status-driven button resolution logic
    // ================================================================

    function testButtonResolution() {
        echo "\n--- Test: Button Resolution ---\n";

        // Simulate resolveButton logic
        $resolve = function($ticketInfo, $widgets) {
            if (!$ticketInfo || !$ticketInfo['topic'] || !$ticketInfo['dept'] || !$ticketInfo['status'])
                return null;

            foreach ($widgets as $w) {
                if ((string) $w['topic'] !== (string) $ticketInfo['topic']) continue;
                $deptCfg = $w['depts'][$ticketInfo['dept']] ?? null;
                if (!$deptCfg) continue;

                if ($deptCfg['start_trigger'] && $ticketInfo['status'] === $deptCfg['start_trigger'])
                    return array('action' => 'start', 'widgetId' => $w['id']);
                if ($deptCfg['start_target'] && $ticketInfo['status'] === $deptCfg['start_target'])
                    return array('action' => 'stop', 'widgetId' => $w['id']);
            }
            return null;
        };

        $widgets = array(
            array('id' => 16, 'topic' => '12', 'depts' => array(
                '12' => array('start_trigger' => '7', 'start_target' => '9')
            )),
            array('id' => 17, 'topic' => '12', 'depts' => array(
                '12' => array('start_trigger' => '22', 'start_target' => '36')
            )),
            array('id' => 20, 'topic' => '16', 'depts' => array(
                '12' => array('start_trigger' => '7', 'start_target' => '9')
            )),
        );

        // Start button on matching status
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => '7'), $widgets);
        $this->assertEquals('start', $r['action'], 'Start on trigger status');
        $this->assertEquals(16, $r['widgetId'], 'Correct widget for step 1');

        // Stop button on working status
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => '9'), $widgets);
        $this->assertEquals('stop', $r['action'], 'Stop on working status');

        // Step 2 start on Case Assembly status
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => '22'), $widgets);
        $this->assertEquals('start', $r['action'], 'Step 2 start on Case Assembly');
        $this->assertEquals(17, $r['widgetId'], 'Correct widget for step 2');

        // Different topic uses different widget
        $r = $resolve(array('topic' => '16', 'dept' => '12', 'status' => '7'), $widgets);
        $this->assertEquals(20, $r['widgetId'], 'Different topic → different widget');

        // No button on unconfigured status
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => '25'), $widgets);
        $this->assert($r === null, 'No button on unconfigured status');

        // No button on unconfigured dept
        $r = $resolve(array('topic' => '12', 'dept' => '99', 'status' => '7'), $widgets);
        $this->assert($r === null, 'No button on unconfigured dept');

        // No button on unconfigured topic
        $r = $resolve(array('topic' => '99', 'dept' => '12', 'status' => '7'), $widgets);
        $this->assert($r === null, 'No button on unconfigured topic');

        // Null ticket info
        $r = $resolve(null, $widgets);
        $this->assert($r === null, 'Null info returns null');

        // Missing status
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => null), $widgets);
        $this->assert($r === null, 'Missing status returns null');

        // Mutual exclusivity: same ticket can't match both start and stop
        // Status 7 → start (widget 16), status 9 → stop (widget 16)
        // Status 22 → start (widget 17), status 36 → stop (widget 17)
        // A ticket in status 7 can ONLY be start, never stop
        $r = $resolve(array('topic' => '12', 'dept' => '12', 'status' => '36'), $widgets);
        $this->assertEquals('stop', $r['action'], 'Step 2 stop on Case Assembly Working');
    }

    // ================================================================
    //  Test: Undo state structure
    // ================================================================

    function testUndoState() {
        echo "\n--- Test: Undo State ---\n";

        // Simulate undo state
        $prevState = array(
            'status_id' => 7,
            'staff_id'  => 0,
            'team_id'   => 12,
            'dept_id'   => 12,
        );

        $undoData = array(
            'action'   => 'start',
            'tickets'  => array(621 => $prevState),
            'time'     => time(),
            'staff_id' => 1,
        );

        $this->assert(!empty($undoData['tickets']), 'Undo has ticket data');
        $this->assertEquals(7, $undoData['tickets'][621]['status_id'], 'Undo preserves prev status');
        $this->assertEquals(0, $undoData['tickets'][621]['staff_id'], 'Undo preserves prev staff');

        // Expiry check
        $undoData['time'] = time() - 30;
        $this->assert(time() - $undoData['time'] <= 60, '30s old → not expired');

        $undoData['time'] = time() - 61;
        $this->assert(time() - $undoData['time'] > 60, '61s old → expired');
    }

    // ================================================================
    //  Run all
    // ================================================================

    function run() {
        echo "=== Quick Buttons Test Suite v2.4 ===\n";

        $this->testChoiceKey();
        $this->testWidgetConfigParsing();
        $this->testConfigValidation();
        $this->testFormatDuration();
        $this->testButtonResolution();
        $this->testUndoState();

        echo "\n=== Results: {$this->passed} passed, {$this->failed} failed ===\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->errors as $err)
                echo "  - {$err}\n";
        }
        return $this->failed === 0;
    }
}

// Run
$test = new QuickButtonsTest();
$ok = $test->run();
exit($ok ? 0 : 1);
