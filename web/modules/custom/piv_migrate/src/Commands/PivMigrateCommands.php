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

  /**
   * Migrate users address.
   *
   * @usage piv_migrate-user-address
   *   Fix terms migrations.
   *
   * @command piv_migrate:user-address
   */
  public function migrateUserAddress() {
    $entity_type_manager = \Drupal::entityTypeManager();
    $user_storage = $entity_type_manager->getStorage('user');
    $query = $this->db()->select('location_instance', 'i');
    $query->join('location', 'l', 'l.lid = i.lid');
    $addresses = $query->condition('i.uid', 0, '>')
      ->fields('l')
      ->fields('i')
      ->execute()
      ->fetchAllAssoc('uid');

    foreach ($addresses as $uid => $address) {
      if ($user = $user_storage->load($uid)) {
        if ($user->field_address->isEmpty()) {
          if ($user->hasRole('poet_network') || $user->hasRole('poet')) {
            if (!empty($address->province)
            || !empty($address->city)
            || !empty($address->postal_code)
            || !empty($address->street)
            || !empty($address->additional)) {
              $user->field_address = [
                'country_code' => empty($address->country) ? NULL : strtoupper($address->country),
                'administrative_area' => empty($address->province) ? NULL : strtoupper($address->province),
                'locality' => $address->city ?? NULL,
                'dependent_locality' => NULL,
                'postal_code' => $address->postal_code ?? NULL,
                'sorting_code' => NULL,
                'address_line1' => $address->street ?? NULL,
                'address_line2' => $address->additional ?? NULL,
                'organization' => NULL,
                'given_name' => NULL,
                'additional_name' => NULL,
                'family_name' => NULL,
              ];
              $user->save();
            }
          }
        }
      }
    }
  }

  /**
   * Migrate from textfield to youtube field.
   *
   * @usage piv_migrate-fix-video
   *   Fix video migrations.
   *
   * @command piv_migrate:fix-video
   */
  public function fixVideo() {
    $entity_type_manager = \Drupal::entityTypeManager();
    $nodes = $entity_type_manager->getStorage('node')
      ->loadByProperties([
        'type' => 'video',
      ]);
    $media_storage = $entity_type_manager->getStorage('media');
    foreach ($nodes as $node) {
      if ($node->field_video->isEmpty() && $video = $node->field_yt_video->value) {
        // Transform embed links into normal youtube links.
        if (strpos($video, 'embed') !== FALSE) {
          $video_id = str_replace('/embed/', '', parse_url($video, PHP_URL_PATH));
          $video = "https://youtu.be/{$video_id}";
        }
        $media = $media_storage->create([
          'field_media_oembed_video' => $video,
          'bundle' => 'remote_video',
        ]);
        $media->save();
        $node->field_video = $media->id();
        $node->save();
      }
    }
  }

}
