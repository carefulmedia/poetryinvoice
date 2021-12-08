#  Description
This module implements the Contest functionality of Poetry In Voice.

## Notes:

### Score templates are not deletable if there are scores referencing them.
This is implemented in Drupal\piv_contest_score_template\ScoreTemplateAccessControlHandler::checkAccess() method.

### Can't edit the score template from a competition.
When there is a score for a competition, the score template will not be able to be changed from that competition. This is implemented in the Drupal\piv_contest_score_template\Plugin\Validation\Constraint\ScoreTemplateConstraint and added to the entity in the Entity Annotation.

### Custom javascript #states in and constraints in competition
Custom states are implemented on piv_contest_competition_form_competition_form_alter() to display/hide fields depending on other fields, this applies to "Online Competition/Address" and "By invitation only/Invited schools".
The Drupal\piv_contest\Plugin\Validation\Constraint\CompetitionConstraintValidator is responsible to make sure the required fields are populated based on the #states above.

### Streams paragraphs constraints
Added to the paragraphs on piv_contest_entity_type_alter(), this constraint makes sure that the stream minimum number of entries is not bigger than the maximum number of entries.
This is implemented on Drupal\piv_contest\Plugin\Validation\Constraint\StreamConstraintValidator
