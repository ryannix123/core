Feature: sharing
  Background:
    Given using api version "1"

  Scenario: Creating a new share with user
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user0" does not exist
    And user "user1" does not exist

  Scenario: Creating a share with a group
    Given user "user0" exists
    And user "user1" exists
    And group "sharing-group" exists
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And user "user0" does not exist
    And user "user1" does not exist
    And group "sharing-group" does not exist

  Scenario: Creating a new public share
    Given user "user0" exists
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" can be downloaded
    And user "user0" does not exist

  Scenario: Creating a new public share with password
    Given user "user0" exists
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded
    And user "user0" does not exist

  Scenario: Creating a new public share with password and adding an expiration date
    Given user "user0" exists
    And As an "user0"
    When creating a public share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    And Adding expiration date to last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded
    And user "user0" does not exist

