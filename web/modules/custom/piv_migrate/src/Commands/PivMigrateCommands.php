<?php

namespace Drupal\piv_migrate\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Database;

/**
 * Common commands for migration.
 */
class PivMigrateCommands extends DrushCommands {

  /**
   * Return the migration db.
   */
  public function db() {
    return Database::getConnection('default', 'migrate');
  }

  /**
   * Fix terms migrations.
   *
   * @usage piv_migrate-fix-terms
   *   Fix terms migrations.
   *
   * @command piv_migrate:fix-terms
   */
  public function fixTerms() {
    $entity_type_manager = \Drupal::entityTypeManager();
    $results = $this->db()
      ->select('taxonomy_term_data', 't')
      ->fields('t')
      ->condition('i18n_tsid', 0, '>')
      ->execute()
      ->fetchAll();
    $terms = [];
    foreach ($results as $result) {
      $terms[$result->i18n_tsid][$result->language] = $result;
    }

    // Map fr to en term.
    $map = [];
    foreach ($terms as $term) {
      if (count($term) == 2) {
        $map[$term['fr']->tid] = $term['en']->tid;
      }
    }

    // Default language is english, edit the english term to add a french
    // translation (if one exists) and delete the french one while migrating the
    // french references to english.
    $term_storage = $entity_type_manager->getStorage('taxonomy_term');
    foreach ($terms as $term) {
      if (count($term) == 2) {
        $term_en = $term_storage->load($term['en']->tid) ?? NULL;
        $term_fr = $term_storage->load($term['fr']->tid) ?? NULL;
        if ($term_en && $term_fr) {
          $new_fr_term = $term_en->hasTranslation('fr')
            ? $term_en->getTranslation('fr')
            : $term_en->addTranslation('fr');
          $new_fr_term->name = $term_fr->name->value;
          $new_fr_term->description = $term_fr->description->value;
          $new_fr_term->save();
        }
      }
    }
    // Update nodes.
    $fields = [
      'field_new_theme',
      'field_terms_and_forms',
      'field_moods',
    ];
    $fr_terms = array_keys($map);
    $query = Database::getConnection()->select('node', 'n', ['conjunction' => 'OR']);
    $query->addField('n', 'nid');
    $query->distinct();
    foreach ($fields as $field) {
      $query->leftJoin("node__{$field}", $field, "{$field}.entity_id = n.nid");
      $query->condition("{$field}.{$field}_target_id", $fr_terms, "IN");
    }
    $results = $query->execute()->fetchCol();
    $nodes_referencing_french_terms = $entity_type_manager->getStorage('node')
      ->loadMultiple($results);
    foreach ($nodes_referencing_french_terms as $node) {
      foreach ($fields as $field) {
        $values = array_column($node->{$field}->getValue(), 'target_id');
        foreach ($values as $key => $value) {
          $values[$key] = str_replace(array_keys($map), array_values($map), $value);
        }
        $node->{$field} = $values;
      }
      $node->save();
    }

    // Delete the french terms.
    $terms_to_delete = $term_storage->loadMultiple(array_keys($map));
    $term_storage->delete($terms_to_delete);
  }

}
