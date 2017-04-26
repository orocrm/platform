@skip
Feature: User configuration
  In order to have ability to change configuration on user level
  As admin
  I need to be able to make such changes

  Scenario: Add comments to user entity
    Given I login as administrator
    And I go to System/User Management/Users
    And I click Configuration on admin in grid "Grid"
    And I click "Display settings"
    And I fill "User Config Form" with:
      | Use parent scope for Position | false   |
      | Position                      | Left    |
    When I press "Save settings"
    Then I should see a "Sided Main Menu" element
