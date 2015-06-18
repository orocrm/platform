<?php

namespace Oro\Bundle\EntityConfigBundle\Provider;

use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PropertyConfigContainer
{
    /**
     * Type Of Config
     */
    const TYPE_ENTITY = 'entity';
    const TYPE_FIELD  = 'field';

    /** @var array */
    protected $config;

    /**
     * @param array $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Gets all configuration values for the given config type
     *
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getItems($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $items = array();
        if (isset($this->config[$type]) && isset($this->config[$type]['items'])) {
            $items = $this->config[$type]['items'];
        }

        return $items;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @param string|null              $fieldType
     * @return array
     */
    public function getDefaultValues($type = self::TYPE_ENTITY, $fieldType = null)
    {
        $type = $this->getConfigType($type);

        $result = array();
        foreach ($this->getItems($type) as $code => $item) {
            if (isset($item['options']['default_value'])
                && ((!$fieldType || !isset($item['options']['allowed_type'])
                    || in_array($fieldType, $item['options']['allowed_type']))
                )
            ) {
                $result[$code] = $item['options']['default_value'];
            }
        }

        return $result;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getNotAuditableValues($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $result = array();
        foreach ($this->getItems($type) as $code => $item) {
            if (isset($item['options']['auditable']) && $item['options']['auditable'] === false) {
                $result[$code] = true;
            }
        }

        return $result;
    }

    /**
     * Get translatable property's codes
     *
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getTranslatableValues($type = self::TYPE_FIELD)
    {
        $type = $this->getConfigType($type);

        $result = array();
        foreach ($this->getItems($type) as $code => $item) {
            if ((isset($item['options']['translatable']) && $item['options']['translatable'] === true)) {
                $result[] = $code;
            }
        }

        return $result;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getIndexedValues($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $result = array();
        foreach ($this->getItems($type) as $code => $item) {
            if (isset($item['options']['indexed']) && $item['options']['indexed'] === true) {
                $result[$code] = true;
            }
        }

        return $result;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @param string|null              $fieldType
     * @return bool
     */
    public function hasForm($type = self::TYPE_ENTITY, $fieldType = null)
    {
        $type = $this->getConfigType($type);

        return (boolean) $this->getFormItems($type, $fieldType);
    }

    /**
     * @param string|ConfigIdInterface $type
     * @param string|null              $fieldType
     * @return bool
     */
    public function getFormItems($type = self::TYPE_ENTITY, $fieldType = null)
    {
        $type = $this->getConfigType($type);

        return array_filter(
            $this->getItems($type),
            function ($item) use ($fieldType) {
                if (!isset($item['form']) || !isset($item['form']['type'])) {
                    return false;
                }

                if ($fieldType
                    && isset($item['options']['allowed_type'])
                    && !in_array($fieldType, $item['options']['allowed_type'])
                ) {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getFormConfig($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $fieldFormConfig = array();
        if (isset($this->config[$type]) && isset($this->config[$type]['form'])) {
            $fieldFormConfig = $this->config[$type]['form'];
        }

        return $fieldFormConfig;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getFormBlockConfig($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $entityFormBlockConfig = null;
        if (isset($this->config[$type])
            && isset($this->config[$type]['form'])
            && isset($this->config[$type]['form']['block_config'])
        ) {
            $entityFormBlockConfig = $this->config[$type]['form']['block_config'];
        }

        return $entityFormBlockConfig;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getGridActions($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $entityGridActions = array();
        if (isset($this->config[$type]) && isset($this->config[$type]['grid_action'])) {
            $entityGridActions = $this->config[$type]['grid_action'];
        }

        return $entityGridActions;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getUpdateActionFilter($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $entityGridActions = null;
        if (isset($this->config[$type]) && isset($this->config[$type]['update_filter'])) {
            $entityGridActions = $this->config[$type]['update_filter'];
        }

        return $entityGridActions;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getLayoutActions($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $entityLayoutActions = array();
        if (isset($this->config[$type]) && isset($this->config[$type]['layout_action'])) {
            $entityLayoutActions = $this->config[$type]['layout_action'];
        }

        return $entityLayoutActions;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getRequiredPropertyValues($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $result = array();
        foreach ($this->getItems($type) as $code => $item) {
            if (isset($item['options']['required_property'])) {
                $result[$code] = $item['options']['required_property'];
            }
        }

        return $result;
    }

    /**
     * Gets a string represents a type of a config
     *
     * @param string|ConfigIdInterface $type
     * @return string
     */
    protected function getConfigType($type)
    {
        if ($type instanceof ConfigIdInterface) {
            return $type instanceof FieldConfigId
                ? PropertyConfigContainer::TYPE_FIELD
                : PropertyConfigContainer::TYPE_ENTITY;
        }

        return $type;
    }

    /**
     * @param string|ConfigIdInterface $type
     * @return array
     */
    public function getRequireJsModules($type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        $result = array();
        if (isset($this->config[$type]) && isset($this->config[$type]['require_js'])) {
            $result = $this->config[$type]['require_js'];
        }

        return $result;
    }

    /**
     * Indicates whether the schema update is required if an attribute with the given code is modified
     *
     * @param string                   $code The attribute name
     * @param string|ConfigIdInterface $type
     *
     * @return bool
     */
    public function isSchemaUpdateRequired($code, $type = self::TYPE_ENTITY)
    {
        $type = $this->getConfigType($type);

        return
            isset($this->config[$type]['items'][$code]['options']['require_schema_update'])
            && $this->config[$type]['items'][$code]['options']['require_schema_update'] === true;
    }
}
