Feature: sharing
  Background:
    Given using api version "1"

  Scenario: Create user user0
    Given As an "admin"
    And user "user0" does not exist
    When sending "POST" to "/cloud/users" with
      | userid | user0 |
      | password | 123456 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user0" exists

  Scenario: Create user user1
    Given As an "admin"
    And user "user1" does not exist
    When sending "POST" to "/cloud/users" with
      | userid | user1 |
      | password | 123456 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user1" exists

  Scenario: Create a group
    Given As an "admin"
    And group "sharing-group" does not exist
    When sending "POST" to "/cloud/groups" with
      | groupid | sharing-group |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And group "sharing-group" exists

  Scenario: Creating a new share
    Given As an "admin"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Delete a user
    Given As an "admin"
    And user "user0" exists
    When sending "DELETE" to "/cloud/users/user0" 
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user0" does not exist
  
  Scenario: Delete a user
    Given As an "admin"
    And user "user1" exists
    When sending "DELETE" to "/cloud/users/user1" 
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user1" does not exist

  Scenario: Delete a group
    Given As an "admin"
    And group "sharing-group" exists
    When sending "DELETE" to "/cloud/groups/sharing-group"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And group "sharing-group" does not exist



