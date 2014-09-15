OroEntityBundle
===============

Entity and entity field selectors, extended functionality of Doctrine entity manager.

**Entity Manager**

In order to extend some native Doctrine Entity Manager functionality a new class `OroEntityManager` was implemented.
In case any other modification are required, your class should extend `OroEntityManager` instead of Doctrine Entity Manager.

**Filter Collection**

Standard Doctrine filter collection implementation allows to add/enable sql filter by passing class name only.
It makes impossible to inject custom services into filters. To provide this functionality,
a new `FilterCollection` class was implemented that allows to add filter objects directly.

Necessary filters can be automatically added to the filters collection by adding `oro_entity.orm.sql_filter` tag:

```yml
oro_security.orm.ownership_sql_filter:
    class: %oro_security.orm.ownership_sql_filter.class%
    arguments:
       - @doctrine.orm.entity_manager
    tags:
       - { name: oro_entity.orm.sql_filter, filter_name: ownershipFilter, enabled: true }
```

where

 - **filter_name** - required filter name,
 - **enbaled** - flag, if the filter must be enabled, by default filters are disabled

## Doctrine field types ##

Some entities have fields which data is money or percents.

For this data was created new field types - money and percent.

**money** field type allow to store money data. It's an alias to decimal(19,4) type.

You can use this field type like:

```php
    /**
     * @var decimal
     *
     * @ORM\Column(name="tax_amount", type="money")
     */
    protected $taxAmount;
```

**percent** field type allow to store percent data. It's an alias to float type.

You can use this field type like:

```php
    /**
     * @var float
     *
     * @ORM\Column(name="percent_field", type="percent")
     */
    protected $percentField;
```

This two data types are available in extend fields. You can create new fields with this types. Additionally in view pages, in grids and in edit pages this fields will be automatically formatted with currency or percent formatters.

In grid, for percent data type will be automatically generated percent filter.
