@block @block_ai_proctor @javascript
Feature: AI Proctor System Analytics and Performance
  In order to understand system performance and usage patterns
  As an administrator or teacher
  I need to view comprehensive analytics and performance metrics

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | admin1   | Admin     | 1        | admin1@example.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C2     | editingteacher |
    And the following ai proctor evidence exists:
      | userid   | courseid | violation_type | timestamp  | status   | ai_confidence |
      | student1 | C1       | Looking Down   | -2 hours   | active   | 88.5          |
      | student1 | C1       | Turning Left   | -1 hour    | resolved | 92.3          |
      | student2 | C1       | Looking Away   | -30 mins   | active   | 85.7          |
      | student1 | C2       | Multiple Faces | -1 day     | archived | 95.2          |

  @analytics_overview_dashboard
  Scenario: View system-wide analytics dashboard
    Given I log in as "admin1"
    When I navigate to "Reports > AI Proctor Analytics" in site administration
    Then I should see "System Analytics Dashboard"
    And I should see total violation statistics
    And I should see violation trend charts
    And I should see course-wise breakdown
    And I should see user engagement metrics

  @course_analytics_detailed
  Scenario: View detailed course analytics
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "AI Proctor Analytics" in the course administration
    Then I should see "Course Analytics: Course 1"
    And I should see student monitoring statistics
    And I should see violation type distribution
    And I should see time-based violation patterns
    And I should see individual student performance metrics

  @performance_monitoring
  Scenario: Monitor system performance metrics
    Given I log in as "admin1"
    When I navigate to "Reports > AI Proctor Performance" in site administration
    Then I should see "Performance Monitoring"
    And I should see AI processing response times
    And I should see database query performance
    And I should see system resource usage
    And I should see alert thresholds and warnings

  @violation_trend_analysis
  Scenario: Analyze violation trends over time
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I view the AI Proctor analytics dashboard
    And I select "Last 7 days" from the time period filter
    Then I should see a trend chart showing violations over time
    And I should see peak violation periods highlighted
    And I should see correlation with course activities
    And I should see predictive trend indicators

  @student_behavior_patterns
  Scenario: Analyze individual student behavior patterns
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I view detailed student analytics for "student1"
    Then I should see student behavior timeline
    And I should see violation frequency patterns
    And I should see improvement or deterioration trends
    And I should see recommendations for intervention

  @ai_confidence_analysis
  Scenario: Analyze AI confidence levels and accuracy
    Given I log in as "admin1"
    When I view AI performance analytics
    Then I should see AI confidence distribution charts
    And I should see accuracy metrics by violation type
    And I should see false positive/negative rates
    And I should see model performance trends over time

  @resource_usage_monitoring
  Scenario: Monitor system resource usage
    Given I log in as "admin1"
    When I view system performance dashboard
    Then I should see CPU usage statistics
    And I should see memory consumption patterns
    And I should see storage usage for evidence data
    And I should see network bandwidth utilization

  @automated_reports_generation
  Scenario: Generate and schedule automated reports
    Given I log in as "admin1"
    When I configure automated reporting
    And I set up daily violation summary reports
    And I set up weekly performance reports
    Then reports should be generated automatically
    And stakeholders should receive email notifications
    And reports should be available for download

  @real_time_monitoring
  Scenario: Monitor violations in real-time
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I open the real-time monitoring dashboard
    Then I should see live violation feed
    And I should see currently active monitoring sessions
    And I should receive notifications for high-confidence violations
    And I should be able to take immediate action

  @data_export_analytics
  Scenario: Export analytics data for external analysis
    Given I log in as "admin1"
    When I access the data export interface
    And I select violation data for export
    And I choose CSV format
    And I apply date range filters
    Then I should be able to download the data
    And the exported data should include all relevant fields
    And the data should be properly formatted for analysis

  @comparative_analytics
  Scenario: Compare performance across courses and time periods
    Given I log in as "admin1"
    When I access comparative analytics
    And I select multiple courses for comparison
    And I choose different time periods
    Then I should see side-by-side comparisons
    And I should see statistical significance indicators
    And I should see recommendations based on comparisons

  @alert_threshold_management
  Scenario: Configure and manage performance alerts
    Given I log in as "admin1"
    When I configure performance alert thresholds
    And I set violation rate alerts above 10 per hour
    And I set AI confidence alerts below 80%
    And I set system performance alerts for response times over 2 seconds
    Then alerts should trigger when thresholds are exceeded
    And relevant personnel should be notified
    And alert history should be maintained

  @analytics_data_retention
  Scenario: Manage analytics data retention policies
    Given I log in as "admin1"
    When I configure data retention policies
    And I set evidence data retention to 90 days
    And I set analytics data retention to 1 year
    Then old data should be automatically archived
    And system performance should be maintained
    And compliance requirements should be met