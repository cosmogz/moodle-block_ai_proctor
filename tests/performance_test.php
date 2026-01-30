<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Performance and load testing for AI Proctor plugin.
 *
 * @package    block_ai_proctor
 * @category   test
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for performance and scalability.
 *
 * @covers \block_ai_proctor
 */
final class performance_test extends \advanced_testcase {

    /** @var stdClass Course object. */
    private $course;

    /** @var array Array of user objects. */
    private $users;

    /**
     * Set up for each test.
     */
    protected function setUp(): void {
        $this->resetAfterTest();

        // Create course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create multiple users for load testing.
        $this->users = [];
        for ($i = 0; $i < 50; $i++) {
            $user = $this->getDataGenerator()->create_user([
                'username' => "testuser{$i}",
                'firstname' => "Test{$i}",
                'lastname' => "User{$i}"
            ]);
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
            $this->users[] = $user;
        }
    }

    /**
     * Test evidence insertion performance.
     */
    public function test_evidence_insertion_performance(): void {
        global $DB;

        $start_time = microtime(true);
        $batch_size = 100;

        // Insert evidence records in batches.
        for ($batch = 0; $batch < 10; $batch++) {
            $evidence_records = [];
            
            for ($i = 0; $i < $batch_size; $i++) {
                $user = $this->users[array_rand($this->users)];
                
                $evidence = new \stdClass();
                $evidence->userid = $user->id;
                $evidence->courseid = $this->course->id;
                $evidence->violation_type = 'looking_away';
                $evidence->evidence_data = json_encode([
                    'confidence' => rand(70, 100) / 100,
                    'timestamp' => time(),
                    'frame_data' => str_repeat('x', 1000) // Simulate image data
                ]);
                $evidence->ai_confidence = rand(70, 100);
                $evidence->timestamp = time() - rand(0, 3600);
                $evidence->status = 'active';

                $evidence_records[] = $evidence;
            }

            // Bulk insert.
            $DB->insert_records('block_ai_proctor_evidence', $evidence_records);
        }

        $end_time = microtime(true);
        $duration = $end_time - $start_time;

        // Should complete within reasonable time (< 5 seconds for 1000 records).
        $this->assertLessThan(5.0, $duration, 
            "Evidence insertion took {$duration} seconds, which is too slow");

        // Verify all records were inserted.
        $count = $DB->count_records('block_ai_proctor_evidence');
        $this->assertEquals(1000, $count);
    }

