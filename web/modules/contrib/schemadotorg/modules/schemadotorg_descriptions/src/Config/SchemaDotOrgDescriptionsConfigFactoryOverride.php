<?php

declare(strict_types=1);

namespace Drupal\schemadotorg_descriptions\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigCollectionInfo;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideBase;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\schemadotorg\SchemaDotOrgSchemaTypeBuilderInterface;
use Drupal\schemadotorg\SchemaDotOrgSchemaTypeManagerInterface;
use Drupal\schemadotorg\Utility\SchemaDotOrgStringHelper;

/**
 * Provides Schema.org descriptions overrides for the configuration factory.
 *
 * @see \Drupal\config_override\SiteConfigOverrides
 * @see \Drupal\language\Config\LanguageConfigFactoryOverride
 * @see https://www.flocondetoile.fr/blog/dynamically-override-configuration-drupal-8
 * @see https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-override-system
 */
class SchemaDotOrgDescriptionsConfigFactoryOverride extends ConfigFactoryOverrideBase implements SchemaDotOrgDescriptionsConfigFactoryOverrideInterface {
  use StringTranslationTrait;

  /**
   * The cache id.
   */
  const CACHE_ID = 'schemadotorg_descriptions.override';

  /**
   * Tracks if description overrides should be applied.
   */
  protected bool $isApplicable;

  /**
   * Constructs a SchemaDotOrgDescriptionsConfigFactoryOverride object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $defaultCacheBackend
   *   The default cache backend.
   * @param \Drupal\Core\Cache\CacheBackendInterface $discoveryCacheBackend
   *   The discovery cache backend.
   * @param \Drupal\schemadotorg\SchemaDotOrgSchemaTypeManagerInterface $schemaTypeManager
   *   The Schema.org schema type manager.
   * @param \Drupal\schemadotorg\SchemaDotOrgSchemaTypeBuilderInterface $schemaTypeBuilder
   *   The Schema.org schema type builder.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected CacheBackendInterface $defaultCacheBackend,
    protected CacheBackendInterface $discoveryCacheBackend,
    protected SchemaDotOrgSchemaTypeManagerInterface $schemaTypeManager,
    protected SchemaDotOrgSchemaTypeBuilderInterface $schemaTypeBuilder,
  ) {}

  /**
   * Applies description overrides if running tests or UI (not CLI).
   *
   * Description overrides should are only useful via UI and cause a performance
   * hit via CLI. Therefore, we are only going to apply description overrides
   * via the UI and tests.
   *
   * @return bool
   *   Returns TRUE if running tests or UI (not CLI).
   */
  protected function applyDescriptionOverrides(): bool {
    if (isset($this->isApplicable)) {
      return $this->isApplicable;
    }

    $this->isApplicable = drupal_valid_test_ua() || (PHP_SAPI !== 'cli');
    return $this->isApplicable;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    $overrides = $this->getDescriptionOverrides();
    return array_intersect_key($overrides, array_flip($names));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'schemadotorg_descriptions';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    $metadata = new CacheableMetadata();
    // @todo Determine if and how we can stop adding this tag to all config.
    // NOTE: Add the cache tag to only specific names does not work as expected.
    $metadata->addCacheTags(['schemadotorg_descriptions.settings']);
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION): StorableConfigBase|NULL {
    return NULL;
  }

  /**
   * Reacts to the ConfigEvents::COLLECTION_INFO event.
   *
   * @param \Drupal\Core\Config\ConfigCollectionInfo $collection_info
   *   The configuration collection info event.
   */
  public function addCollections(ConfigCollectionInfo $collection_info): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $this->onConfigChange($event);
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $this->onConfigChange($event);
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigRename(ConfigRenameEvent $event): void {
    $this->onConfigChange($event);
  }

