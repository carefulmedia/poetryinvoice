@api
@javascript
Feature: Create contest competition

  Scenario: Create contest competition
    # Login
    Given I am on "/user/login"
    When I fill in the following:
      | edit-name | webmaster@poetryinvoice.com |
      | edit-pass | 1 |
    When I press the "edit-submit" button
    Then I should see the link "Contest" in the "toolbar" region
    
    # Add competition.
    Given I am on "/admin/contest/competition/add/default"
    Then I should see "Add competition" in the "admin_header" region
    Given Sticky elements are removed from page
    When I fill in the following:
      | edit-title-0-value                           | Contest competition test    |
      | edit-field-open-date-0-value-date            | 2022-12-31                  |
      | edit-field-open-date-0-value-time            | 12:00:00                    |
      | edit-field-submission-deadline-0-value-date  | 2023-12-31                  |
      | edit-field-submission-deadline-0-value-time  | 12:00:00                    |
      | edit-field-judging-deadline-0-value-date     | 2024-12-31                  |
      | edit-field-judging-deadline-0-value-time     | 12:00:00                    |
      | edit-field-competition-current-level-0-value | 1                           |
      | edit-field-score-template-0-target-id        | "2023 Scoring template (1)" |
      | edit-field-competition-levels-0-value        | 1                           |
      # Stream
      | edit-field-competition-streams-0-subform-field-label-0-value                   | "Contest competition test english stream" |
      | edit-field-competition-streams-0-subform-field-min-recitations-0-value         | 2 |
      | edit-field-competition-streams-0-subform-field-entries-required-school-0-value | 2 |
      | edit-field-competition-streams-0-subform-field-max-entries-school-0-value      | 3 |
    And I check the box "edit-field-allowed-grades-grade-6"
    And I check the box "edit-field-competition-streams-0-subform-field-stream-languages-en"
    And I check the box "edit-field-online-competition-value"
    And I press the "edit-submit" button
    Then I should see text matching "New competition .* has been created."
    
    # Delete contest.
    # Then I should see the link "Delete" in the "tabs"
    When I click "Delete" in the "tabs"
    Then I should see "Are you sure you want to delete the competition"
    And I click "edit-submit"
    Then I should see text matching "The competition .* has been deleted."