    /**
     * Test query performance with large datasets.
     */
    public function test_query_performance(): void {
        global $DB;

        // Create large dataset.
        $this->create_large_dataset(5000);

        $user = $this->users[0];

        // Test various query patterns.
        $queries = [
            // Basic user query.
            function() use ($DB, $user) {
                return $DB->get_records('block_ai_proctor_evidence', ['userid' => $user->id]);
            },
            // Time-based query.
            function() use ($DB) {
                $cutoff = time() - 3600;
                return $DB->get_records_select('block_ai_proctor_evidence', 
                    'timestamp > ?', [$cutoff]);
            },
            // Aggregation query.
            function() use ($DB) {
                return $DB->get_records_sql('
                    SELECT userid, COUNT(*) as count, AVG(ai_confidence) as avg_confidence
                    FROM {block_ai_proctor_evidence}
                    WHERE status = ?
                    GROUP BY userid
                ', ['active']);
            },
            // Complex filtering query.
            function() use ($DB) {
                return $DB->get_records_select('block_ai_proctor_evidence',
                    'ai_confidence > ? AND violation_type = ? AND timestamp > ?',
                    [80, 'looking_away', time() - 7200]);
            }
        ];

        foreach ($queries as $index => $query) {
            $start_time = microtime(true);
            $results = $query();
            $end_time = microtime(true);
            $duration = $end_time - $start_time;

            // Each query should complete in under 1 second.
            $this->assertLessThan(1.0, $duration,
                "Query {$index} took {$duration} seconds, which is too slow");
        }
    }

    /**
     * Test concurrent access performance.
     */
    public function test_concurrent_access_simulation(): void {
        global $DB;

        // Simulate concurrent users accessing the system.
        $operations = [];
        
        // Simulate 20 concurrent users.
        for ($i = 0; $i < 20; $i++) {
            $user = $this->users[$i];
            
            $operations[] = function() use ($DB, $user) {
                // Read operations.
                $evidence = $DB->get_records('block_ai_proctor_evidence', 
                    ['userid' => $user->id]);
                
                // Write operations.
                $new_evidence = new \stdClass();
                $new_evidence->userid = $user->id;
                $new_evidence->courseid = $this->course->id;
                $new_evidence->violation_type = 'test_concurrent';
                $new_evidence->evidence_data = json_encode(['test' => 'concurrent']);
                $new_evidence->ai_confidence = 85.0;
                $new_evidence->timestamp = time();
                $new_evidence->status = 'active';
                
                return $DB->insert_record('block_ai_proctor_evidence', $new_evidence);
            };
        }

        $start_time = microtime(true);
        
        // Execute all operations.
        foreach ($operations as $operation) {
            $operation();
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;

        // Should handle concurrent access efficiently.
        $this->assertLessThan(3.0, $duration,
            "Concurrent operations took {$duration} seconds, which is too slow");

        // Verify all operations completed successfully.
        $count = $DB->count_records('block_ai_proctor_evidence', 
            ['violation_type' => 'test_concurrent']);
        $this->assertEquals(20, $count);
    }

    /**
     * Test memory usage with large datasets.
     */
    public function test_memory_usage(): void {
        global $DB;

        $initial_memory = memory_get_usage();

        // Process large dataset.
        $this->create_large_dataset(2000);

        // Simulate processing evidence records.
        $records = $DB->get_records('block_ai_proctor_evidence', [], '', '*', 0, 1000);
        
        $processed = 0;
        foreach ($records as $record) {
            // Simulate evidence processing.
            $data = json_decode($record->evidence_data, true);
            
            // Simple processing to avoid optimization.
            if ($record->ai_confidence > 80) {
                $processed++;
            }
        }

        $final_memory = memory_get_usage();
        $memory_increase = $final_memory - $initial_memory;

        // Memory usage should not be excessive (< 50MB for this test).
        $max_memory = 50 * 1024 * 1024; // 50MB
        $this->assertLessThan($max_memory, $memory_increase,
            "Memory usage increased by {$memory_increase} bytes, which is excessive");

        $this->assertGreaterThan(0, $processed);
    }

    /**
     * Test cleanup task performance.
     */
    public function test_cleanup_performance(): void {
        global $DB;

        // Create old evidence records.
        $old_records = [];
        $cutoff_time = time() - (90 * 24 * 3600); // 90 days ago
        
        for ($i = 0; $i < 1000; $i++) {
            $user = $this->users[array_rand($this->users)];
            
            $evidence = new \stdClass();
            $evidence->userid = $user->id;
            $evidence->courseid = $this->course->id;
            $evidence->violation_type = 'old_evidence';
            $evidence->evidence_data = json_encode(['old' => true]);
            $evidence->ai_confidence = 75.0;
            $evidence->timestamp = $cutoff_time - rand(1, 86400);
            $evidence->status = 'archived';

            $old_records[] = $evidence;
        }

        $DB->insert_records('block_ai_proctor_evidence', $old_records);

        // Create some recent records.
        $recent_records = [];
        for ($i = 0; $i < 200; $i++) {
            $user = $this->users[array_rand($this->users)];
            
            $evidence = new \stdClass();
            $evidence->userid = $user->id;
            $evidence->courseid = $this->course->id;
            $evidence->violation_type = 'recent_evidence';
            $evidence->evidence_data = json_encode(['recent' => true]);
            $evidence->ai_confidence = 85.0;
            $evidence->timestamp = time() - rand(1, 3600);
            $evidence->status = 'active';

            $recent_records[] = $evidence;
        }

        $DB->insert_records('block_ai_proctor_evidence', $recent_records);

        // Test cleanup performance.
        $start_time = microtime(true);

        // Simulate cleanup task.
        $deleted = $DB->delete_records_select('block_ai_proctor_evidence',
            'timestamp < ? AND status = ?', [$cutoff_time, 'archived']);

        $end_time = microtime(true);
        $duration = $end_time - $start_time;

        // Cleanup should be fast.
        $this->assertLessThan(2.0, $duration,
            "Cleanup took {$duration} seconds, which is too slow");

        // Verify cleanup was effective.
        $remaining = $DB->count_records('block_ai_proctor_evidence');
        $this->assertEquals(200, $remaining); // Only recent records should remain
    }

    /**
     * Test database index effectiveness.
     */
    public function test_database_indexes(): void {
        global $DB;

        $this->create_large_dataset(3000);

        // Test queries that should benefit from indexes.
        $indexed_queries = [
            // userid index.
            ['field' => 'userid', 'value' => $this->users[0]->id],
            // courseid index.
            ['field' => 'courseid', 'value' => $this->course->id],
            // timestamp index.
            ['field' => 'timestamp', 'operator' => '>', 'value' => time() - 3600],
            // status index.
            ['field' => 'status', 'value' => 'active'],
        ];

        foreach ($indexed_queries as $query) {
            $start_time = microtime(true);

            if (isset($query['operator'])) {
                $records = $DB->get_records_select('block_ai_proctor_evidence',
                    "{$query['field']} {$query['operator']} ?", [$query['value']]);
            } else {
                $records = $DB->get_records('block_ai_proctor_evidence',
                    [$query['field'] => $query['value']]);
            }

            $end_time = microtime(true);
            $duration = $end_time - $start_time;

            // Indexed queries should be fast.
            $this->assertLessThan(0.5, $duration,
                "Indexed query on {$query['field']} took {$duration} seconds");
        }
    }

    /**
     * Helper method to create large dataset for testing.
     */
    private function create_large_dataset($count): void {
        global $DB;

        $violation_types = ['looking_away', 'looking_down', 'turning_left', 'turning_right', 'multiple_faces'];
        $statuses = ['active', 'resolved', 'archived'];

        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->users[array_rand($this->users)];
            
            $evidence = new \stdClass();
            $evidence->userid = $user->id;
            $evidence->courseid = $this->course->id;
            $evidence->violation_type = $violation_types[array_rand($violation_types)];
            $evidence->evidence_data = json_encode([
                'confidence' => rand(70, 100) / 100,
                'frame_number' => $i,
                'metadata' => str_repeat('data', 100) // Simulate larger data
            ]);
            $evidence->ai_confidence = rand(70, 100);
            $evidence->timestamp = time() - rand(0, 7 * 24 * 3600); // Last week
            $evidence->status = $statuses[array_rand($statuses)];

            $records[] = $evidence;

            // Insert in batches to avoid memory issues.
            if (count($records) >= 100) {
                $DB->insert_records('block_ai_proctor_evidence', $records);
                $records = [];
            }
        }

        // Insert remaining records.
        if (!empty($records)) {
            $DB->insert_records('block_ai_proctor_evidence', $records);
        }
    }
}