  /**
   * Actions to be performed to configuration override on configuration rename.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config event.
   */
  public function onConfigChange(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    $name = $config->getName();

    // Purge cached overrides when any mapping is updated.
    if (str_starts_with($name, 'schemadotorg.schemadotorg_mapping.')) {
      $this->resetDescriptionOverrides();
      return;
    }

    // Purge cached overrides when an entity or field definition is updated.
    $overrides = $this->getDescriptionOverrides();
    if (isset($overrides[$name])) {
      $this->resetDescriptionOverrides();
    }
  }

  /**
   * Reset Schema.org description configuration overrides.
   */
  protected function resetDescriptionOverrides(): void {
    // Reset cached descriptions.
    $this->defaultCacheBackend->delete(static::CACHE_ID);

    // Skip resetting anything else if description overrides are not being applied.
    if (!$this->applyDescriptionOverrides()) {
      return;
    }

    // Reset config.
    $this->configFactory->reset();

    // Reset the entire plugin discovery cache.
    $this->discoveryCacheBackend->deleteAll();
  }

  /**
   * Get Schema.org description configuration overrides.
   *
   * @return array
   *   An array of description configuration overrides for
   *   mapped entity types and fields.
   */
  protected function getDescriptionOverrides(): array {
    if (!$this->applyDescriptionOverrides()) {
      return [];
    }

    if ($cache = $this->defaultCacheBackend->get(static::CACHE_ID)) {
      return $cache->data;
    }

    $overrides = [];

    /* ********************************************************************** */
    // Schema.org type and properties.
    /* ********************************************************************** */

    $type_keys = [];
    $type_overrides = [];

    $property_keys = [];
    $property_overrides = [];

    // Load the unaltered or not overridden Schema.org mapping configuration.
    $config_names = $this->configFactory->listAll('schemadotorg.schemadotorg_mapping.');
    foreach ($config_names as $config_name) {
      $config = $this->configFactory->getEditable($config_name);

      $entity_type_id = $config->get('target_entity_type_id');
      $bundle = $config->get('target_bundle');
      $schema_type = $config->get('schema_type');
      $schema_properties = $config->get('schema_properties') ?: [];
      // @todo We could apply additional mapping Schema.org types to keys.
      $additional_mappings = $config->get('additional_mappings') ?: [];
      foreach ($additional_mappings as $additional_mapping) {
        $schema_properties += $additional_mapping['schema_properties'];
      }

      // Set entity type override.
      $type_overrides["$entity_type_id.type.$bundle"] = $schema_type;
      $type_keys["$entity_type_id.type.$bundle"] = [
        "$entity_type_id--$schema_type--$bundle",
        "$entity_type_id--$schema_type",
        "$entity_type_id--$bundle",
        "$schema_type--$bundle",
        $schema_type,
        $bundle,
      ];

      // Set entity field instance overrides.
      foreach ($schema_properties as $field_name => $schema_property) {
        $property_overrides["field.field.$entity_type_id.$bundle.$field_name"] = $schema_property;
        $property_keys["field.field.$entity_type_id.$bundle.$field_name"] = [
          "$entity_type_id--$schema_type--$schema_property",
          "$entity_type_id--$schema_type--$field_name",
          "$entity_type_id--$bundle--$schema_property",
          "$entity_type_id--$bundle--$field_name",
          "$entity_type_id--$schema_property",
          "$entity_type_id--$field_name",
          "$schema_type--$schema_property",
          "$schema_type--$field_name",
          "$bundle---$schema_property",
          "$bundle--$field_name",
          $schema_property,
          $field_name,
        ];
      }
    }

    $this->setItemDescriptionOverrides('properties', $property_overrides, $property_keys);
    $this->setItemDescriptionOverrides('types', $type_overrides, $type_keys);
    $overrides += $type_overrides + $property_overrides;

    /* ********************************************************************** */
    // Allows unmapped fields to be overwritten.
    /* ********************************************************************** */

    $custom_descriptions = $this->configFactory
      ->getEditable('schemadotorg_descriptions.settings')
      ->get('custom_descriptions') ?? [];

    // Load the unaltered or not overridden field instance configuration.
    $config_names = array_merge(
      $this->configFactory->listAll('field.field.'),
      $this->configFactory->listAll('core.base_field_override.')
    );
    foreach ($config_names as $config_name) {
      if (isset($overrides[$config_name])) {
        continue;
      }

      [, , $entity_type_id, $bundle, $field_name] = explode('.', $config_name);
      $description = $this->getCustomDescription($custom_descriptions, [
        "$entity_type_id--$bundle--$field_name",
        "$entity_type_id--$field_name",
        "$bundle--$field_name",
        $field_name,
      ]);
      if ($description !== FALSE) {
        $overrides[$config_name] = ['description' => $description];
      }
    }

    $this->defaultCacheBackend->set(static::CACHE_ID, $overrides);

    return $overrides;
  }

