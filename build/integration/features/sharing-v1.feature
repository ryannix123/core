Feature: sharing
  Background:
    Given using api version "1"

  Scenario: Creating a new share
    Given As an "admin"
    And Create user "user0"
    And Create user "user1"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And As an "admin"
    And Delete user "user0"
    And Delete user "user1"

    Scenario: Creating a new public share
    Given As an "admin"
    And Create user "user0"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareType | 3 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    # A check of the public link is needed here
    And As an "admin"
    And Delete user "user0"



