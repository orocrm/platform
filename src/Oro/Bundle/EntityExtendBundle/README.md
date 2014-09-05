OroEntityExtendBundle
=====================

- Allows to add an additional fields into existing entities through UI or using configuration files
- Allows to add new entities through UI or using configuration files

Manage entities through UI
--------------------------

To manage existing entities or create new ones through UI go to **System > Entities > Entity Management** page. On this page you can see a list of all entities, but please note that you can modify only entities marked as extendable. Check **IS EXTEND** column to see whether an entity can be modified or not. To create a new entity click **Create entity** button at the top right corner of the page, fill the form and click **Save And Close**. Next add necessary fields to your entity clicking on **Create field** button. To add new field to existing entity go to view page of this entity and click **Create field** button. When all changes are made do not forget to click **Update schema** button to apply your changes.

Modify existing entity
----------------------
The existing entity can be changed using data migrations. To create new extended field you can use `addColumn` method with a special options named `oro_options`. The following example shows it:

``` php
<?php

namespace OroCRM\Bundle\AccountBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class OroCRMAccountBundle implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->createTable('orocrm_account');
        $table->addColumn(
            'extend_description',
            'text',
            [
                'oro_options' => [
                    'extend'   => ['is_extend' => true, 'owner' => ExtendScope::OWNER_CUSTOM],
                    'datagrid' => ['is_visible' => false],
                    'merge'    => ['display' => true],
                ]
            ]
        );
    }
}
```
Creating option set column or relations is more complex task and there is a special extension for [Migration bundle](../MigrationBundle/README.md#extensions-for-database-structure-migrations) named [ExtendExtension](Migration/Extension/ExtendExtension.php). Your migration should implement [ExtendExtensionAwareInterface](Migration/Extension/ExtendExtensionAwareInterface.php) before you can use this extension. The following example shows how to create option set column:

``` php
<?php

namespace OroCRM\Bundle\SalesBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroCRMSalesBundle implements Migration, ExtendExtensionAwareInterface
{
    protected $extendExtension;

    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->createTable('orocrm_sales_lead');
        $extendExtension->addOptionSet(
            $schema,
            $table,
            'extend_source',
            [
                'extend' => ['is_extend' => true, 'set_expanded' => false]
            ]
        );
    }
}
```

Also you can use [OroOptions](Migration/OroOptions.php) class to build `oro_options`. It can be helpful in same cases. The following example shows how to use this class:

``` php
<?php

namespace Acme\Bundle\TestBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AcmeTestBundle implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $options = new OroOptions();

        // include Email entity in 'acme' group
        // please note that 'append' method adds new value in additional to existing values
        // so, if Email entity was already included in some other groups this information will not be lost
        $options->append('grouping', 'groups', 'acme');

        $table = $schema->getTable('oro_email');
        $table->addOption(OroOptions::KEY, $options);
    }
}
```

Create custom entity
--------------------
A custom entity is an entity which has no PHP class in any bundle. The definition of such entity is created automatically in Symfony cache. To create a custom entity you can use [ExtendExtension](Migration/Extension/ExtendExtension.php). The following example shows it:

``` php
<?php

namespace Acme\Bundle\TestBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AcmeTestBundle implements Migration, ExtendExtensionAwareInterface
{
    protected $extendExtension;

    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $this->extendExtension->createCustomEntityTable(
            $schema,
            'TestCustomEntity'
        );
        $table->addColumn(
            'name',
            'string',
            [
                'length' => 100,
                'oro_options' => [
                    'extend'  => ['owner' => ExtendScope::OWNER_CUSTOM],
                ]
            ]
        );
        $this->extendExtension->addManyToOneRelation(
            $schema,
            $table,
            'users',
            'oro_user',
            'first_name'
        );
    }
}
```

Preparing entity extend configuration
-------------------------------------
The following command prepares extended entities configuration:

```bash
php app/console oro:entity-extend:update-config
```

Warming up the cache
--------------------
To save entity extend configuration stored in the database to the application cache, the following command can be used:

```bash
php app/console oro:entity-extend:cache:warmup
```

Clearing up the cache
---------------------
The following command removes all data related to entity extend functionality from the application cache:

```bash
php app/console oro:entity-extend:cache:clear --no-warmup
```
To reload all cached data just run this command without `--no-warmup` option.
