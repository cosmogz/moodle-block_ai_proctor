@block @block_ai_proctor @javascript
Feature: AI Proctor Privacy and GDPR Compliance
  In order to comply with privacy regulations
  As a system administrator
  I need to ensure proper data handling and user rights

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
    And the following ai proctor evidence exists:
      | userid   | courseid | violation_type | timestamp       | status | ai_confidence |
      | student1 | C1       | Looking Down   | -1 day          | active | 85.5          |
      | student1 | C1       | Turning Left   | -2 hours        | active | 92.1          |

  @block_ai_proctor_data_export
  Scenario: User data can be exported for privacy compliance
    Given I log in as "admin"
    When I navigate to "Users > Privacy and policies > Data requests" in site administration
    And I follow "New request"
    And I set the field "Type of request" to "Export all of my personal data"
    And I set the field "User" to "student1"
    And I press "Save changes"
    And I run all adhoc tasks
    Then I should see "Export all of my personal data"
    And the request should include AI Proctor data

  @block_ai_proctor_data_deletion
  Scenario: User data can be deleted for privacy compliance
    Given I log in as "admin"
    When I navigate to "Users > Privacy and policies > Data requests" in site administration
    And I follow "New request" 
    And I set the field "Type of request" to "Delete all of my personal data"
    And I set the field "User" to "student1"
    And I press "Save changes"
    And I run all adhoc tasks
    Then I should see "Delete all of my personal data"
    And AI Proctor data should be removed for the user

  @block_ai_proctor_privacy_metadata
  Scenario: Privacy metadata is properly declared
    Given I log in as "admin"
    When I navigate to "Users > Privacy and policies > Privacy API overview" in site administration
    Then I should see "block_ai_proctor" in the privacy overview
    And I should see "AI Proctor evidence table" in the privacy overview
    And I should see "MediaPipe AI service" in the privacy overview

  @block_ai_proctor_data_retention
  Scenario: Old evidence data is automatically cleaned up
    Given I log in as "admin"
    And there is evidence older than 90 days
    When the cleanup task runs
    Then old evidence should be removed
    And recent evidence should be preserved

  @block_ai_proctor_consent_information
  Scenario: Students see appropriate consent information
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I view the AI Proctor block
    Then I should see monitoring consent information
    And I should see camera usage information
    And I should see data processing information

  @block_ai_proctor_teacher_data_access
  Scenario: Teachers can only access data for their courses
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher2 | C2     | editingteacher |
    When I log in as "teacher2"
    And I try to access Course 1 AI Proctor data
    Then I should be denied access
    And I should see "Access denied" or equivalent message

  @block_ai_proctor_anonymization
  Scenario: Data can be anonymized instead of deleted
    Given I log in as "admin"
    When I anonymize user data for "student1"
    Then AI Proctor evidence should be anonymized
    And violation patterns should be preserved for research
    And personal identifiers should be removed