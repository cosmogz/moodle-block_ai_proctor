@block @block_ai_proctor @javascript
Feature: AI Proctor Block Basic Functionality
  In order to monitor exam integrity
  As a teacher
  I need to add and configure the AI Proctor block

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @block_ai_proctor_add
  Scenario: Teacher can add AI Proctor block to course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    When I add the "AI Exam Proctor" block
    Then I should see "AI Exam Proctor" in the "AI Exam Proctor" "block"
    And I should see "Command Center" in the "AI Exam Proctor" "block"

  @block_ai_proctor_student_view
  Scenario: Student sees monitoring interface
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "AI Exam Proctor" in the "AI Exam Proctor" "block"
    And I should see "AI Proctor System" in the page
    And I should see "Initializing secure monitoring" in the page

  @block_ai_proctor_teacher_access
  Scenario: Teacher can access command center
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I click on "Command Center" "button" in the "AI Exam Proctor" "block"
    Then I should see "AI Proctor Report" in the page
    And I should see "Course 1" in the page

  @block_ai_proctor_configuration
  Scenario: Teacher can configure AI Proctor block
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I configure the "AI Exam Proctor" block
    Then I should see "AI Proctor Configuration" in the page
    And I should see "Block title" in the page

  @block_ai_proctor_privacy_compliance
  Scenario: Block displays privacy information for students
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "MediaPipe" in the page
    And I should see "camera" in the page
    And I should see "monitoring" in the page

  @block_ai_proctor_no_multiple_instances
  Scenario: Block cannot be added multiple times to same course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    When I open the blocks action menu
    Then I should not see "AI Exam Proctor" in the "Add a block..." "select"

  @block_ai_proctor_site_course_restriction
  Scenario: Block cannot be added to site course
    Given I log in as "admin"
    And I am on site homepage
    And I turn editing mode on
    When I open the blocks action menu
    Then I should not see "AI Exam Proctor" in the "Add a block..." "select"