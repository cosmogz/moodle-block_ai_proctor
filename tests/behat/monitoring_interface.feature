@block @block_ai_proctor @javascript
Feature: AI Proctor Monitoring and Evidence
  In order to ensure exam integrity
  As a system
  I need to monitor student behavior and capture evidence

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "AI Exam Proctor" block
    And I log out

  @block_ai_proctor_monitoring_interface
  Scenario: Student sees complete monitoring interface
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "AI Proctor System" in the page
    And I should see "Initializing secure monitoring" in the page
    And I should see "Checking camera support" in the page
    And I should see "Loading AI model" in the page
    And I should see "Initializing face detection" in the page

  @block_ai_proctor_camera_requirements
  Scenario: System shows camera requirement messages
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "camera" in the page
    And I should see "MediaPipe" in the page
    And I should see "face" in the page

  @block_ai_proctor_progress_indicators
  Scenario: System shows initialization progress
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see elements with CSS selector "#init-progress"
    And I should see elements with CSS selector "#init-steps"
    And I should see elements with CSS selector "#shield-status"

  @block_ai_proctor_hud_elements
  Scenario: Monitoring HUD contains required elements
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see elements with CSS selector "#ai-hud"
    And I should see elements with CSS selector "#heat-bar"
    And I should see elements with CSS selector "#violation-log"
    And I should see elements with CSS selector "#mini-video-container"

  @block_ai_proctor_violation_limits
  Scenario: System has configured violation thresholds
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then the page source should contain "HEAD_LEFT: 0.40"
    And the page source should contain "HEAD_RIGHT: 0.60"
    And the page source should contain "MOUTH_OPEN: 0.05"
    And the page source should contain "EYE_DOWN_THRESHOLD: 0.40"

  @block_ai_proctor_warning_system
  Scenario: Warning overlay system is present
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see elements with CSS selector "#warning-overlay"
    And I should see elements with CSS selector "#warning-instructions"
    And I should see elements with CSS selector "#warning-countdown"

  @block_ai_proctor_evidence_capture
  Scenario: Evidence capture system is configured
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then the page source should contain "evidenceCanvas"
    And the page source should contain "captureEvidence"
    And the page source should contain "uploadVideoEvidence"

  @block_ai_proctor_security_measures
  Scenario: Security blocking mechanisms are present
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see elements with CSS selector "#click-blocker"
    And I should see "EXAM HIDDEN" in the page
    And I should see "Face Lost. Return to continue" in the page

  @block_ai_proctor_status_tracking
  Scenario: Status tracking elements are present
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see elements with CSS selector "#proctor-status-bar"
    And I should see elements with CSS selector "#header-status"
    And I should see elements with CSS selector "#header-strikes"
    And I should see "Strikes:" in the page
    And I should see "/5" in the page