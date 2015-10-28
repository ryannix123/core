Feature: sharing
  Background:
    Given using api version "1"

  Scenario: Creating a new share with user
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

  Scenario: Creating a share with a group
    Given As an "admin"
    And Create user "user0"
    And Create user "user1"
    And Create group "sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And As an "admin"
    And Delete user "user0"
    And Delete user "user1"
    And Delete group "sharing-group"

  Scenario: Creating a new public share
    Given As an "admin"
    And Create user "user0"
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" can be downloaded
    And As an "admin"
    And Delete user "user0"

  Scenario: Creating a new public share with password
    Given As an "admin"
    And Create user "user0"
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded
    And As an "admin"
    And Delete user "user0"

  Scenario: Creating a new public share with password and adding an expiration date
    Given As an "admin"
    And Create user "user0"
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    And Adding expiration date to last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded
    And As an "admin"
    And Delete user "user0"