  /**
   * Sets item description overrides for configurations.
   *
   * @param string $table
   *   The table identifier where the data items are stored.
   * @param array &$overrides
   *   An array of configuration overrides to be updated.
   * @param array $keys
   *   An array of keys mapping configuration names to Schema.org item identifiers.
   *
   * @return array
   *   The modified array of overrides containing the description and help text
   *   as applicable.
   */
  protected function setItemDescriptionOverrides(string $table, array &$overrides, array $keys): array {
    $items = $this->schemaTypeManager->getItems($table, $overrides, ['label', 'comment']);
    $options = ['base_path' => 'https://schema.org/'];

    $help_descriptions = $this->configFactory
      ->getEditable('schemadotorg_descriptions.settings')
      ->get('help_descriptions');
    $custom_descriptions = $this->configFactory
      ->getEditable('schemadotorg_descriptions.settings')
      ->get('custom_descriptions') ?? [];
    foreach ($overrides as $config_name => $id) {
      $custom_description = $this->getCustomDescription($custom_descriptions, $keys[$config_name]);
      if ($custom_description !== FALSE) {
        $description = $custom_description;
        $help = $custom_description;
      }
      elseif (isset($items[$id])) {
        $comment = $items[$id]['comment'];
        // Tidy <br/> tags.
        $comment = preg_replace('#<br[^>]*]>#', '<br/>', $comment);
        // Trim description.
        $comment = SchemaDotOrgStringHelper::getFirstSentence($comment);
        $description = $this->schemaTypeBuilder->formatComment($comment, $options);
        $help = $description;
      }
      else {
        $description = '';
        $help = '';
      }

      $data = $this->configFactory->getEditable($config_name)->getRawData();

      if (empty($data)
        || !empty($data['description'])
        || empty($description)) {
        // Having empty overrides allows us to easily purge them as needed.
        // @see \Drupal\schemadotorg_descriptions\Config\SchemaDotOrgDescriptionsConfigFactoryOverride::onConfigChange
        $overrides[$config_name] = [];
      }
      else {
        $overrides[$config_name] = ['description' => $description];
        if ($help_descriptions) {
          $overrides[$config_name]['help'] = $help;
        }
      }
    }

    return $overrides;
  }

  /**
   * Retrieves a custom description based on the provided keys.
   *
   * @param array $custom_descriptions
   *   An associative array of custom descriptions where keys represent identifiers
   *   and values represent the corresponding descriptions.
   * @param array $keys
   *   An array of keys to search in the custom descriptions.
   *
   * @return string|bool|null
   *   The corresponding custom description as a string if found, NULL if a matching
   *   description is null, or FALSE if no matching key is found in the descriptions.
   */
  protected function getCustomDescription(array $custom_descriptions, array $keys): string|null|bool {
    foreach ($keys as $key) {
      if (array_key_exists($key, $custom_descriptions)) {
        return $custom_descriptions[$key];
      }
    }
    return FALSE;
  }

}
