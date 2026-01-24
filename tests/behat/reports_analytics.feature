@block @block_ai_proctor @javascript
Feature: AI Proctor Reports and Analytics
  In order to review student exam behavior
  As a teacher
  I need to access comprehensive monitoring reports

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  @block_ai_proctor_reports_access
  Scenario: Teacher can access AI Proctor reports
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "AI Proctor Report" in the page
    And I should see "Course: Course 1" in the page
    And I should see "Evidence Dashboard" in the page

  @block_ai_proctor_reports_filtering
  Scenario: Teacher can filter reports by criteria
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "Filter by User" in the page
    And I should see "Filter by Violation Type" in the page
    And I should see "Date Range" in the page
    And I should see "Status Filter" in the page

  @block_ai_proctor_reports_statistics
  Scenario: Reports show statistical overview
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "Total Evidence Records" in the page
    And I should see "Active Violations" in the page
    And I should see "Students Monitored" in the page
    And I should see "Average Confidence" in the page

  @block_ai_proctor_evidence_details
  Scenario: Teacher can view evidence details
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Proctor" "block"
    And I should see "Evidence Table" in the page
    And I should see "Timestamp" in the page
    And I should see "Violation Type" in the page
    And I should see "AI Confidence" in the page
    And I should see "Status" in the page

  @block_ai_proctor_export_functionality
  Scenario: Reports can be exported
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "Export" in the page
    And I should see "CSV" in the page

  @block_ai_proctor_student_privacy
  Scenario: Student cannot access reports
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should not see "Command Center" in the "AI Exam Proctor" "block"
    And I should not see "📊 Command Center" in the page

  @block_ai_proctor_realtime_monitoring
  Scenario: Reports show real-time activity
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "Active Sessions" in the page
    And I should see "Live Monitoring" in the page
    And I should see "Real-time Alerts" in the page

  @block_ai_proctor_violation_review
  Scenario: Teacher can review and update violation status
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "Mark as Reviewed" in the page
    And I should see "Dismiss" in the page
    And I should see "Add Notes" in the page